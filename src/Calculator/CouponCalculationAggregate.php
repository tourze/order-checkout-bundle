<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Calculator;

use Tourze\CouponCoreBundle\ValueObject\CouponApplicationResult;
use Tourze\CouponCoreBundle\ValueObject\GiftItem;
use Tourze\CouponCoreBundle\ValueObject\RedeemItem;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductServiceContracts\SkuLoaderInterface;

/**
 * @internal
 */
final class CouponCalculationAggregate
{
    /** @var numeric-string */
    private string $totalDiscount = '0.00';

    /** @var array<string, numeric-string> */
    private array $allocationSummary = [];

    /** @var array<int, array{sku_id: int, gtin?: string, quantity: int, name?: string|null}> */
    private array $giftSummary = [];

    /** @var array<string, array{sku_id: string, quantity: int, unit_price: string, name?: string|null}> */
    private array $redeemSummary = [];

    /** @var array<string, array<string, mixed>> */
    private array $breakdown = [];

    /** @var array<int, array<string, mixed>> */
    private array $products = [];

    /** @var string[] */
    private array $appliedCodes = [];

    /** @var string[] */
    private array $messages = [];

    private bool $shouldMarkPaid = false;

    public function __construct(
        private readonly SkuLoaderInterface $skuLoader,
    ) {
    }

    public function recordMessage(string $message): void
    {
        $this->messages[] = $message;
    }

    public function applyResult(string $couponCode, CouponApplicationResult $result): void
    {
        $discount = $this->normalizeAmount($result->getDiscountAmount());
        $this->totalDiscount = bcadd($this->totalDiscount, $discount, 2);

        $this->mergeAllocations($result->getAllocations());
        $this->mergeGifts($result->getGiftItems());
        $this->mergeRedeemItems($result->getRedeemItems());

        $this->breakdown[$couponCode] = [
            'code' => $couponCode,
            'discount' => $discount,
            'allocations' => $result->getAllocations(),
            'gifts' => array_map(
                static fn (GiftItem $gift): array => [
                    'sku_id' => $gift->getSkuId(),
                    'gtin' => $gift->getGtin(),
                    'quantity' => $gift->getQuantity(),
                    'name' => $gift->getName(),
                ],
                $result->getGiftItems()
            ),
            'redeem_items' => array_map(
                static fn (RedeemItem $item): array => [
                    'sku_id' => $item->getSkuId(),
                    'quantity' => $item->getQuantity(),
                    'unit_price' => $item->getUnitPrice(),
                    'name' => $item->getName(),
                ],
                $result->getRedeemItems()
            ),
            'metadata' => $result->getMetadata(),
        ];

        $this->appendProducts($result);

        if ($result->shouldMarkOrderPaid()) {
            $this->shouldMarkPaid = true;
        }

        $this->appliedCodes[] = $couponCode;
    }

    public function hasEffectiveResult(): bool
    {
        return 0 !== bccomp($this->totalDiscount, '0.00', 2)
            || [] !== $this->giftSummary
            || [] !== $this->redeemSummary;
    }

    /**
     * @return numeric-string
     */
    public function getTotalDiscount(): string
    {
        return $this->totalDiscount;
    }

