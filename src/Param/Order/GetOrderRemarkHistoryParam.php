<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Param\Order;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

readonly class GetOrderRemarkHistoryParam implements RpcParamInterface
{
    public function __construct(
        #[MethodParam(description: '订单ID')]
        #[Assert\NotBlank(message: '订单ID不能为空')]
        #[Assert\Positive(message: '订单ID必须为正整数')]
        public int $orderId,
    ) {
    }
}
