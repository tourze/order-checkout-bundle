<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Checkout;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\CouponCoreBundle\Service\CouponEvaluator;
use Tourze\CouponCoreBundle\ValueObject\CouponEvaluationContext;
use Tourze\CouponCoreBundle\ValueObject\CouponOrderItem;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Procedure\BaseProcedure;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\DTO\RecommendedCoupon;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationException;
use Tourze\OrderCheckoutBundle\Param\Checkout\CalculatePriceParam;
use Tourze\OrderCheckoutBundle\Provider\CouponProviderChain;
use Tourze\OrderCheckoutBundle\Service\CouponRecommendationService;
use Tourze\OrderCheckoutBundle\Service\PriceCalculationService;

#[MethodTag(name: '订单结算')]
#[MethodDoc(description: '计算购物车商品价格（预结算）')]
#[MethodExpose(method: 'CalculateCheckoutPrice')]
#[IsGranted(attribute: 'ROLE_USER')]
#[WithMonologChannel(channel: 'order_checkout')]
final class CalculatePriceProcedure extends BaseProcedure
{
    public function __construct(
        private readonly Security $security,
        private readonly PriceCalculationService $priceCalculationService,
        private readonly CouponRecommendationService $couponRecommendationService,
        private readonly CouponEvaluator $couponEvaluator,
        private readonly CouponProviderChain $couponProviderChain,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @phpstan-param CalculatePriceParam $param
     */
    public function execute(CalculatePriceParam|RpcParamInterface $param): ArrayResult
    {
        $this->logger->debug('[计价流程] 开始执行计价', [
            'cartItems' => $param->cartItems,
            'addressId' => $param->addressId,
            'couponCode' => $param->couponCode,
            'pointsToUse' => $param->pointsToUse,
            'useCoupon' => $param->useCoupon,
            'paymentMode' => $param->paymentMode,
            'useIntegralAmount' => $param->useIntegralAmount,
        ]);

        $user = $this->validateUser();
        $this->logger->debug('[计价流程] 用户验证通过', ['userId' => $user->getUserIdentifier()]);

        $this->validateCartAndCoupon($param);
        $this->logger->debug('[计价流程] 购物车和优惠券验证通过');

        $checkoutItems = $this->convertCartItemsToCheckoutItems($param);
        $this->logger->debug('[计价流程] 购物车商品转换完成', [
            'checkoutItemsCount' => count($checkoutItems),
            'checkoutItems' => array_map(fn ($item) => [
                'skuId' => $item->getSkuId(),
                'quantity' => $item->getQuantity(),
                'selected' => $item->isSelected(),
                'hasSku' => null !== $item->getSku(),
            ], $checkoutItems),
        ]);

        $allAvailableCoupons = $this->fetchAvailableCoupons($param, $user, $checkoutItems);
        $this->logger->debug('[计价流程] 可用优惠券获取完成', [
            'availableCouponsCount' => count($allAvailableCoupons),
        ]);

        $autoAppliedCoupon = $this->applyAutoCouponIfNeeded($param, $user, $allAvailableCoupons);
        $this->logger->debug('[计价流程] 自动应用优惠券处理完成', [
            'autoAppliedCoupon' => $autoAppliedCoupon,
            'finalCouponCode' => $param->couponCode,
        ]);

        $context = $this->buildFinalCalculationContext($param, $user, $checkoutItems);
        $this->logger->debug('[计价流程] 计算上下文构建完成', [
            'itemsCount' => count($context->getItems()),
            'items' => $context->getItems(),
            'appliedCouponsCount' => count($context->getAppliedCoupons()),
            'metadata' => $context->getMetadata(),
        ]);

        try {
            $this->logger->debug('[计价流程] 开始执行价格计算');
            $priceResult = $this->priceCalculationService->calculate($context);
            $this->logger->debug('[计价流程] 价格计算完成', [
                'originalPrice' => $priceResult->getOriginalPrice(),
                'finalPrice' => $priceResult->getFinalPrice(),
                'discount' => $priceResult->getDiscount(),
                'productsCount' => count($priceResult->getProducts()),
                'detailsCount' => count($priceResult->getDetails()),
            ]);

            $result = $this->buildCalculationResult($param, $priceResult, $autoAppliedCoupon, $allAvailableCoupons, $context);
            $this->logger->debug('[计价流程] 计价流程执行完成', [
                'resultKeys' => array_keys($result),
                'pricing' => $result['pricing'] ?? [],
                'productsCount' => count($result['products'] ?? []),
            ]);

            return new ArrayResult($result);
        } catch (PriceCalculationException $e) {
            $this->logger->error('[计价流程] 价格计算失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new ApiException($e->getMessage());
        }
    }

    private function validateUser(): UserInterface
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        return $user;
    }

    private function validateCartAndCoupon(CalculatePriceParam $param): void
    {
        if ([] === $param->cartItems && null === $param->couponCode) {
            throw new ApiException('购物车中没有选中的商品，且未提供优惠券');
        }
    }

    /**
     * @return CheckoutItem[]
     */
    private function convertCartItemsToCheckoutItems(CalculatePriceParam $param): array
    {
        $checkoutItems = [];
        foreach ($param->cartItems as $item) {
            $checkoutItems[] = CheckoutItem::fromArray($item);
        }

        return new ArrayResult($checkoutItems);
    }

    /**
     * @param CheckoutItem[] $checkoutItems
     * @return RecommendedCoupon[]
     */
    private function fetchAvailableCoupons(CalculatePriceParam $param, UserInterface $user, array $checkoutItems): array
    {
        if (!$param->getAvailableCoupons && !$param->useCoupon) {
            return new ArrayResult([]);
        }

        $tempContext = new CalculationContext(
            $user,
            $checkoutItems,
            [],
            [
                'addressId' => $param->addressId,
                'pointsToUse' => $param->pointsToUse,
                'orderType' => 'normal',
                'paymentMode' => $param->paymentMode,
                'useIntegralAmount' => $param->useIntegralAmount,
            ]
        );

        return $this->getAllAvailableCoupons($tempContext);
    }

    /**
     * @param RecommendedCoupon[] $allAvailableCoupons
     */
    private function applyAutoCouponIfNeeded(CalculatePriceParam $param, UserInterface $user, array $allAvailableCoupons): ?string
    {
        if (!$param->useCoupon || null !== $param->couponCode || [] === $allAvailableCoupons) {
            return null;
        }

        $autoAppliedCoupon = $allAvailableCoupons[0]->getCode();
        $this->logger->info('自动应用优惠券', [
            'couponCode' => $autoAppliedCoupon,
            'user' => $user->getUserIdentifier(),
            'availableCount' => count($allAvailableCoupons),
        ]);

        return new ArrayResult($autoAppliedCoupon);
    }

    /**
     * @param CheckoutItem[] $checkoutItems
     */
    private function buildFinalCalculationContext(CalculatePriceParam $param, UserInterface $user, array $checkoutItems): CalculationContext
    {
        $couponCode = $param->couponCode;
        $appliedCoupons = null !== $couponCode ? [$couponCode] : [];
        $isRedeemOnlyOrder = [] === $param->cartItems && null !== $couponCode;

        return new CalculationContext(
            $user,
            $checkoutItems,
            $appliedCoupons,
            [
                'addressId' => $param->addressId,
                'pointsToUse' => $param->pointsToUse,
                'orderType' => $isRedeemOnlyOrder ? 'redeem' : 'normal',
                'paymentMode' => $param->paymentMode,
                'useIntegralAmount' => $param->useIntegralAmount,
            ]
        );
    }

    /**
     * @param RecommendedCoupon[] $allAvailableCoupons
     * @return array<string, mixed>
     */
    private function buildCalculationResult(
        CalculatePriceParam $param,
        PriceResult $priceResult,
        ?string $autoAppliedCoupon,
        array $allAvailableCoupons,
        CalculationContext $context,
    ): array {
        $result = [
            'pricing' => [
                'originalPrice' => $priceResult->getOriginalPrice(),
                'finalPrice' => $priceResult->getFinalPrice(),
                'totalDiscount' => $priceResult->getDiscount(),
                'promotionDiscount' => $priceResult->getDetail('promotion_discount', 0.0),
                'couponDiscount' => $priceResult->getDetail('coupon_discount', 0.0),
                'pointsDiscount' => $priceResult->getDetail('points_discount', 0.0),
                'shippingFee' => $priceResult->getDetail('shipping_fee', 0.0),
                'totalIntegralRequired' => $priceResult->getDetail('total_integral_required', 0),
                'savings' => $priceResult->getDiscount(),
            ],
            'products' => $priceResult->getProducts(),
            'breakdown' => $priceResult->getDetails(),
            'appliedPromotions' => $priceResult->getDetail('applied_promotions', []),
            'items' => $param->cartItems,
        ];

        $couponCode = $param->couponCode;
        $result['couponInfo'] = [
            'appliedCouponCode' => $couponCode,
            'originalCouponCode' => $couponCode,
            'autoAppliedCoupon' => $autoAppliedCoupon,
            'hasAutoApplied' => null !== $autoAppliedCoupon,
            'userSelectedCoupon' => null === $autoAppliedCoupon && null !== $couponCode,
            'appliedCoupon' => $this->getAppliedCouponDetails($couponCode, $allAvailableCoupons, $context),
        ];

        $result['availableCoupons'] = $this->enrichCouponsWithGiftInfo($param, $allAvailableCoupons, $context, $autoAppliedCoupon);

        return new ArrayResult($result);
    }

    public function getCacheKey(JsonRpcRequest $request): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            throw new ApiException('用户未登录或类型错误');
        }

