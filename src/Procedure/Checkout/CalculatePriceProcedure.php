<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Checkout;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationException;
use Tourze\OrderCheckoutBundle\Service\PriceCalculationService;

#[MethodTag(name: '订单结算')]
#[MethodDoc(description: '计算购物车商品价格（预结算）')]
#[MethodExpose(method: 'CalculateCheckoutPrice')]
#[IsGranted(attribute: 'ROLE_USER')]
class CalculatePriceProcedure extends BaseProcedure
{
    /**
     * @var array<int, array{id: int, skuId: int, quantity: int, price?: float}>
     */
    #[MethodParam(description: '购物车商品数组')]
    public array $cartItems = [];

    #[MethodParam(description: '收货地址ID（用于计算运费）')]
    public ?int $addressId = null;

    #[MethodParam(description: '优惠券代码')]
    #[Assert\Length(max: 50)]
    public ?string $couponCode = null;

    #[MethodParam(description: '用户积分抵扣数量')]
    #[Assert\PositiveOrZero]
    public int $pointsToUse = 0;

    public function __construct(
        private readonly Security $security,
        private readonly PriceCalculationService $priceCalculationService,
    ) {
    }

    public function execute(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        if ([] === $this->cartItems) {
            throw new ApiException('购物车中没有选中的商品');
        }

        // 转换数组数据为 CheckoutItem 对象
        $checkoutItems = [];
        foreach ($this->cartItems as $item) {
            $checkoutItems[] = CheckoutItem::fromArray($item);
        }

        // 构建计算上下文
        $appliedCoupons = null !== $this->couponCode ? [$this->couponCode] : [];
        $context = new CalculationContext(
            $user,
            $checkoutItems,
            $appliedCoupons,
            [
                'addressId' => $this->addressId,
                'pointsToUse' => $this->pointsToUse,
            ]
        );

        try {
            $priceResult = $this->priceCalculationService->calculate($context);

            return [
                'pricing' => [
                    'originalPrice' => $priceResult->getOriginalPrice(),
                    'finalPrice' => $priceResult->getFinalPrice(),
                    'totalDiscount' => $priceResult->getDiscount(),
                    'promotionDiscount' => $priceResult->getDetail('promotion_discount', 0.0),
                    'couponDiscount' => $priceResult->getDetail('coupon_discount', 0.0),
                    'pointsDiscount' => $priceResult->getDetail('points_discount', 0.0),
                    'shippingFee' => $priceResult->getDetail('shipping_fee', 0.0),
                    'savings' => $priceResult->getDiscount(),
                ],
                'products' => $priceResult->getProducts(),
                'breakdown' => $priceResult->getDetails(),
                'appliedPromotions' => $priceResult->getDetail('applied_promotions', []),
                'items' => $this->cartItems,
            ];
        } catch (PriceCalculationException $e) {
            throw new ApiException($e->getMessage());
        }
    }

    public function getCacheKey(JsonRpcRequest $request): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        // 基于用户、购物车状态、地址、优惠券等生成缓存Key
        return sprintf(
            'price_calc:%s:%s:%s:%d',
            $user->getUserIdentifier(),
            $this->addressId ?? 'no_addr',
            $this->couponCode ?? 'no_coupon',
            $this->pointsToUse
        );
    }

    public function getCacheDuration(JsonRpcRequest $request): int
    {
        return 120; // 2分钟，价格计算结果变化相对频繁
    }

    /**
     * @return array<string>
     */
    public function getCacheTags(JsonRpcRequest $request): iterable
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }
        $tags = [
            'checkout',
            'price_calculation',
            'cart_user_' . $user->getUserIdentifier(),
        ];

        if (null !== $this->couponCode) {
            $tags[] = 'coupon_' . $this->couponCode;
        }

        return $tags;
    }

    public static function getMockResult(): ?array
    {
        return [
            'pricing' => [
                'basePrice' => 299.98,
                'promotionDiscount' => 50.00,
                'couponDiscount' => 20.00,
                'pointsDiscount' => 10.00,
                'shippingFee' => 15.00,
                'finalPrice' => 234.98,
                'savings' => 80.00,
            ],
            'breakdown' => [
                'base_price' => ['amount' => 299.98, 'description' => '商品原价'],
                'promotion' => ['amount' => -50.00, 'description' => '满减优惠'],
                'coupon' => ['amount' => -20.00, 'description' => '优惠券抵扣'],
                'points' => ['amount' => -10.00, 'description' => '积分抵扣'],
                'shipping' => ['amount' => 15.00, 'description' => '运费'],
            ],
            'appliedPromotions' => [
                ['type' => 'full_reduction', 'description' => '满200减50'],
            ],
            'items' => [
                [
                    'id' => 1,
                    'skuId' => 100,
                    'skuName' => '商品名称',
                    'price' => 99.99,
                    'quantity' => 3,
                    'subtotal' => 299.97,
                    'isValid' => true,
                ],
            ],
        ];
    }
}
