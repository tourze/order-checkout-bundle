<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Payment;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\OrderCheckoutBundle\Service\PaymentService;
use Tourze\PaymentContracts\Enum\PaymentType;

#[MethodTag(name: '支付管理')]
#[MethodDoc(description: '发起订单支付（选择支付方式并调用支付网关）')]
#[MethodExpose(method: 'InitiatePayment')]
#[IsGranted(attribute: 'ROLE_USER')]
class InitiatePaymentProcedure extends LockableProcedure
{
    #[MethodParam(description: '订单ID')]
    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $orderId = 0;

    #[MethodParam(description: '支付方式代码')]
    public string $paymentMethod = '';

    public function __construct(
        private readonly PaymentService $paymentService,
    ) {
    }

    public function execute(): array
    {
        $paymentMethod = PaymentType::tryFrom($this->paymentMethod);
        if (null === $paymentMethod) {
            throw new ApiException('无效的支付方式');
        }
        try {
            $paymentResult = $this->paymentService->initiatePayment($this->orderId, $paymentMethod->value);

            return [
                '__message' => '支付发起成功',
                'orderId' => $this->orderId,
                'orderNumber' => $paymentResult['orderNumber'],
                'paymentMethod' => $paymentMethod->value,
                'paymentMethodLabel' => $paymentMethod->getLabel(),
                'orderState' => 'paying',
                'totalAmount' => $paymentResult['amount'],
                'paymentParams' => $paymentResult['params'],
            ];
        } catch (\Exception $e) {
            throw new ApiException(sprintf('支付发起失败: %s', $e->getMessage()));
        }
    }

    public function getLockResource(JsonRpcParams $params): ?array
    {
        // 订单级别锁定，防止重复支付
        return [sprintf('payment_initiate:%d', $this->orderId)];
    }

    public static function getMockResult(): ?array
    {
        return [
            '__message' => '支付发起成功',
            'orderId' => 123456,
            'orderNumber' => 'ORD202401010001',
            'paymentMethod' => PaymentType::LEGACY_ALIPAY->value,
            'paymentMethodLabel' => PaymentType::LEGACY_ALIPAY->getLabel(),
            'orderState' => 'paying',
            'totalAmount' => 234.98,
            'paymentParams' => [
                'paymentId' => 'PAY2024010112345',
                'paymentUrl' => 'https://openapi.alipay.com/gateway.do?...',
                'qrCode' => 'data:image/png;base64,iVBOR...',
                'expireTime' => '2024-01-01 13:00:00',
            ],
        ];
    }
}
