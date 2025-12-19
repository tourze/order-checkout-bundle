<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use OrderCoreBundle\Entity\Contract;
use Psr\Log\LoggerInterface;
use Tourze\IntegralServiceContracts\DTO\Request\DecreaseIntegralRequest;
use Tourze\IntegralServiceContracts\Exception\InsufficientBalanceException;
use Tourze\IntegralServiceContracts\Exception\IntegralServiceException;
use Tourze\IntegralServiceContracts\IntegralServiceInterface;

/**
 * 积分扣除服务
 *
 * 职责：
 * - 验证积分余额
 * - 执行积分扣除
 * - 记录扣除日志
 */
#[WithMonologChannel(channel: 'order_checkout')]
readonly class IntegralDeductionService
{
    private const SOURCE_TYPE_ORDER = 'order';

    public function __construct(
        private LoggerInterface $logger,
        private ?IntegralServiceInterface $integralService,
    ) {
    }

    /**
     * 扣除积分
     *
     * @throws \RuntimeException 当积分不足或服务异常时
     */
    public function deductIntegral(
        Contract $contract,
        string $userIdentifier,
        int $amount,
    ): void {
        if (null === $this->integralService) {
            $this->logger->warning('IntegralRefundService: integralService is null');
            return;
        }

        try {
            $this->validateIntegralBalance($userIdentifier, $amount);
            $this->executeDeduction($contract, $userIdentifier, $amount);
        } catch (InsufficientBalanceException $e) {
            $this->logInsufficientBalance($contract, $userIdentifier, $e);
            throw new \RuntimeException($e->getMessage(), 0, $e);
        } catch (IntegralServiceException $e) {
            $this->logDeductionError($contract, $userIdentifier, $amount, $e);
            throw new \RuntimeException('积分服务异常，请稍后重试', 0, $e);
        }
    }

    /**
     * 验证积分余额是否充足
     *
     * @throws \RuntimeException
     */
    private function validateIntegralBalance(string $userIdentifier, int $amount): void
    {
        if (null === $this->integralService) {
            $this->logger->warning('IntegralRefundService: integralService is null');
            return;
        }

        $account = $this->integralService->getIntegralAccount($userIdentifier);

        if (null === $account) {
            throw new \RuntimeException(sprintf('用户 %s 的积分账户不存在', $userIdentifier));
        }

        if ($account->availableIntegral < $amount) {
            throw new \RuntimeException(sprintf('积分不足：需要 %d，当前可用 %d', $amount, $account->availableIntegral));
        }
    }

    /**
     * 执行积分扣除操作
     */
    private function executeDeduction(
        Contract $contract,
        string $userIdentifier,
        int $amount,
    ): void {
        if (null === $this->integralService) {
            $this->logger->warning('IntegralRefundService: integralService is null');
            return;
        }

        $this->integralService->decreaseIntegral(
            new DecreaseIntegralRequest(
                userIdentifier: $userIdentifier,
                changeValue: $amount,
                changeReason: sprintf('订单支付：%s', $contract->getSn()),
                sourceId: $this->generateDeductSourceId($contract),
                sourceType: self::SOURCE_TYPE_ORDER,
                remark: sprintf('订单 %s 扣除积分', $contract->getSn()),
            )
        );

        $this->logger->info('扣除积分成功', [
            'orderId' => $contract->getId(),
            'orderSn' => $contract->getSn(),
            'userIdentifier' => $userIdentifier,
            'amount' => $amount,
        ]);
    }

    /**
     * 记录积分不足日志
     */
    private function logInsufficientBalance(
        Contract $contract,
        string $userIdentifier,
        InsufficientBalanceException $e,
    ): void {
        $this->logger->warning('积分不足', [
            'orderId' => $contract->getId(),
            'userIdentifier' => $userIdentifier,
            'required' => $e->required,
            'available' => $e->available,
        ]);
    }

    /**
     * 记录扣除失败日志
     */
    private function logDeductionError(
        Contract $contract,
        string $userIdentifier,
        int $amount,
        IntegralServiceException $e,
    ): void {
        $this->logger->error('扣除积分失败', [
            'orderId' => $contract->getId(),
            'userIdentifier' => $userIdentifier,
            'amount' => $amount,
            'exception' => $e->getMessage(),
        ]);
    }

    /**
     * 生成扣款操作的 sourceId（用于幂等性）
     */
    private function generateDeductSourceId(Contract $contract): string
    {
        return sprintf('%s-deduct', $contract->getSn());
    }
}
