<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderPrice;
use OrderCoreBundle\Entity\OrderProduct;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\IntegralServiceContracts\Exception\IntegralServiceException;
use Tourze\IntegralServiceContracts\IntegralServiceInterface;
use Tourze\OrderCheckoutBundle\Entity\OrderIntegralInfo;
use Tourze\OrderCheckoutBundle\Repository\OrderIntegralInfoRepository;

/**
 * 积分记录服务
 *
 * 职责：
 * - 创建积分扣除记录
 * - 更新积分退款状态
 * - 查询积分余额
 */
#[WithMonologChannel(channel: 'order_checkout')]
readonly class IntegralRecordService
{
    public function __construct(
        private LoggerInterface $logger,
        private ?IntegralServiceInterface $integralService,
        private EntityManagerInterface $entityManager,
        private OrderIntegralInfoRepository $integralInfoRepository,
    ) {
    }

    /**
     * 为单个商品创建积分信息记录
     */
    public function createIntegralInfoForProduct(
        Contract $contract,
        UserInterface $user,
        OrderProduct $product,
    ): void {
        $integralAmount = $product->getIntegralPrice();

        if (null === $integralAmount || $integralAmount <= 0) {
            return;
        }

        // 查询扣款后的余额
        $balanceAfter = $this->getIntegralBalance($user->getUserIdentifier());
        $balanceBefore = $balanceAfter + $integralAmount;

        $integralInfo = new OrderIntegralInfo();
        $integralInfo->setOrderId($contract->getId());
        $integralInfo->setUserId((int) $user->getUserIdentifier());
        $integralInfo->setProductId($product->getId());
        $integralInfo->setIntegralRequired($integralAmount);
        $integralInfo->setIntegralOperationId($this->generateOperationIdForProduct($contract, $product));
        $integralInfo->setDeductedTime($product->getIntegralDeductedTime() ?? new \DateTimeImmutable());
        $integralInfo->setBalanceBefore($balanceBefore);
        $integralInfo->setBalanceAfter($balanceAfter);
        $integralInfo->setRemark(sprintf('订单 %s 商品 %s 扣除积分', $contract->getSn(), $product->getId()));

        $this->entityManager->persist($integralInfo);
    }

    /**
     * 为单个价格创建积分信息记录
     *
     * @deprecated 使用 createIntegralInfoForProduct 代替
     */
    public function createIntegralInfoForPrice(
        Contract $contract,
        UserInterface $user,
        OrderPrice $price,
    ): void {
        if (!$price->isPaid()) {
            return;
        }

        $integralAmount = $contract->getTotalIntegral();

        if (null === $integralAmount || $integralAmount <= 0) {
            return;
        }

        // 查询扣款后的余额
        $balanceAfter = $this->getIntegralBalance($user->getUserIdentifier());
        $balanceBefore = $balanceAfter + $integralAmount;

        $integralInfo = new OrderIntegralInfo();
        $integralInfo->setOrderId($contract->getId());
        $integralInfo->setUserId((int) $user->getUserIdentifier());
        $integralInfo->setIntegralRequired($integralAmount);
        $integralInfo->setIntegralOperationId($this->generateOperationId($contract, $price));
        $integralInfo->setDeductedTime(new \DateTime());
        $integralInfo->setBalanceBefore($balanceBefore);
        $integralInfo->setBalanceAfter($balanceAfter);
        $integralInfo->setRemark(sprintf('订单 %s 扣除积分', $contract->getSn()));

        $this->entityManager->persist($integralInfo);
    }

    /**
     * 更新积分记录的退款状态（基于商品）
     */
    public function updateIntegralInfoRefundStatusByProduct(
        Contract $contract,
        OrderProduct $product,
    ): void {
        $integralInfos = $this->integralInfoRepository->findBy([
            'orderId' => $contract->getId(),
            'productId' => $product->getId(),
            'isRefunded' => false,
        ]);

        foreach ($integralInfos as $integralInfo) {
            $integralInfo->setIsRefunded(true);
            $integralInfo->setRefundedTime(new \DateTimeImmutable());
            $integralInfo->setRefundOperationId(
                sprintf('%s-refund-product-%s', $contract->getSn(), $product->getId())
            );
            $this->entityManager->persist($integralInfo);
        }

        $this->entityManager->flush();
    }

    /**
     * 更新积分记录的退款状态
     *
     * @deprecated 使用 updateIntegralInfoRefundStatusByProduct 代替
     */
    public function updateIntegralInfoRefundStatus(
        Contract $contract,
        OrderPrice $price,
    ): void {
        $integralInfos = $this->integralInfoRepository->findBy([
            'orderId' => $contract->getId(),
            'isRefunded' => false,
        ]);

        foreach ($integralInfos as $integralInfo) {
            // 简单匹配：如果金额相等，认为是同一笔
            if ($integralInfo->getIntegralRequired() === (int) $price->getMoney()) {
                $integralInfo->setIsRefunded(true);
                $integralInfo->setRefundedTime(new \DateTimeImmutable());
                $integralInfo->setRefundOperationId($this->generateOperationId($contract, $price));
                $this->entityManager->persist($integralInfo);
                break;
            }
        }

        $this->entityManager->flush();
    }

    /**
     * 查询所有未退款的积分记录
     *
     * @return array<OrderIntegralInfo>
     */
    public function findUnrefundedIntegralInfos(int $orderId): array
    {
        return $this->integralInfoRepository->findBy([
            'orderId' => $orderId,
            'isRefunded' => false,
        ]);
    }

    /**
     * 获取积分余额
     */
    public function getIntegralBalance(string $userIdentifier): int
    {
        if (null === $this->integralService) {
            return 0;
        }

        try {
            $account = $this->integralService->getIntegralAccount($userIdentifier);

            return null !== $account ? $account->availableIntegral : 0;
        } catch (IntegralServiceException $e) {
            $this->logger->error('查询积分余额失败', [
                'userIdentifier' => $userIdentifier,
                'exception' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * 生成操作 ID（基于商品）
     */
    private function generateOperationIdForProduct(Contract $contract, OrderProduct $product): string
    {
        return sprintf('%s-product-%s', $contract->getSn(), $product->getId());
    }

    /**
     * 生成操作 ID
     *
     * @deprecated 使用 generateOperationIdForProduct 代替
     */
    private function generateOperationId(Contract $contract, OrderPrice $price): string
    {
        return sprintf('%s-%s', $contract->getSn(), $price->getId());
    }
}
