<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Param\Order;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

readonly class SaveOrderRemarkParam implements RpcParamInterface
{
    public function __construct(
        #[MethodParam(description: '订单ID')]
        #[Assert\NotBlank(message: '订单ID不能为空')]
        #[Assert\Positive(message: '订单ID必须为正整数')]
        public int $orderId,

        #[MethodParam(description: '备注内容，最多200个字符，支持emoji表情')]
        #[Assert\NotBlank(message: '备注内容不能为空')]
        #[Assert\Length(
            max: 200,
            maxMessage: '备注内容不能超过200个字符'
        )]
        public string $remark,
    ) {
    }
}
