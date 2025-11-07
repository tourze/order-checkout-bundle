<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Checkout;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CouponCoreBundle\Service\CouponEvaluator;
use Tourze\CouponCoreBundle\ValueObject\CouponEvaluationContext;
use Tourze\CouponCoreBundle\ValueObject\CouponOrderItem;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;
use Tourze\CouponCoreBundle\ValueObject\FullGiftTier;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\RecommendedCoupon;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationException;
use Tourze\OrderCheckoutBundle\Provider\CouponProviderChain;
use Tourze\OrderCheckoutBundle\Service\CouponRecommendationService;
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

    #[MethodParam(description: '是否计算优惠券')]
    public bool $useCoupon = false;

    public function __construct(
        private readonly Security $security,
        private readonly PriceCalculationService $priceCalculationService,
        private readonly CouponRecommendationService $couponRecommendationService,
        private readonly CouponEvaluator $couponEvaluator,
        private readonly CouponProviderChain $couponProviderChain,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        // 允许空购物车用于纯兑换场景
        if ([] === $this->cartItems && null === $this->couponCode) {
            throw new ApiException('购物车中没有选中的商品，且未提供优惠券');
        }

        // 转换数组数据为 CheckoutItem 对象
        $checkoutItems = [];
        foreach ($this->cartItems as $item) {
            $checkoutItems[] = CheckoutItem::fromArray($item);
        }

        // 【新增】先获取所有可用优惠券（不包含优惠券的临时上下文）
        $tempContext = new CalculationContext(
            $user,
            $checkoutItems,
            [],
            [
                'addressId' => $this->addressId,
                'pointsToUse' => $this->pointsToUse,
                'orderType' => 'normal', // 临时上下文使用正常模式
            ]
        );
        $allAvailableCoupons = [];
        if ($this->useCoupon === true){
            $allAvailableCoupons = $this->getAllAvailableCoupons($tempContext);
        }

        // 【新增】自动应用优惠券逻辑
        $originalCouponCode = $this->couponCode;
        $autoAppliedCoupon = null;

        if (null === $this->couponCode && [] !== $allAvailableCoupons) {
            $autoAppliedCoupon = $allAvailableCoupons[0]->getCode();
            $this->couponCode = $autoAppliedCoupon;
            $this->logger->info('自动应用优惠券', [
                'couponCode' => $autoAppliedCoupon,
                'user' => $user->getUserIdentifier(),
                'availableCount' => count($allAvailableCoupons),
            ]);
        }

        // 构建最终计算上下文
        $appliedCoupons = null !== $this->couponCode ? [$this->couponCode] : [];
        
        // 检测是否为纯兑换券场景
        $isRedeemOnlyOrder = [] === $this->cartItems && null !== $this->couponCode;
        
        $context = new CalculationContext(
            $user,
            $checkoutItems,
            $appliedCoupons,
            [
                'addressId' => $this->addressId,
                'pointsToUse' => $this->pointsToUse,
                'orderType' => $isRedeemOnlyOrder ? 'redeem' : 'normal', // 添加订单类型标识
            ]
        );

        try {
            $priceResult = $this->priceCalculationService->calculate($context);

            $result = [
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

            // 【新增】优惠券信息
            $result['couponInfo'] = [
                'appliedCouponCode' => $this->couponCode,
                'originalCouponCode' => $originalCouponCode,
                'autoAppliedCoupon' => $autoAppliedCoupon,
                'hasAutoApplied' => null !== $autoAppliedCoupon,
                'userSelectedCoupon' => null !== $originalCouponCode,
                'appliedCoupon' => $this->getAppliedCouponDetails($this->couponCode, $allAvailableCoupons, $context),
            ];

            // 【新增】所有可用优惠券（标识状态 + 赠品信息）
            $result['availableCoupons'] = $this->enrichCouponsWithGiftInfo($allAvailableCoupons, $context, $autoAppliedCoupon);

            return $result;
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

        // 【修正】包含购物车商品内容的哈希，确保商品变更时缓存失效
        $cartHash = $this->getCartItemsHash();
        $couponKey = $this->couponCode ?? 'auto_apply';

        return sprintf(
            'price_calc:%s:%s:%s:%s:%d',
            $user->getUserIdentifier(),
            $cartHash,
            $this->addressId ?? 'no_addr',
            $couponKey,
            $this->pointsToUse
        );
    }

    public function getCacheDuration(JsonRpcRequest $request): int
    {
        // 自动应用优惠券场景缓存时间稍短，确保优惠券变更能及时生效
        return null === $this->couponCode ? 60 : 120;
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
            'cart_hash_' . $this->getCartItemsHash(), // 购物车内容变更时可批量清理
        ];

        if (null !== $this->couponCode) {
            $tags[] = 'coupon_' . $this->couponCode;
        }

        return $tags;
    }

    /**
     * 生成购物车商品内容的哈希值
     * 确保商品ID或数量变更时缓存失效
     */
    private function getCartItemsHash(): string
    {
        // 提取关键字段并排序，确保哈希一致性
        // 注意：空购物车时返回空数组的哈希，用于纯兑换场景
        $cartData = [];
        foreach ($this->cartItems as $item) {
            $cartData[] = [
                'skuId' => (int) $item['skuId'],
                'quantity' => (int) $item['quantity'],
                // 注意：不包含price，因为价格可能实时变动
            ];
        }

        // 按skuId排序确保顺序不影响哈希结果
        usort($cartData, fn ($a, $b) => $a['skuId'] <=> $b['skuId']);

        $jsonString = json_encode($cartData);
        if (false === $jsonString) {
            throw new \RuntimeException('购物车数据序列化失败');
        }

        return hash('crc32b', $jsonString);
    }

    /**
     * 获取所有用户可用优惠券
     *
     * @return array<int, RecommendedCoupon>
     */
    private function getAllAvailableCoupons(CalculationContext $context): array
    {
        try {
            $coupons = $this->couponRecommendationService->getRecommendedCoupons($context);
            $this->logger->debug('获取所有可用优惠券', [
                'count' => count($coupons),
                'user' => $context->getUser()->getUserIdentifier(),
            ]);

            return $coupons;
        } catch (\Throwable $e) {
            $this->logger->warning('获取可用优惠券失败', [
                'error' => $e->getMessage(),
                'user' => $context->getUser()->getUserIdentifier(),
            ]);

            return [];
        }
    }

    /**
     * 为推荐优惠券补充应用状态信息
     *
     * @param array<int, RecommendedCoupon> $allAvailableCoupons
     * @param string|null $autoAppliedCoupon
     * @return array<int, array<string, mixed>>
     */
    private function enrichCouponsWithGiftInfo(array $allAvailableCoupons, CalculationContext $context, ?string $autoAppliedCoupon): array
    {
        $enrichedCoupons = [];

        foreach ($allAvailableCoupons as $recommendedCoupon) {
            $couponArray = $recommendedCoupon->formatApiData();
            $couponArray['isCurrentlyApplied'] = $recommendedCoupon->getCode() === $this->couponCode;
            $couponArray['isAutoApplied'] = $recommendedCoupon->getCode() === $autoAppliedCoupon;

            // 赠品信息已经在 RecommendedCoupon 中，无需重新获取
            // $couponArray['giftItems'] 和 $couponArray['redeemItems'] 已通过 formatApiData() 包含
            // $couponArray['hasGifts'] 也已通过 formatApiData() 包含

            $enrichedCoupons[] = $couponArray;
        }

        return $enrichedCoupons;
    }

    /**
     * 获取优惠券的赠品信息
     *
     * @return array{giftItems: array<int, array<string, mixed>>, redeemItems: array<int, array<string, mixed>>}
     */
    private function getCouponGiftInfo(RecommendedCoupon $recommendedCoupon, CalculationContext $context): array
    {
        try {
            // 通过提供者链获取完整的优惠券VO对象
            $couponVO = $this->couponProviderChain->findByCode($recommendedCoupon->getCode(), $context->getUser());

            if (null === $couponVO) {
                $this->logger->warning('无法获取优惠券VO对象', [
                    'couponCode' => $recommendedCoupon->getCode(),
                ]);

                return ['giftItems' => [], 'redeemItems' => []];
            }

            // 构建优惠券评估上下文
            $evaluationContext = $this->buildCouponEvaluationContext($context);

            // 评估优惠券获取赠品信息
            $evaluationResult = $this->couponEvaluator->evaluate($couponVO, $evaluationContext);

            return [
                'giftItems' => array_map(function ($giftItem) {
                    return [
                        'skuId' => $giftItem->getSkuId(),
                        'gtin' => $giftItem->getGtin(),
                        'quantity' => $giftItem->getQuantity(),
                        'name' => $giftItem->getName(),
                    ];
                }, $evaluationResult->getGiftItems()),
                'redeemItems' => array_map(function ($redeemItem) {
                    return [
                        'skuId' => $redeemItem->getSkuId(),
                        'quantity' => $redeemItem->getQuantity(),
                        'unitPrice' => $redeemItem->getUnitPrice(),
                        'name' => $redeemItem->getName(),
                        'subtotal' => $redeemItem->getSubtotal(),
                    ];
                }, $evaluationResult->getRedeemItems()),
            ];
        } catch (\Throwable $e) {
            // 获取赠品信息失败不影响主流程，记录警告日志
            $this->logger->warning('获取优惠券赠品信息失败', [
                'couponCode' => $recommendedCoupon->getCode(),
                'error' => $e->getMessage(),
            ]);

            return ['giftItems' => [], 'redeemItems' => []];
        }
    }

    /**
     * 将 CalculationContext 转换为 CouponEvaluationContext
     */
    private function buildCouponEvaluationContext(CalculationContext $context): CouponEvaluationContext
    {
        // 将 CheckoutItem 转换为 CouponOrderItem
        $couponOrderItems = [];
        foreach ($context->getItems() as $checkoutItem) {
            if (!$checkoutItem->isSelected()) {
                continue;
            }

            $sku = $checkoutItem->getSku();
            if (null === $sku) {
                // 这里应该通过 SKU 加载器获取 SKU，但为了简化，我们构造基本信息
                $unitPrice = '0.00';
                $skuGtin = null;
                $spuId = null;
                $spuGtin = null;
            } else {
                $unitPrice = sprintf('%.2f', (float) ($sku->getMarketPrice() ?? 0));
                $skuGtin = $sku->getGtin();
                $spuId = null !== $sku->getSpu() ? (string) $sku->getSpu()->getId() : null;
                $spuGtin = $sku->getSpu()?->getGtin();
            }

            $subtotal = bcmul($unitPrice, sprintf('%.0f', $checkoutItem->getQuantity()), 2);

            $couponOrderItems[] = new CouponOrderItem(
                (string) $checkoutItem->getSkuId(),
                $checkoutItem->getQuantity(),
                $unitPrice,
                $checkoutItem->isSelected(),
                $spuId,
                null, // categoryId
                $skuGtin,
                $spuGtin,
                $subtotal
            );
        }

        return new CouponEvaluationContext(
            $context->getUser(),
            $couponOrderItems,
            $context->getMetadata(),
            new \DateTimeImmutable()
        );
    }

    /**
     * 获取当前应用优惠券的详细信息
     *
     * @param array<int, RecommendedCoupon> $allAvailableCoupons
     * @return array<string, mixed>|null
     */
    private function getAppliedCouponDetails(?string $appliedCouponCode, array $allAvailableCoupons, CalculationContext $context): ?array
    {
        if (null === $appliedCouponCode) {
            return null;
        }

        // 从推荐优惠券列表中找到当前应用的优惠券
        $appliedRecommendedCoupon = null;
        foreach ($allAvailableCoupons as $recommendedCoupon) {
            if ($recommendedCoupon->getCode() === $appliedCouponCode) {
                $appliedRecommendedCoupon = $recommendedCoupon;
                break;
            }
        }

        if (null === $appliedRecommendedCoupon) {
            // 如果在推荐列表中找不到，可能是用户直接输入的优惠券码
            // 尝试通过 CouponProviderChain 获取
            try {
                $couponVO = $this->couponProviderChain->findByCode($appliedCouponCode, $context->getUser());
                if (null === $couponVO) {
                    $this->logger->warning('无法获取当前应用的优惠券详情', [
                        'appliedCouponCode' => $appliedCouponCode,
                    ]);

                    return null;
                }

                // 构造基础信息
                $appliedCouponDetails = [
                    'code' => $couponVO->getCode(),
                    'name' => $couponVO->getName() ?? '优惠券',
                    'type' => $couponVO->getType()->value,
                    'description' => $this->buildCouponDescription($couponVO),
                    'validFrom' => $couponVO->getValidFrom()?->format('Y-m-d H:i:s'),
                    'validTo' => $couponVO->getValidTo()?->format('Y-m-d H:i:s'),
                ];
            } catch (\Throwable $e) {
                $this->logger->warning('获取应用优惠券详情失败', [
                    'appliedCouponCode' => $appliedCouponCode,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        } else {
            // 从推荐优惠券获取基础信息
            $appliedCouponDetails = $appliedRecommendedCoupon->formatApiData();
        }

        return $appliedCouponDetails;
    }

    /**
     * 从优惠券码创建临时的 RecommendedCoupon 对象
     */
    private function createRecommendedCouponFromCode(string $couponCode, CalculationContext $context): RecommendedCoupon
    {
        try {
            $couponVO = $this->couponProviderChain->findByCode($couponCode, $context->getUser());
            if (null === $couponVO) {
                throw new \RuntimeException('优惠券不存在');
            }

            return new RecommendedCoupon(
                code: $couponVO->getCode(),
                name: $couponVO->getName() ?? '优惠券',
                type: $couponVO->getType()->value,
                expectedDiscount: '0.00', // 这里可以通过评估获取实际折扣，但为了简化暂时使用默认值
                description: $this->buildCouponDescription($couponVO),
                validFrom: $couponVO->getValidFrom()?->format('Y-m-d H:i:s'),
                validTo: $couponVO->getValidTo()?->format('Y-m-d H:i:s'),
                conditions: [],
                metadata: $couponVO->getMetadata()
            );
        } catch (\Throwable $e) {
            $this->logger->error('创建 RecommendedCoupon 失败', [
                'couponCode' => $couponCode,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 构建优惠券描述
     */
    private function buildCouponDescription(CouponVO $couponVO): string
    {
        $type = $couponVO->getType();
        $condition = $couponVO->getCondition();

        $parts = [];

        // 添加优惠类型描述
        switch ($type->value) {
            case 'full_reduction':
                $parts[] = '满减优惠';
                break;
            case 'buy_gift':
                $parts[] = '买赠优惠';
                break;
            case 'full_gift':
                $parts[] = '满赠优惠';
                break;
            case 'redeem':
                $parts[] = '兑换优惠';
                break;
            default:
                $parts[] = '优惠券';
        }

        // 添加使用条件
        if (!$condition->isNoThreshold()) {
            $thresholdAmount = $condition->getThresholdAmount();
            if (null !== $thresholdAmount && bccomp($thresholdAmount, '0.00', 2) > 0) {
                $parts[] = sprintf('满 ¥%s 可用', $thresholdAmount);
            }
        } else {
            $parts[] = '无门槛';
        }

        return implode('，', $parts);
    }

    public static function getMockResult(): ?array
    {
        return [
            'pricing' => [
                'originalPrice' => 299.98,
                'finalPrice' => 234.98,
                'totalDiscount' => 65.00,
                'promotionDiscount' => 50.00,
                'couponDiscount' => 20.00,
                'pointsDiscount' => 10.00,
                'shippingFee' => 15.00,
                'savings' => 65.00,
            ],
            'products' => [
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
                    'quantity' => 3,
                    'price' => 99.99,
                ],
            ],
            'couponInfo' => [
                'appliedCouponCode' => 'SAVE20',
                'originalCouponCode' => null,
                'autoAppliedCoupon' => 'SAVE20',
                'hasAutoApplied' => true,
                'userSelectedCoupon' => false,
                'appliedCoupon' => [
                    'code' => 'SAVE20',
                    'name' => '满200减20优惠券',
                    'type' => 'full_reduction',
                    'expectedDiscount' => 20.00,
                    'description' => '立减 ¥20.00，满 ¥200.00 可用',
                    'validFrom' => '2025-10-20 00:00:00',
                    'validTo' => '2025-11-20 23:59:59',
                    'conditions' => [
                        'min_amount' => '200.00',
                        'max_amount' => null,
                        'applicable_products' => ['type' => 'all'],
                    ],
                    'giftItems' => [],
                    'redeemItems' => [],
                    'hasGifts' => false,
                ],
            ],
            'availableCoupons' => [
                [
                    'code' => 'SAVE20',
                    'name' => '满200减20优惠券',
                    'type' => 'full_reduction',
                    'expectedDiscount' => '20.00',
                    'description' => '立减 ¥20.00，满 ¥200.00 可用',
                    'validFrom' => '2025-10-20 00:00:00',
                    'validTo' => '2025-11-20 23:59:59',
                    'conditions' => [
                        'min_amount' => '200.00',
                        'max_amount' => null,
                        'applicable_products' => ['type' => 'all'],
                    ],
                    'isCurrentlyApplied' => true,
                    'isAutoApplied' => true,
                    'giftItems' => [],
                    'redeemItems' => [],
                    'hasGifts' => false,
                ],
                [
                    'code' => 'BUY_GIFT_001',
                    'name' => '买赠券',
                    'type' => 'buy_gift',
                    'expectedDiscount' => '0.00',
                    'description' => '买赠优惠',
                    'validFrom' => '2025-10-20 00:00:00',
                    'validTo' => '2025-11-20 23:59:59',
                    'conditions' => [
                        'min_amount' => '0.00',
                        'max_amount' => null,
                        'applicable_products' => ['type' => 'all'],
                    ],
                    'isCurrentlyApplied' => false,
                    'isAutoApplied' => false,
                    'giftItems' => [
                        [
                            'gtin' => '6901234567890',
                            'quantity' => 1,
                            'name' => '赠品小样',
                        ],
                        [
                            'gtin' => '6901234567891',
                            'quantity' => 2,
                            'name' => '赠品试用装',
                        ],
                    ],
                    'redeemItems' => [],
                    'hasGifts' => true,
                ],
                [
                    'code' => 'REDEEM_001',
                    'name' => '兑换券',
                    'type' => 'redeem',
                    'expectedDiscount' => '99.00',
                    'description' => '兑换优惠',
                    'validFrom' => '2025-10-20 00:00:00',
                    'validTo' => '2025-11-20 23:59:59',
                    'conditions' => [
                        'min_amount' => '0.00',
                        'max_amount' => null,
                        'applicable_products' => ['type' => 'all'],
                    ],
                    'isCurrentlyApplied' => false,
                    'isAutoApplied' => false,
                    'giftItems' => [],
                    'redeemItems' => [
                        [
                            'skuId' => 'SKU123',
                            'quantity' => 1,
                            'unitPrice' => '99.00',
                            'name' => '兑换商品',
                            'subtotal' => '99.00',
                        ],
                    ],
                    'hasGifts' => true,
                ],
            ],
        ];
    }
}