    public function shouldMarkPaid(): bool
    {
        return $this->shouldMarkPaid;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDetailPayload(): array
    {
        return [
            'coupon_discount' => (float) $this->totalDiscount,
            'coupon_breakdown' => $this->breakdown,
            'coupon_allocations' => $this->formatAggregatedAllocations(),
            'coupon_gift_items' => array_values($this->giftSummary),
            'coupon_redeem_items' => array_values($this->redeemSummary),
            'coupon_applied_codes' => array_values($this->appliedCodes),
            'coupon_should_mark_paid' => $this->shouldMarkPaid,
            'coupon_messages' => $this->messages,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * @param list<array{sku_id?: string, amount?: string}> $allocations
     */
    private function mergeAllocations(array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $skuId = $allocation['sku_id'] ?? '';
            $amount = $allocation['amount'] ?? '0.00';
            if (!is_string($skuId) || '' === $skuId) {
                continue;
            }

            $amountString = $this->normalizeAmount($amount);
            if (!isset($this->allocationSummary[$skuId])) {
                $this->allocationSummary[$skuId] = '0.00';
            }
            $this->allocationSummary[$skuId] = bcadd($this->allocationSummary[$skuId], $amountString, 2);
        }
    }

    /**
     * @param list<GiftItem> $giftItems
     */
    private function mergeGifts(array $giftItems): void
    {
        foreach ($giftItems as $gift) {
            $skuId = $gift->getSkuId();
            if (!isset($this->giftSummary[$skuId])) {
                $this->giftSummary[$skuId] = [
                    'sku_id' => $skuId,
                    'gtin' => $gift->getGtin() ?? '',
                    'quantity' => 0,
                    'name' => $gift->getName(),
                ];
            }
            $this->giftSummary[$skuId]['quantity'] += $gift->getQuantity();
        }
    }

    /**
     * @param list<RedeemItem> $items
     */
    private function mergeRedeemItems(array $items): void
    {
        foreach ($items as $item) {
            $skuId = $item->getSkuId();
            if (!isset($this->redeemSummary[$skuId])) {
                $this->redeemSummary[$skuId] = [
                    'sku_id' => $skuId,
                    'quantity' => 0,
                    'unit_price' => $item->getUnitPrice(),
                    'name' => $item->getName(),
                ];
            }
            $this->redeemSummary[$skuId]['quantity'] += $item->getQuantity();
            $this->redeemSummary[$skuId]['unit_price'] = $item->getUnitPrice();
        }
    }

    private function appendProducts(CouponApplicationResult $result): void
    {
        if ($result->hasGifts()) {
            foreach ($result->getGiftItems() as $gift) {
                $sku = $this->loadSkuSafely((string) $gift->getSkuId());

                $this->products[] = [
                    'skuId' => $gift->getSkuId(),
                    'spuId' => $sku?->getSpu()?->getId(),
                    'quantity' => $gift->getQuantity(),
                    'payablePrice' => '0.00',
                    'unitPrice' => '0.00',
                    'mainThumb' => $sku?->getMainThumb(),
                    'productName' => $sku?->getFullName() ?? $gift->getName() ?? '赠品',
                    'specifications' => $sku?->getDisplayAttribute(),
                    'labels' => ['coupon_gift'],
                    'isGift' => true,
                ];
            }
        }

        if ($result->hasRedeemItems()) {
            foreach ($result->getRedeemItems() as $item) {
                $sku = $this->loadSkuSafely($item->getSkuId());

                $this->products[] = [
                    'skuId' => $item->getSkuId(),
                    'spuId' => $sku?->getSpu()?->getId(),
                    'quantity' => $item->getQuantity(),
                    'payablePrice' => '0.00',
                    'unitPrice' => $item->getUnitPrice(),
                    'mainThumb' => $sku?->getMainThumb(),
                    'productName' => $sku?->getFullName() ?? $item->getName() ?? '兑换商品',
                    'specifications' => $sku?->getDisplayAttribute(),
                    'labels' => ['coupon_redeem'],
                    'isRedeem' => true,
                ];
            }
        }
    }

    /**
     * 安全地加载SKU信息，失败时返回null不影响主流程
     */
    private function loadSkuSafely(string $skuId): ?Sku
    {
        try {
            $sku = $this->skuLoader->loadSkuByIdentifier($skuId);

            // 确保返回的是 Sku 实体类型
            if ($sku instanceof Sku) {
                return $sku;
            }

            return null;
        } catch (\Throwable) {
            // 加载SKU失败时静默处理，不影响优惠券主流程
            return null;
        }
    }

    /**
     * @return list<array{sku_id: string, amount: numeric-string}>
     */
    private function formatAggregatedAllocations(): array
    {
        $result = [];
        foreach ($this->allocationSummary as $skuId => $amount) {
            $result[] = [
                'sku_id' => $skuId,
                'amount' => $amount,
            ];
        }

        return $result;
    }

    /**
     * @return numeric-string
     */
    private function normalizeAmount(string|float|int $value): string
    {
        if (is_string($value) && is_numeric($value)) {
            return sprintf('%.2f', (float) $value);
        }

        return sprintf('%.2f', (float) $value);
    }
}
