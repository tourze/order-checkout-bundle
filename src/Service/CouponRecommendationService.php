<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\Exception\CouponEvaluationException;
use Tourze\CouponCoreBundle\Service\CouponEvaluator;
use Tourze\CouponCoreBundle\ValueObject\CouponEvaluationContext;
use Tourze\CouponCoreBundle\ValueObject\CouponOrderItem;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\RecommendedCoupon;
use Tourze\OrderCheckoutBundle\Provider\CouponProviderChain;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductServiceContracts\SkuLoaderInterface;

/**
 * 优惠券推荐服务
 * 负责获取用户可用优惠券并根据购物车内容进行筛选和排序
 */
#[WithMonologChannel(channel: 'order_checkout')]
final class CouponRecommendationService
{
    public function __construct(
        private readonly CouponProviderChain $providerChain,
        private readonly CouponEvaluator $couponEvaluator,
        private readonly SkuLoaderInterface $skuLoader,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * 获取推荐优惠券列表
     *
     * @return array<int, RecommendedCoupon>
     */
    public function getRecommendedCoupons(CalculationContext $context): array
    {
        return $this->processRecommendations($context);
    }

    /**
     * 处理优惠券推荐逻辑
     *
     * @return array<int, RecommendedCoupon>
     */
    private function processRecommendations(CalculationContext $context): array
    {
        $availableCoupons = $this->getAllAvailableCoupons($context->getUser());
        if ([] === $availableCoupons) {
            return [];
        }

        $orderItems = $this->buildOrderItems($context);
        if ([] === $orderItems) {
            return [];
        }

        $evaluationContext = new CouponEvaluationContext(
            $context->getUser(),
            array_values($orderItems), // 确保是 list 类型
            $context->getMetadata(),
            $this->normalizeCalculateTime($context)
        );

        return $this->evaluateCoupons($availableCoupons, $evaluationContext, $orderItems);
    }

    /**
     * 评估优惠券列表
     *
     * @param array<int, CouponVO> $coupons
     * @param array<int, CouponOrderItem> $orderItems
     * @return array<int, RecommendedCoupon>
     */
    private function evaluateCoupons(array $coupons, CouponEvaluationContext $context, array $orderItems): array
    {
        $totalAmount = $this->calculateTotalAmount($orderItems);
        $recommendations = [];
        $maxRecommendations = 100;
        $evaluatedCount = 0;

        $this->logger->debug('开始评估可用优惠券',[
            'coupons' => $coupons
        ]);
        foreach ($coupons as $couponVO) {
            $this->logger?->debug('评估优惠券' . $couponVO->getCode(), ['code' => $couponVO->toArray()]);
            if ($this->shouldStopEvaluation($evaluatedCount, $maxRecommendations, $recommendations)) {
                $this->logger?->debug('停止评估优惠券'. $couponVO->getCode(), ['code' => $couponVO->toArray()]);
                break;
            }

            if ($this->quickValidate($couponVO, $totalAmount)) {
                $recommendation = $this->evaluateCoupon($couponVO, $context);
                if (null !== $recommendation) {
                    $recommendations[] = $recommendation;
                }
            }

            ++$evaluatedCount;
        }

        // 按优惠金额降序排序
        usort($recommendations, function (RecommendedCoupon $a, RecommendedCoupon $b): int {
            /** @var numeric-string $aDiscount */
            $aDiscount = $a->getExpectedDiscount();
            /** @var numeric-string $bDiscount */
            $bDiscount = $b->getExpectedDiscount();

            return bccomp($bDiscount, $aDiscount, 2);
        });

        return $recommendations;
    }

    /**
     * 判断是否应该停止评估
     *
     * @param array<int, RecommendedCoupon> $currentRecommendations
     */
    private function shouldStopEvaluation(int $evaluatedCount, int $maxRecommendations, array $currentRecommendations): bool
    {
        return $evaluatedCount >= $maxRecommendations * 2 || count($currentRecommendations) >= $maxRecommendations;
    }

    /**
     * 计算订单项总金额
     *
     * @param array<int, CouponOrderItem> $orderItems
     */
    private function calculateTotalAmount(array $orderItems): string
    {
        return array_reduce(
            $orderItems,
            fn (string $carry, CouponOrderItem $item): string => bcadd($carry, $item->getSubtotal(), 2),
            '0.00'
        );
    }

    /**
     * 快速验证优惠券是否可能适用
     */
    private function quickValidate(CouponVO $couponVO, string $totalAmount): bool
    {
        // 检查有效期
        $now = new \DateTimeImmutable();
        if (!$couponVO->isWithinValidity($now)) {
            $this->logger?->debug('优惠券不在有效期内（快速验证）' . $couponVO->getCode(), ['code' => $couponVO->getCode()]);
            return false;
        }

        // 检查最低消费门槛（无门槛优惠券跳过此检查）
        $condition = $couponVO->getCondition();
        if (!$condition->isNoThreshold()) {
            $thresholdAmount = $condition->getThresholdAmount();
            if (null !== $thresholdAmount && $thresholdAmount > '0') {
                /** @var numeric-string $thresholdAmount */
                /** @var numeric-string $totalAmount */
                if (bccomp($totalAmount, $thresholdAmount, 2) < 0) {
                    $this->logger?->debug('优惠券未达到最低消费门槛（快速验证）'. $couponVO->getCode(), [
                        'code' => $couponVO->getCode(),
                        'threshold' => $thresholdAmount,
                        'total' => $totalAmount,
                    ]);
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 获取用户所有可用优惠券
     *
     * @return array<int, CouponVO>
     */
    private function getAllAvailableCoupons(UserInterface $user): array
    {
        $coupons = [];

        // 从所有提供者获取优惠券
        foreach ($this->providerChain->getProviders() as $provider) {
            try {
                // 注意：这里需要扩展 CouponProviderInterface 添加 getAllCouponsForUser 方法
                // 或者通过其他方式获取用户的优惠券列表
                if (method_exists($provider, 'getAllCouponsForUser')) {
                    $providerCoupons = $provider->getAllCouponsForUser($user);
                    $coupons = array_merge($coupons, $providerCoupons);
                }
            } catch (\Throwable $e) {
                $this->logger?->warning('获取优惠券失败', [
                    'provider' => $provider->getIdentifier(),
                    'user' => $user->getUserIdentifier(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->debug('获取所有有效优惠券',[
            'coupons' => $coupons
        ]);
        return $coupons;
    }

    /**
     * 评估单个优惠券
     */
    private function evaluateCoupon(CouponVO $couponVO, CouponEvaluationContext $context): ?RecommendedCoupon
    {
        try {
            // 检查有效期
            if (!$couponVO->isWithinValidity($context->getEvaluateTime())) {
                $this->logger?->debug('优惠券不在有效期内'. $couponVO->getCode(), ['code' => $couponVO->getCode()]);
                return null;
            }

            // 检查适用范围
            if (!$this->isApplicableToCart($couponVO, $context->getItems())) {
                $this->logger?->debug('优惠券不适用于当前购物车'. $couponVO->getCode(), ['code' => $couponVO->getCode()]);
                return null;
            }

            // 评估优惠金额
            $result = $this->couponEvaluator->evaluate($couponVO, $context);

            $typeValue = $couponVO->getType()->value;
            $isGiftOrRedeemCoupon = in_array($typeValue, ['full_gift', 'buy_gift', 'redeem'], true);
            $hasEffectiveBenefit = $result->hasDiscount()
                || ($isGiftOrRedeemCoupon && ($result->hasGifts() || $result->hasRedeemItems()));

            if (!$hasEffectiveBenefit) {
                $this->logger?->debug('优惠券无可用折扣', ['code' => $couponVO->getCode()]);
                return null;
            }

            $discountAmount = $result->getDiscountAmount();

            // 构建赠品信息
            $giftItems = array_map(static function ($giftItem) {
                return [
                    'skuId' => $giftItem->getSkuId(),
                    'gtin' => $giftItem->getGtin(),
                    'quantity' => $giftItem->getQuantity(),
                    'name' => $giftItem->getName(),
                ];
            }, $result->getGiftItems());

            $redeemItems = array_map(static function ($redeemItem) {
                return [
                    'skuId' => $redeemItem->getSkuId(),
                    'quantity' => $redeemItem->getQuantity(),
                    'unitPrice' => $redeemItem->getUnitPrice(),
                    'name' => $redeemItem->getName(),
                    'subtotal' => $redeemItem->getSubtotal(),
                ];
            }, $result->getRedeemItems());

            return new RecommendedCoupon(
                code: $couponVO->getCode(),
                name: $couponVO->getName() ?? '优惠券',
                type: $couponVO->getType()->value,
                expectedDiscount: $discountAmount,
                description: $this->buildCouponDescription($couponVO, $discountAmount),
                validFrom: $couponVO->getValidFrom()?->format('Y-m-d H:i:s'),
                validTo: $couponVO->getValidTo()?->format('Y-m-d H:i:s'),
                conditions: $this->extractConditions($couponVO),
                metadata: $couponVO->getMetadata(),
                giftItems: $giftItems,
                redeemItems: $redeemItems
            );
        } catch (CouponEvaluationException $e) {
            $this->logger?->debug('优惠券不可用' . $couponVO->getCode() , [
                'reason' => $e->getMessage(),
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->logger?->error('优惠券评估异常' . $couponVO->getCode(), [
                'code' => $couponVO->getCode(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 检查优惠券是否适用于当前购物车
     *
     * @param array<int, CouponOrderItem> $orderItems
     */
    private function isApplicableToCart(CouponVO $couponVO, array $orderItems): bool
    {
        $scope = $couponVO->getScope();
        $condition = $couponVO->getCondition();

        // 检查商品范围限制
        $applicableItems = [];
        foreach ($orderItems as $item) {
            if ($scope->isSkuEligible($item->getSkuId())) {
                $applicableItems[] = $item;
            }
        }

        if ([] === $applicableItems) {
            return false;
        }

        // 检查最低消费门槛
        $totalAmount = array_reduce(
            $applicableItems,
            fn (string $carry, CouponOrderItem $item): string => bcadd($carry, $item->getSubtotal(), 2),
            '0.00'
        );

        // 检查最低消费门槛（无门槛优惠券跳过此检查）
        if (!$condition->isNoThreshold()) {
            $thresholdAmount = $condition->getThresholdAmount();
            if (null !== $thresholdAmount && bccomp($totalAmount, $thresholdAmount, 2) < 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * 构建优惠券描述
     */
    private function buildCouponDescription(CouponVO $couponVO, string $discountAmount): string
    {
        $type = $couponVO->getType();
        $condition = $couponVO->getCondition();

        $parts = [];

        // 添加优惠类型描述
        switch ($type->value) {
            case 'full_reduction':
                $parts[] = sprintf('立减 ¥%s', $discountAmount);
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
                $parts[] = sprintf('优惠 ¥%s', $discountAmount);
        }

        // 添加使用条件
        $thresholdAmount = $condition->getThresholdAmount();
        if (null !== $thresholdAmount) {
            $parts[] = sprintf('满 ¥%s 可用', $thresholdAmount);
        }

        return implode('，', $parts);
    }

    /**
     * 提取优惠券使用条件
     *
     * @return array<string, mixed>
     */
    private function extractConditions(CouponVO $couponVO): array
    {
        $condition = $couponVO->getCondition();

        return [
            'min_amount' => $condition->getThresholdAmount(),
            'max_amount' => null, // CouponConditionVO 没有 maxAmount 概念
            'applicable_products' => $this->getApplicableProductInfo($couponVO),
        ];
    }

    /**
     * 获取适用商品信息
     *
     * @return array<string, mixed>
     */
    private function getApplicableProductInfo(CouponVO $couponVO): array
    {
        $scope = $couponVO->getScope();

        return [
            'type' => $scope->getType()->value,
            'included_skus' => $scope->getIncludedSkuIds(),
            'excluded_skus' => $scope->getExcludedSkuIds(),
            'included_spus' => $scope->getIncludedSpuIds(),
            'included_categories' => $scope->getIncludedCategoryIds(),
        ];
    }

    /**
     * 构建订单项用于优惠券评估
     *
     * @return array<int, CouponOrderItem>
     */
    private function buildOrderItems(CalculationContext $context): array
    {
        $items = [];
        foreach ($context->getItems() as $item) {
            if (!$item instanceof CheckoutItem || !$item->isSelected()) {
                continue;
            }

            $sku = $item->getSku();
            if (null === $sku) {
                $sku = $this->skuLoader->loadSkuByIdentifier((string) $item->getSkuId());
            }

            if (!$sku instanceof Sku) {
                $this->logger?->warning('优惠券推荐跳过未知SKU', ['skuId' => $item->getSkuId()]);
                continue;
            }

            $unitPrice = $this->normalizePrice($sku->getMarketPrice());
            $quantity = $item->getQuantity();
            $subtotal = bcmul($unitPrice, sprintf('%.0f', $quantity), 2);
            /** @var numeric-string $unitPrice */
            $unitPrice = $unitPrice;
            /** @var numeric-string $subtotal */
            $subtotal = $subtotal;

            $items[] = new CouponOrderItem(
                (string) $item->getSkuId(),
                $quantity,
                $unitPrice,
                $item->isSelected(),
                null !== $sku->getSpu()?->getId() ? (string) $sku->getSpu()->getId() : null,
                null,
                $sku->getGtin(),
                $sku->getSpu()?->getGtin(),
                $subtotal
            );
        }

        return $items;
    }

    private function normalizeCalculateTime(CalculationContext $context): \DateTimeImmutable
    {
        $calculateTime = $context->getMetadataValue('calculate_time');
        if ($calculateTime instanceof \DateTimeImmutable) {
            return $calculateTime;
        }
        if ($calculateTime instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($calculateTime);
        }

        return new \DateTimeImmutable();
    }

    /**
     * @return numeric-string
     */
    private function normalizePrice(mixed $price): string
    {
        if (is_string($price) && is_numeric($price)) {
            return sprintf('%.2f', (float) $price);
        }

        if (is_numeric($price)) {
            return sprintf('%.2f', (float) $price);
        }

        return '0.00';
    }
}
