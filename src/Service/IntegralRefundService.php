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
use Tourze\IntegralServiceContracts\DTO\Request\IncreaseIntegralRequest;
use Tourze\IntegralServiceContracts\Exception\IntegralServiceException;
use Tourze\IntegralServiceContracts\IntegralServiceInterface;
use Tourze\OrderCheckoutBundle\Entity\OrderIntegralInfo;

/**
 * 积分退还服务
 *
 * 职责：
 * - 订单创建失败时退还积分
 * - 订单取消时退还积分
 * - 价格退款时退还积分
 */
#[WithMonologChannel(channel: 'order_checkout')]
readonly class IntegralRefundService
{
    private const SOURCE_TYPE_REFUND = 'order_refund';

    public function __construct(
        private LoggerInterface $logger,
        private ?IntegralServiceInterface $integralService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * 退还积分（通用方法）
     *
     * @throws IntegralServiceException
     */
    public function refundIntegral(
        Contract $contract,
        string $userIdentifier,
        int $amount,
        string $reason,
    ): void {
        if (null === $this->integralService) {
            $this->logger->warning('IntegralRefundService: integralService is null');
            return;
        }

        try {
            $this->integralService->increaseIntegral(
                new IncreaseIntegralRequest(
                    userIdentifier: $userIdentifier,
                    changeValue: $amount,
                    changeReason: $reason,
                    sourceId: $this->generateRefundSourceId($contract),
                    sourceType: self::SOURCE_TYPE_REFUND,
                    remark: sprintf('订单 %s 退还积分', $contract->getSn()),
                )
            );

            $this->logger->info('退还积分成功', [
                'orderId' => $contract->getId(),
                'orderSn' => $contract->getSn(),
                'userIdentifier' => $userIdentifier,
                'amount' => $amount,
            ]);
        } catch (IntegralServiceException $e) {
            $this->logger->error('退还积分失败', [
                'orderId' => $contract->getId(),
                'userIdentifier' => $userIdentifier,
                'amount' => $amount,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 退还单个商品的积分（订单创建失败）
     */
    public function refundProductOnFailure(
        Contract $contract,
        UserInterface $user,
        OrderProduct $product,
    ): void {
        $integralPrice = $product->getIntegralPrice();
        if (null === $integralPrice || $integralPrice <= 0) {
            $this->logger->warning('IntegralRefundService: integralService is null');
            return;
        }

        $this->refundIntegral(
            $contract,
            $user->getUserIdentifier(),
            $integralPrice,
            sprintf('订单创建失败，退还商品积分：%s - 商品ID: %s', $contract->getSn(), $product->getId())
        );
    }

    /**
     * 退还单个商品的积分（订单取消）
     */
    public function refundProductOnCancel(
        Contract $contract,
        UserInterface $user,
        OrderProduct $product,
    ): void {
        $integralPrice = $product->getIntegralPrice();
        if (null === $integralPrice || $integralPrice <= 0) {
            return;
        }

        $this->refundIntegral(
            $contract,
            $user->getUserIdentifier(),
            $integralPrice,
            sprintf('订单取消，退还商品积分：%s - 商品ID: %s', $contract->getSn(), $product->getId())
        );
    }

    /**
     * 退还单个价格的积分（订单创建失败）
     *
     * @deprecated 使用 refundProductOnFailure 代替
     */
    public function refundPriceOnFailure(
        Contract $contract,
        UserInterface $user,
        OrderPrice $price,
    ): void {
        if (!$price->isPaid()) {
            return;
        }

        $totalIntegral = $contract->getTotalIntegral();
        if (null === $totalIntegral || $totalIntegral <= 0) {
            return;
        }

        $this->refundIntegral(
            $contract,
            $user->getUserIdentifier(),
            $totalIntegral,
            sprintf('订单创建失败，退还积分：%s', $contract->getSn())
        );

        $price->setPaid(false);
    }

    /**
     * 处理价格退款
     */
    public function processPriceRefund(
        Contract $contract,
        OrderPrice $price,
        string $userIdentifier,
        int $integralAmount,
    ): void {
        try {
            $this->refundIntegral(
                $contract,
                $userIdentifier,
                $integralAmount,
                sprintf('订单退款：%s', $contract->getSn())
            );

            // 标记为已退款
            $price->setRefund(true);
            $this->entityManager->persist($price);
            $this->entityManager->flush();
        } catch (IntegralServiceException $e) {
            $this->logger->error('价格退款退还积分失败', [
                'priceId' => $price->getId(),
                'orderId' => $contract->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 处理单个积分记录的退款
     */
    public function processIntegralInfoRefund(
        Contract $contract,
        OrderIntegralInfo $integralInfo,
    ): void {
        if (null === $this->integralService) {
            return;
        }

        try {
            $refundResponse = $this->integralService->increaseIntegral(
                new IncreaseIntegralRequest(
                    userIdentifier: (string) $integralInfo->getUserId(),
                    changeValue: $integralInfo->getIntegralRequired(),
                    changeReason: sprintf('订单取消退还积分：%s', $contract->getSn()),
                    sourceId: $this->generateRefundSourceIdForInfo($contract, $integralInfo),
                    sourceType: self::SOURCE_TYPE_REFUND,
                    remark: sprintf('订单 %s 取消，退还积分', $contract->getSn()),
                )
            );

            // 更新退款记录
            $integralInfo->setIsRefunded(true);
            $integralInfo->setRefundedTime(new \DateTimeImmutable());
            $integralInfo->setRefundOperationId($refundResponse->history->id);

            $this->entityManager->persist($integralInfo);

            $this->logger->info('订单取消退还积分成功', [
                'orderId' => $contract->getId(),
                'integralAmount' => $integralInfo->getIntegralRequired(),
                'operationId' => $refundResponse->history->id,
            ]);
        } catch (IntegralServiceException $e) {
            $this->logger->error('订单取消退还积分失败', [
                'orderId' => $contract->getId(),
                'integralInfo' => $integralInfo->getId(),
                'exception' => $e->getMessage(),
            ]);
            // 继续处理其他记录
        }
    }

    /**
     * 生成退款操作的 sourceId（从 Contract）
     */
    private function generateRefundSourceId(Contract $contract): string
    {
        return sprintf('%s-refund', $contract->getSn());
    }

    /**
     * 生成退款操作的 sourceId（用于幂等性）
     */
    private function generateRefundSourceIdForInfo(
        Contract $contract,
        OrderIntegralInfo $integralInfo,
    ): string {
        return sprintf('%s-refund-%s', $contract->getSn(), $integralInfo->getId());
    }
}