        $params = $request->getParams();
        $cartItems = $params->get('cartItems', []);
        $addressId = $params->get('addressId');
        $couponCode = $params->get('couponCode');
        $pointsToUse = $params->get('pointsToUse', 0);
        $paymentMode = $params->get('paymentMode', 'CASH_ONLY');
        $useIntegralAmount = $params->get('useIntegralAmount', 0);

        // 【修正】包含购物车商品内容的哈希，确保商品变更时缓存失效
        $cartHash = $this->getCartItemsHash($cartItems);
        $couponKey = $couponCode ?? 'auto_apply';

        return sprintf(
            'price_calc:%s:%s:%s:%s:%d:%s:%d',
            $user->getUserIdentifier(),
            $cartHash,
            $addressId ?? 'no_addr',
            $couponKey,
            $pointsToUse,
            $paymentMode,
            $useIntegralAmount
        );
    }

    public function getCacheDuration(JsonRpcRequest $request): int
    {
        $params = $request->getParams();
        $couponCode = $params->get('couponCode');

        // 自动应用优惠券场景缓存时间稍短，确保优惠券变更能及时生效
        return null === $couponCode ? 60 : 120;
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

        $params = $request->getParams();
        $cartItems = $params->get('cartItems', []);
        $couponCode = $params->get('couponCode');

        $tags = [
            'checkout',
            'price_calculation',
            'cart_user_' . $user->getUserIdentifier(),
            'cart_hash_' . $this->getCartItemsHash($cartItems), // 购物车内容变更时可批量清理
        ];

        if (null !== $couponCode) {
            $tags[] = 'coupon_' . $couponCode;
        }

        return new ArrayResult($tags);
    }

    /**
     * 生成购物车商品内容的哈希值
     * 确保商品ID或数量变更时缓存失效
     *
     * @param array<int, array{id?: int, skuId: int, quantity: int, price?: float}> $cartItems
     */
    private function getCartItemsHash(array $cartItems): string
    {
        // 提取关键字段并排序，确保哈希一致性
        // 注意：空购物车时返回空数组的哈希，用于纯兑换场景
        $cartData = [];
        foreach ($cartItems as $item) {
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

            return new ArrayResult($coupons);
        } catch (\Throwable $e) {
            $this->logger->warning('获取可用优惠券失败', [
                'error' => $e->getMessage(),
                'user' => $context->getUser()->getUserIdentifier(),
            ]);

            return new ArrayResult([]);
        }
    }

    /**
     * 为推荐优惠券补充应用状态信息
     *
     * @param array<int, RecommendedCoupon> $allAvailableCoupons
     * @param string|null $autoAppliedCoupon
     * @return array<int, array<string, mixed>>
     */
    private function enrichCouponsWithGiftInfo(CalculatePriceParam $param, array $allAvailableCoupons, CalculationContext $context, ?string $autoAppliedCoupon): array
    {
        $enrichedCoupons = [];

        foreach ($allAvailableCoupons as $recommendedCoupon) {
            $couponArray = $recommendedCoupon->formatApiData();
            $couponArray['isCurrentlyApplied'] = $recommendedCoupon->getCode() === $param->couponCode;
            $couponArray['isAutoApplied'] = $recommendedCoupon->getCode() === $autoAppliedCoupon;

            // 赠品信息已经在 RecommendedCoupon 中，无需重新获取
            // $couponArray['giftItems'] 和 $couponArray['redeemItems'] 已通过 formatApiData() 包含
            // $couponArray['hasGifts'] 也已通过 formatApiData() 包含

            $enrichedCoupons[] = $couponArray;
        }

        return new ArrayResult($enrichedCoupons);
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

                return new ArrayResult(['giftItems' => [], 'redeemItems' => []]);
            }

            // 构建优惠券评估上下文
            $evaluationContext = $this->buildCouponEvaluationContext($context);

            // 评估优惠券获取赠品信息
            $evaluationResult = $this->couponEvaluator->evaluate($couponVO, $evaluationContext);

            return new ArrayResult([
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
            ]);
        } catch (\Throwable $e) {
            // 获取赠品信息失败不影响主流程，记录警告日志
            $this->logger->warning('获取优惠券赠品信息失败', [
                'couponCode' => $recommendedCoupon->getCode(),
                'error' => $e->getMessage(),
            ]);

            return new ArrayResult(['giftItems' => [], 'redeemItems' => []]);
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

        return new ArrayResult($appliedCouponDetails);
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
}
