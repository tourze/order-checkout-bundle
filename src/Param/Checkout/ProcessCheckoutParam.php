<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Param\Checkout;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

readonly class ProcessCheckoutParam implements RpcParamInterface
{
    /**
     * @param array<int, array{id?: int, skuId: string|int, quantity: int, price?: float}> $skuItems
     */
    public function __construct(
        #[MethodParam(description: 'SKU商品数组（当fromCart为false时使用）')]
        public array $skuItems = [],

        #[MethodParam(description: '是否从购物车获取商品（为true时忽略skuItems）')]
        public bool $fromCart = false,

        #[MethodParam(description: '收货地址ID')]
        public int $addressId = 0,

        #[MethodParam(description: '优惠券代码')]
        #[Assert\Length(max: 50)]
        public ?string $couponCode = null,

        #[MethodParam(description: '用户积分抵扣数量')]
        #[Assert\PositiveOrZero]
        public int $pointsToUse = 0,

        #[MethodParam(description: '订单备注')]
        #[Assert\Length(max: 500)]
        public ?string $orderRemark = null,

        #[MethodParam(description: '触达分销员ID')]
        #[Assert\Positive(message: '触达分销员ID必须大于0')]
        public ?int $referralDistributorId = null,

        #[MethodParam(description: '触达来源（示例：scan_qrcode）')]
        #[Assert\Length(max: 32)]
        public ?string $referralSource = null,

        #[MethodParam(description: '触达追踪码')]
        #[Assert\Length(max: 64)]
        public ?string $referralTrackCode = null,

        #[MethodParam(description: '支付模式：CASH_ONLY(仅现金)、INTEGRAL_ONLY(仅积分)、MIXED(混合支付)')]
        #[Assert\Choice(choices: ['CASH_ONLY', 'INTEGRAL_ONLY', 'MIXED'])]
        public string $paymentMode = 'CASH_ONLY',

        #[MethodParam(description: '混合支付模式下使用的积分数量')]
        #[Assert\PositiveOrZero]
        public int $useIntegralAmount = 0,
    ) {
    }
}
