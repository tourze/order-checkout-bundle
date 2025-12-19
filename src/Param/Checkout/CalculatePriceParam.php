<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Param\Checkout;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

readonly class CalculatePriceParam implements RpcParamInterface
{
    /**
     * @param array<int, array{id: int, skuId: int, quantity: int, price?: float}> $cartItems
     */
    public function __construct(
        #[MethodParam(description: '购物车商品数组')]
        public array $cartItems = [],

        #[MethodParam(description: '收货地址ID（用于计算运费）')]
        public ?int $addressId = null,

        #[MethodParam(description: '优惠券代码')]
        #[Assert\Length(max: 50)]
        public ?string $couponCode = null,

        #[MethodParam(description: '用户积分抵扣数量')]
        #[Assert\PositiveOrZero]
        public int $pointsToUse = 0,

        #[MethodParam(description: '是否使用优惠券，如果没穿自动应用可用优惠券')]
        public bool $useCoupon = false,

        #[MethodParam(description: '是否拉取有效优惠券返回')]
        public bool $getAvailableCoupons = false,

        #[MethodParam(description: '支付模式：CASH_ONLY(仅现金)、INTEGRAL_ONLY(仅积分)、MIXED(混合支付)')]
        #[Assert\Choice(choices: ['CASH_ONLY', 'INTEGRAL_ONLY', 'MIXED'])]
        public string $paymentMode = 'CASH_ONLY',

        #[MethodParam(description: '混合支付模式下使用的积分数量')]
        #[Assert\PositiveOrZero]
        public int $useIntegralAmount = 0,
    ) {
    }
}
