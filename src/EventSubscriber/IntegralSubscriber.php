<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use OrderCoreBundle\Event\AfterOrderCancelEvent;
use OrderCoreBundle\Event\AfterOrderCreatedEvent;
use OrderCoreBundle\Event\BeforeOrderCreatedEvent;
use OrderCoreBundle\Event\BeforeOrderProductRefundEvent;
use OrderCoreBundle\Event\CreateOrderFailedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Tourze\OrderCheckoutBundle\Service\IntegralDeductionService;
use Tourze\OrderCheckoutBundle\Service\IntegralRecordService;
use Tourze\OrderCheckoutBundle\Service\IntegralRefundService;

/**
 * 积分订单事件订阅器
 *
 * 职责：
 * 1. 订单创建前检查并扣除积分
 * 2. 订单创建后记录积分操作信息
 * 3. 订单创建失败时退还积分
 * 4. 订单取消时退还积分
 * 5. 订单退款时退还积分
 */
#[WithMonologChannel(channel: 'order_integral')]
readonly final class IntegralSubscriber
{
    public function __construct(
        private LoggerInterface $logger,
        private IntegralDeductionService $deductionService,
        private IntegralRefundService $refundService,
        private IntegralRecordService $recordService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * 订单创建前：检查积分余额并扣除积分
     */
    #[AsEventListener]
    public function checkAndDeductIntegral(BeforeOrderCreatedEvent $event): void
    {
        $contract = $event->getContract();
        $user = $contract->getUser();

        if (null === $user) {
            return;
        }

        // 遍历商品，逐个扣除积分
        foreach ($contract->getProducts() as $product) {
            $integralPrice = $product->getIntegralPrice();

            // 如果商品没有积分价格，跳过
            if (null === $integralPrice || $integralPrice <= 0) {
                continue;
            }

            // 如果已经扣减但未返还，跳过（避免重复扣减）
            if (null !== $product->getIntegralDeductedTime() && null === $product->getIntegralRefundedTime()) {
                continue;
            }

            // 扣除该商品的积分
            $this->deductionService->deductIntegral(
                $contract,
                $user->getUserIdentifier(),
                $integralPrice
            );

            // 记录扣减时间
            $product->setIntegralDeductedTime(new \DateTimeImmutable());
            // 清除之前的返还时间（如果是重新扣减）
            $product->setIntegralRefundedTime(null);
        }
    }

    /**
     * 订单创建后：创建积分操作记录
     */
    #[AsEventListener]
    public function createIntegralRecord(AfterOrderCreatedEvent $event): void
    {
        $contract = $event->getContract();
        $user = $contract->getUser();

        if (null === $user) {
            return;
        }

        // 只为已扣减但未返还的商品创建记录
        foreach ($contract->getProducts() as $product) {
            // 检查是否已扣减且未返还
            if (null === $product->getIntegralDeductedTime()) {
                continue;
            }

            if (null !== $product->getIntegralRefundedTime()) {
                continue;
            }

            $integralPrice = $product->getIntegralPrice();
            if (null !== $integralPrice && $integralPrice > 0) {
                $this->recordService->createIntegralInfoForProduct($contract, $user, $product);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * 订单创建失败：退还已扣除的积分
     */
    #[AsEventListener]
    public function refundIntegralOnFailure(CreateOrderFailedEvent $event): void
    {
        $contract = $event->getContract();
        $user = $contract->getUser();

        if (null === $user) {
            return;
        }

        // 只退还已经扣减但未返还的商品积分
        foreach ($contract->getProducts() as $product) {
            // 检查是否已经扣减过积分且未返还
            if (null === $product->getIntegralDeductedTime()) {
                continue;
            }

            // 如果已经返还过，跳过
            if (null !== $product->getIntegralRefundedTime()) {
                continue;
            }

            $integralPrice = $product->getIntegralPrice();
            if (null !== $integralPrice && $integralPrice > 0) {
                $this->refundService->refundProductOnFailure($contract, $user, $product);
                // 记录返还时间
                $product->setIntegralRefundedTime(new \DateTimeImmutable());
            }
        }

        $this->entityManager->flush();
    }

    /**
     * 订单取消：退还积分
     */
    #[AsEventListener]
    public function refundIntegralOnCancel(AfterOrderCancelEvent $event): void
    {
        $contract = $event->getContract();
        $user = $contract->getUser();

        if (null === $user) {
            return;
        }

        // 遍历商品，退还已扣减但未返还的积分
        foreach ($contract->getProducts() as $product) {
            // 检查是否已扣减且未返还
            if (null === $product->getIntegralDeductedTime()) {
                continue;
            }

            if (null !== $product->getIntegralRefundedTime()) {
                continue;
            }

            $integralPrice = $product->getIntegralPrice();
            if (null !== $integralPrice && $integralPrice > 0) {
                // 退还积分
                $this->refundService->refundProductOnCancel($contract, $user, $product);

                // 记录返还时间
                $product->setIntegralRefundedTime(new \DateTimeImmutable());

                // 更新积分记录的退款状态
                $this->recordService->updateIntegralInfoRefundStatusByProduct($contract, $product);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * 商品退款：退还商品的积分
     */
    #[AsEventListener]
    public function refundIntegralOnProductRefund(BeforeOrderProductRefundEvent $event): void
    {
        $product = $event->getProduct();
        $contract = $event->getContract();
        $user = $contract->getUser();

        if (null === $user) {
            $this->logger->error('退款时用户不存在', [
                'orderId' => $contract->getId(),
                'productId' => $product->getId(),
            ]);
            return;
        }

        // 检查是否已扣减且未返还
        if (null === $product->getIntegralDeductedTime()) {
            return;
        }

        if (null !== $product->getIntegralRefundedTime()) {
            return;
        }

        $integralPrice = $product->getIntegralPrice();
        if (null === $integralPrice || $integralPrice <= 0) {
            return;
        }

        // 退还积分
        $this->refundService->refundProductOnCancel($contract, $user, $product);

        // 记录返还时间
        $product->setIntegralRefundedTime(new \DateTimeImmutable());

        // 更新积分记录
        $this->recordService->updateIntegralInfoRefundStatusByProduct($contract, $product);
    }
}
