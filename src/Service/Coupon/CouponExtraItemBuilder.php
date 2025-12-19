<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service\Coupon;

use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Service\SkuServiceInterface;

/**
 * 优惠券额外商品构建器
 * 负责处理赠品和兑换商品的 SKU 加载与构建
 */
final class CouponExtraItemBuilder
{
    public function __construct(
        private readonly SkuServiceInterface $skuService,
    ) {
    }

    /**
     * @param array<int, array<mixed>> $rawGifts
     * @param array<int, array<mixed>> $rawRedeems
     * @return array<int|string, Sku>
     */
    public function loadSkusForExtras(array $rawGifts, array $rawRedeems): array
    {
        $skuIds = $this->collectExtraSkuIds($rawGifts, $rawRedeems);

        return $this->loadSkusByIds($skuIds);
    }

    /**
     * @param array<int, array<mixed>> $rawGifts
     * @param array<int, Sku> $skuMap
     * @return array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string}>
     */
    public function buildGiftExtras(array $rawGifts, array $skuMap): array
    {
        $extras = [];
        foreach ($rawGifts as $gift) {
            $extra = $this->buildSingleGiftExtra($gift, $skuMap);
            if (null !== $extra) {
                $extras[] = $extra;
            }
        }

        return $extras;
    }

    /**
     * @param array<int, array<mixed>> $rawRedeems
     * @param array<int, Sku> $skuMap
     * @return array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string}>
     */
    public function buildRedeemExtras(array $rawRedeems, array $skuMap): array
    {
        $extras = [];
        foreach ($rawRedeems as $redeem) {
            $extra = $this->buildSingleRedeemExtra($redeem, $skuMap);
            if (null !== $extra) {
                $extras[] = $extra;
            }
        }

        return $extras;
    }

    /**
     * @param array<int, array<mixed>> $rawGifts
     * @param array<int, array<mixed>> $rawRedeems
     * @return int[]
     */
    private function collectExtraSkuIds(array $rawGifts, array $rawRedeems): array
    {
        $skuIds = [];

        foreach ($rawGifts as $gift) {
            $skuId = $this->extractSkuIdFromItem($gift);
            if ($skuId > 0) {
                $skuIds[] = $skuId;
            }
        }

        foreach ($rawRedeems as $redeem) {
            $skuId = $this->extractSkuIdFromItem($redeem);
            if ($skuId > 0) {
                $skuIds[] = $skuId;
            }
        }

        return array_values(array_unique($skuIds));
    }

    private function extractSkuIdFromItem(mixed $item): int
    {
        if (!is_array($item) || !isset($item['sku_id'])) {
            return 0;
        }

        return (int) $item['sku_id'];
    }

    /**
     * @param int[] $skuIds
     * @return array<int|string, Sku>
     */
    private function loadSkusByIds(array $skuIds): array
    {
        if ([] === $skuIds) {
            return [];
        }

        $skuIdStrings = array_map('strval', $skuIds);
        $skus = $this->skuService->findByIds($skuIdStrings);

        $map = [];
        foreach ($skus as $sku) {
            if ($sku instanceof Sku) {
                $skuId = $sku->getId();
                if (is_numeric($skuId) && (int) $skuId > 0) {
                    $map[(int) $skuId] = $sku;
                }
            }
        }

        $this->logNotFoundSkus($skuIds, $map);

        return $map;
    }

    /**
     * @param int[] $requestedIds
     * @param array<int|string, Sku> $foundMap
     */
    private function logNotFoundSkus(array $requestedIds, array $foundMap): void
    {
        $foundIds = array_map('intval', array_keys($foundMap));
        $notFoundSkuIds = array_diff($requestedIds, $foundIds);

        if ([] !== $notFoundSkuIds) {
            error_log('CouponExtraItemBuilder: 未找到以下 SKU ID 对应的 SKU: ' . implode(', ', $notFoundSkuIds));
        }
    }

    /**
     * @param array<int, Sku> $skuMap
     * @return array{item: CheckoutItem, type: string, unit_price: string, total_price: string}|null
     */
    private function buildSingleGiftExtra(mixed $gift, array $skuMap): ?array
    {
        if (!is_array($gift)) {
            return null;
        }

        $skuId = isset($gift['sku_id']) ? (int) $gift['sku_id'] : 0;
        $quantity = isset($gift['quantity']) ? max(0, (int) $gift['quantity']) : 0;

        if ($skuId <= 0 || $quantity <= 0) {
            return null;
        }

        $sku = $this->requireSku($skuMap, $skuId, '优惠券赠品');

        return [
            'item' => new CheckoutItem((string) $skuId, $quantity, true, $sku),
            'type' => 'coupon_gift',
            'unit_price' => '0.00',
            'total_price' => '0.00',
        ];
    }

    /**
     * @param array<int, Sku> $skuMap
     * @return array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price: string}|null
     */
    private function buildSingleRedeemExtra(mixed $redeem, array $skuMap): ?array
    {
        if (!is_array($redeem)) {
            return null;
        }

        $skuId = isset($redeem['sku_id']) ? (int) $redeem['sku_id'] : 0;
        $quantity = isset($redeem['quantity']) ? max(0, (int) $redeem['quantity']) : 0;

        if ($skuId <= 0 || $quantity <= 0) {
            return null;
        }

        $sku = $this->requireSku($skuMap, $skuId, '兑换券商品');

        return [
            'item' => new CheckoutItem((string) $skuId, $quantity, true, $sku),
            'type' => 'coupon_redeem',
            'unit_price' => '0.00',
            'total_price' => '0.00',
            'reference_unit_price' => $this->normalizePrice($redeem['unit_price'] ?? '0.00'),
        ];
    }

    /**
     * @param array<int, Sku> $skuMap
     */
    private function requireSku(array $skuMap, int $skuId, string $itemType): Sku
    {
        $sku = $skuMap[$skuId] ?? null;

        if (!$sku instanceof Sku) {
            error_log(sprintf('CouponExtraItemBuilder: %s SKU ID %d 未找到或无效', $itemType, $skuId));
            throw new CheckoutException(sprintf('%s %d 不存在或已下架', $itemType, $skuId));
        }

        return $sku;
    }

    /**
     * @return numeric-string
     */
    public function normalizePrice(mixed $price): string
    {
        if (is_numeric($price)) {
            return sprintf('%.2f', (float) $price);
        }

        return '0.00';
    }
}
