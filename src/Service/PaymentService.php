<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Enum\OrderState;
use OrderCoreBundle\Repository\ContractRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tourze\OrderCheckoutBundle\Exception\PaymentException;
use Tourze\PaymentContracts\Enum\PaymentType;
use Tourze\PaymentContracts\Event\PaymentParametersRequestedEvent;
use Tourze\PaymentContracts\ValueObject\AttachData;
use Tourze\Symfony\AopDoctrineBundle\Attribute\Transactional;

/**
 * 支付服务
 * 处理订单支付相关逻辑
 */
class PaymentService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContractRepository $contractRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * 根据ID获取订单
     */
    public function getOrderById(int $orderId): ?Contract
    {
        return $this->contractRepository->find($orderId);
    }

    /**
     * 设置订单支付方式
     */
    #[Transactional]
    public function setPaymentMethod(Contract $contract, string $paymentMethod): void
    {
        if (!in_array($contract->getState(), [OrderState::INIT, OrderState::PAYING], true)) {
            throw new PaymentException('订单状态不允许设置支付方式');
        }

        // 这里可以将支付方式保存到订单的元数据或专门的支付表中
        // 暂时使用订单备注字段存储，实际项目中应该有专门的支付方式字段
        $currentRemark = $contract->getRemark() ?? '';
        $paymentRemark = "支付方式: {$paymentMethod}";

        if (str_contains($currentRemark, '支付方式:')) {
            // 替换现有的支付方式
            $newRemark = preg_replace('/支付方式: \w+/', $paymentRemark, $currentRemark);
        } else {
            // 添加支付方式
            $newRemark = trim($currentRemark . "\n" . $paymentRemark);
        }

        $contract->setRemark($newRemark);
        $this->entityManager->flush();
    }

    /**
     * 获取订单支付方式
     */
    public function getOrderPaymentMethod(Contract $contract): ?string
    {
        $remark = $contract->getRemark() ?? '';
        if (1 === preg_match('/支付方式: (\w+)/', $remark, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * 获取支付参数
     * @return array<string, mixed>
     */
    public function getPaymentParams(Contract $contract, string $paymentMethod): array
    {
        $totalAmount = $this->calculateOrderTotal($contract);

        return match ($paymentMethod) {
            'alipay' => [
                'app_id' => $_ENV['ALIPAY_APP_ID'] ?? 'mock_app_id',
                'method' => 'alipay.trade.app.pay',
                'total_amount' => (string) $totalAmount,
                'subject' => '订单支付-' . $contract->getSn(),
                'return_url' => $_ENV['PAYMENT_RETURN_URL'] ?? 'https://example.com/payment/return',
                'notify_url' => $_ENV['PAYMENT_NOTIFY_URL'] ?? 'https://example.com/payment/notify',
            ],
            'wechat_pay' => [
                'appid' => $_ENV['WECHAT_APP_ID'] ?? 'mock_app_id',
                'mch_id' => $_ENV['WECHAT_MCH_ID'] ?? 'mock_mch_id',
                'total_fee' => (int) ($totalAmount * 100), // 微信支付单位是分
                'body' => '订单支付-' . $contract->getSn(),
                'notify_url' => $_ENV['PAYMENT_NOTIFY_URL'] ?? 'https://example.com/payment/notify',
            ],
            default => [
                'method' => $paymentMethod,
                'amount' => $totalAmount,
                'order_sn' => $contract->getSn(),
            ],
        };
    }

    /**
     * 计算订单总金额
     */
    public function calculateOrderTotal(Contract $contract): float
    {
        $total = 0.0;
        foreach ($contract->getPrices() as $price) {
            if (false === $price->isRefund()) {
                $total += (float) $price->getMoney();
            }
        }

        return $total;
    }

    /**
     * 执行支付（调用支付网关）
     * @return array<string, mixed>
     */
    #[Transactional]
    public function processPayment(Contract $contract): array
    {
        if (!in_array($contract->getState(), [OrderState::INIT, OrderState::PAYING], true)) {
            throw new PaymentException('订单状态不允许支付');
        }

        $paymentMethod = $this->getOrderPaymentMethod($contract);
        if (null === $paymentMethod || '' === $paymentMethod) {
            throw new PaymentException('未设置支付方式');
        }

        $totalAmount = $this->calculateOrderTotal($contract);
        if ($totalAmount <= 0) {
            throw new PaymentException('订单金额异常');
        }

        try {
            // 更新订单状态为支付中
            $contract->setState(OrderState::PAYING);
            $this->entityManager->flush();

            // 调用支付网关（这里是模拟）
            return $this->callPaymentGateway($contract, $paymentMethod, $totalAmount);
        } catch (\Exception $e) {
            // 支付失败，恢复订单状态
            $contract->setState(OrderState::INIT);
            $this->entityManager->flush();

            throw new PaymentException('支付网关调用失败: ' . $e->getMessage());
        }
    }

    /**
     * 发起支付（选择支付方式并调用支付网关）
     * @return array<string, mixed>
     */
    #[Transactional]
    public function initiatePayment(int $orderId, string $paymentMethod): array
    {
        $contract = $this->getOrderById($orderId);
        if (null === $contract) {
            throw new PaymentException('订单不存在');
        }

        if (!in_array($contract->getState(), [OrderState::INIT, OrderState::PAYING], true)) {
            throw new PaymentException('订单状态不允许发起支付');
        }

        $totalAmount = $this->calculateOrderTotal($contract);
        if ($totalAmount <= 0) {
            throw new PaymentException('订单金额异常');
        }

        try {
            // 不在备注中设置支付方式（已通过其他方式处理）

            // 更新订单状态为支付中
            $contract->setState(OrderState::PAYING);
            $this->entityManager->flush();

            // 调用支付网关
            $paymentParams = $this->callPaymentGateway($contract, $paymentMethod, $totalAmount);

            return [
                'orderNumber' => $contract->getSn(),
                'amount' => $totalAmount,
                'params' => $paymentParams,
            ];
        } catch (\Exception $e) {
            // 支付失败，恢复订单状态
            $contract->setState(OrderState::INIT);
            $this->entityManager->flush();

            throw new PaymentException('支付发起失败: ' . $e->getMessage());
        }
    }

    /**
     * 调用支付网关
     * @return array<string, mixed>
     */
    private function callPaymentGateway(Contract $contract, string $paymentMethod, float $amount): array
    {
        $paymentType = PaymentType::fromValue($paymentMethod);

        $event = new PaymentParametersRequestedEvent(
            paymentType: $paymentMethod,
            amount: $amount,
            orderNumber: $contract->getSn(),
            orderId: $contract->getId(),
            orderState: $contract->getState()->value,
            requestTime: date('Y-m-d H:i:s'),
            appId: is_string($_ENV['WECHAT_APP_ID'] ?? null) ? $_ENV['WECHAT_APP_ID'] : null,
            mchId: is_string($_ENV['WECHAT_MCH_ID'] ?? null) ? $_ENV['WECHAT_MCH_ID'] : null,
            description: '订单支付-' . $contract->getSn(),
            attach: $this->createAttachData($contract)->encode(),
            openId: $contract->getUser()?->getUserIdentifier(),
            paymentTypeEnum: $paymentType,
        );

        $this->eventDispatcher->dispatch($event);

        if ($event->hasPaymentParams()) {
            return $event->getPaymentParams() ?? [];
        }

        $paymentId = 'PAY' . date('YmdHis') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        return match ($paymentType?->getChannel()) {
            'alipay' => [
                'paymentId' => $paymentId,
                'paymentUrl' => 'https://openapi.alipay.com/gateway.do?payment_id=' . $paymentId,
                'qrCode' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
                'expireTime' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
                'paymentType' => $paymentMethod,
                'channel' => 'alipay',
            ],
            'wechat' => [
                'paymentId' => $paymentId,
                'prepay_id' => 'wx' . $paymentId,
                'qrCode' => 'weixin://wxpay/bizpayurl?pr=' . $paymentId,
                'expireTime' => date('Y-m-d H:i:s', strtotime('+15 minutes')),
                'paymentType' => $paymentMethod,
                'channel' => 'wechat',
            ],
            'bank' => [
                'paymentId' => $paymentId,
                'bankUrl' => 'https://bank.example.com/pay?id=' . $paymentId,
                'expireTime' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
                'paymentType' => $paymentMethod,
                'channel' => 'bank',
            ],
            'balance' => [
                'paymentId' => $paymentId,
                'status' => 'success',
                'amount' => $amount,
                'processedTime' => date('Y-m-d H:i:s'),
                'paymentType' => $paymentMethod,
                'channel' => 'balance',
            ],
            default => [
                'paymentId' => $paymentId,
                'status' => 'pending',
                'amount' => $amount,
                'expireTime' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
                'paymentType' => $paymentMethod,
                'channel' => 'unknown',
            ],
        };
    }

    /**
     * 创建支付附加数据
     */
    private function createAttachData(Contract $contract): AttachData
    {
        $orderId = $contract->getId();
        if (null === $orderId) {
            throw new PaymentException('Contract ID cannot be null when creating attach data');
        }

        return new AttachData(
            orderId: $orderId,
            orderSn: $contract->getSn(),
            type: 'order'
        );
    }
}
