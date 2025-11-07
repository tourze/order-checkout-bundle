<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Calculator;

use Psr\Log\LoggerInterface;
use Tourze\CouponCoreBundle\Exception\CouponEvaluationException;
use Tourze\CouponCoreBundle\Service\CouponEvaluator;
use Tourze\CouponCoreBundle\ValueObject\CouponEvaluationContext;
use Tourze\CouponCoreBundle\ValueObject\CouponOrderItem;
use Tourze\OrderCheckoutBundle\Contract\PriceCalculatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\Provider\CouponProviderChain;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductServiceContracts\SkuLoaderInterface;

/**
 * 优惠券计算器
 */
class CouponCalculator implements PriceCalculatorInterface
{
    public function __construct(
        private readonly CouponProviderChain $providerChain,
        private readonly CouponEvaluator $couponEvaluator,
        private readonly SkuLoaderInterface $skuLoader,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function calculate(CalculationContext $context): PriceResult
    {
        $appliedCoupons = $context->getAppliedCoupons();
        if ([] === $appliedCoupons) {
            return PriceResult::empty();
        }

        $orderItems = $this->buildOrderItems($context);
        $isRedeemOnlyOrder = $context->getMetadataValue('orderType') === 'redeem';
        
        // 纯兑换券场景：即使没有基础商品也要进行计算
        if ([] === $orderItems && !$isRedeemOnlyOrder) {
            return PriceResult::empty();
        }

        $evaluationContext = new CouponEvaluationContext(
            $context->getUser(),
            $orderItems,
            $context->getMetadata(),
            $this->normalizeCalculateTime($context)
        );

        $aggregate = new CouponCalculationAggregate($this->skuLoader);

        foreach ($appliedCoupons as $couponCode) {
            $this->processCouponCode($context, $evaluationContext, $couponCode, $aggregate);
        }

        if (!$aggregate->hasEffectiveResult()) {
            return PriceResult::empty();
        }

        $totalDiscount = $aggregate->getTotalDiscount();
        $details = $aggregate->buildDetailPayload();

        // 计算最终价格：普通场景返回负值，纯兑换券场景返回0
        $finalPrice = bcsub('0.00', $totalDiscount, 2); // 0 - 折扣 = 负值
        
        // 只有在纯兑换券场景下才将负值改为0
        if ($isRedeemOnlyOrder && bccomp($finalPrice, '0.00', 2) < 0) {
            $finalPrice = '0.00';
        }

        return new PriceResult(
            originalPrice: '0.00',
            finalPrice: $finalPrice,
            discount: $totalDiscount,
            details: $details,
            products: $aggregate->getProducts()
        );
    }

    public function supports(CalculationContext $context): bool
    {
        return count($context->getAppliedCoupons()) > 0;
    }

    public function getPriority(): int
    {
        return 600;
    }

    public function getType(): string
    {
        return 'coupon';
    }

    /**
     * @return list<CouponOrderItem>
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
                $this->logger?->warning('优惠券计算跳过未知SKU', ['skuId' => $item->getSkuId()]);
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
                $sku->getSpu()?->getId() !== null ? (string) $sku->getSpu()->getId() : null,
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

    private function processCouponCode(
        CalculationContext $context,
        CouponEvaluationContext $evaluationContext,
        string $couponCode,
        CouponCalculationAggregate $aggregate
    ): void {
        try {
            // 直接通过 Provider 链获取 VO
            $couponVO = $this->providerChain->findByCode($couponCode, $context->getUser());
            if (null === $couponVO) {
                $aggregate->recordMessage(sprintf('优惠券码[%s]不存在或未归属当前用户', $couponCode));
                $this->logger?->warning('优惠券码不存在', [
                    'code' => $couponCode,
                    'user' => $context->getUser()->getUserIdentifier(),
                ]);
                return;
            }
            
            $result = $this->couponEvaluator->evaluate(
                $couponVO,
                $evaluationContext->withMetadata(['coupon_code' => $couponCode])
            );
            
            $aggregate->applyResult($couponCode, $result);
        } catch (CouponEvaluationException $exception) {
            $aggregate->recordMessage(sprintf('优惠券[%s]不可用: %s', $couponCode, $exception->getMessage()));
            $this->logger?->warning('优惠券评估失败', [
                'code' => $couponCode,
                'error' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            $aggregate->recordMessage(sprintf('优惠券[%s]应用异常', $couponCode));
            $this->logger?->error('优惠券评估异常', [
                'code' => $couponCode,
                'exception' => $exception,
            ]);
        }
    }

}
