<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Payment;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPCLockBundle\Procedure\LockableProcedure;
use Tourze\OrderCheckoutBundle\Param\Payment\InitiatePaymentParam;
use Tourze\OrderCheckoutBundle\Service\PaymentService;
use Tourze\PaymentContracts\Enum\PaymentType;

#[MethodTag(name: '支付管理')]
#[MethodDoc(description: '发起订单支付（选择支付方式并调用支付网关）')]
#[MethodExpose(method: 'InitiatePayment')]
#[IsGranted(attribute: 'ROLE_USER')]
final class InitiatePaymentProcedure extends LockableProcedure
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {
    }

    /**
     * @phpstan-param InitiatePaymentParam $param
     */
    public function execute(InitiatePaymentParam|RpcParamInterface $param): ArrayResult
    {
        $paymentMethod = PaymentType::tryFrom($param->paymentMethod);
        if (null === $paymentMethod) {
            throw new ApiException('无效的支付方式');
        }
        try {
            $paymentResult = $this->paymentService->initiatePayment($param->orderId, $paymentMethod->value);

            return new ArrayResult([
                '__message' => '支付发起成功',
                'orderId' => $param->orderId,
                'orderNumber' => $paymentResult['orderNumber'],
                'paymentMethod' => $paymentMethod->value,
                'paymentMethodLabel' => $paymentMethod->getLabel(),
                'orderState' => 'paying',
                'totalAmount' => $paymentResult['amount'],
                'paymentParams' => $paymentResult['params'],
            ]);
        } catch (\Exception $e) {
            throw new ApiException(sprintf('支付发起失败: %s', $e->getMessage()));
        }
    }

    public function getLockResource(JsonRpcParams $params): ?array
    {
        $orderId = $params->get('orderId');
        // 订单级别锁定，防止重复支付
        return [sprintf('payment_initiate:%d', $orderId)];
    }
}
