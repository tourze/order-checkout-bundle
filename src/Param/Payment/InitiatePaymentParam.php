<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Param\Payment;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

readonly class InitiatePaymentParam implements RpcParamInterface
{
    public function __construct(
        #[MethodParam(description: '订单ID')]
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $orderId,

        #[MethodParam(description: '支付方式代码')]
        public string $paymentMethod = '',
    ) {
    }
}
