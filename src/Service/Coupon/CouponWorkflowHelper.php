<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service\Coupon;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponUsageLogger;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\OrderCheckoutBundle\Provider\CouponProviderChain;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Service\SkuServiceInterface;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderProduct;

/**
 * 处理结算流程中的优惠券附加逻辑。
 * 重构版本：使用CouponProviderChain而非直接操作Entity
 */
class CouponWorkflowHelper
{
    /** @var string[] 已锁定的优惠券码 */
    private array $lockedCoupons = [];

    public function __construct(
        private readonly CouponProviderChain $providerChain,
        private readonly SkuServiceInterface $skuService,
        private readonly ?CouponUsageLogger $couponUsageLogger = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}>
     */
    public function extractCouponExtraItems(PriceResult $priceResult): array
    {
        $details = $priceResult->getDetails();
        $this->logger->debug('开始合并兑换-赠送的商品数据',[
            'details' => $details
        ]);
        $rawGifts = is_array($details['coupon_gift_items'] ?? null) ? $details['coupon_gift_items'] : [];
        $rawRedeems = is_array($details['coupon_redeem_items'] ?? null) ? $details['coupon_redeem_items'] : [];

        $skuMap = $this->loadSkusByIds($this->collectExtraSkuIds($rawGifts, $rawRedeems));
        $this->logger->debug('开始合并兑换-赠送的商品数据',[
            'rawGifts' => $rawGifts,
            'rawRedeems' => $rawRedeems,
            'skuMap' => $skuMap,
        ]);
        return array_merge(
            $this->buildGiftExtras($rawGifts, $skuMap),
            $this->buildRedeemExtras($rawRedeems, $skuMap)
        );
    }

    /**
     * @param CheckoutItem[] $baseItems
     * @param array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}> $extraItems
     * @return CheckoutItem[]
     */
    public function mergeCheckoutItems(array $baseItems, array $extraItems): array
    {
        $items = $baseItems;
        foreach ($extraItems as $extra) {
            $items[] = $extra['item'];
        }

        return $items;
    }

    /**
     * @return string[]
     */
    public function extractCouponCodes(PriceResult $priceResult): array
    {
        $codes = $priceResult->getDetail('coupon_applied_codes', []);
        if (!is_array($codes)) {
            return [];
        }

        $result = [];
        foreach ($codes as $code) {
            if (is_string($code) && '' !== $code) {
                $result[] = $code;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param string[] $couponCodes
     * @return string[] 成功锁定的优惠券码
     */
    public function lockCouponCodes(UserInterface $user, array $couponCodes): array
    {
        $locked = [];
        foreach ($couponCodes as $code) {
            if ($this->providerChain->lock($code, $user)) {
                $locked[] = $code;
                $this->lockedCoupons[] = $code;
            } else {
                // 锁定失败，解锁已锁定的
                foreach ($locked as $lockedCode) {
                    $this->providerChain->unlock($lockedCode, $user);
                }
                throw new CheckoutException(sprintf('优惠券 %s 无法锁定', $code));
            }
        }

        return $locked;
    }

    /**
     * @param string[] $codes
     */
    public function unlockCouponCodes(array $codes, UserInterface $user): void
    {
        foreach ($codes as $code) {
            $this->providerChain->unlock($code, $user);
            
            // 从已锁定列表中移除
            $index = array_search($code, $this->lockedCoupons, true);
            if (false !== $index) {
                unset($this->lockedCoupons[$index]);
            }
        }
    }

    /**
     * @param string[] $codes
     */
    public function redeemCouponCodes(array $codes, Contract $contract): void
    {
        $user = $contract->getUser();
        if (null === $user) {
            throw new CheckoutException('订单用户信息无效，无法核销优惠券');
        }

        foreach ($codes as $code) {
            $metadata = [
                'orderId' => $contract->getId(),
                'orderNumber' => $contract->getSn(),
            ];
            
            if (!$this->providerChain->redeem($code, $user, $metadata)) {
                throw new CheckoutException(sprintf('优惠券 %s 核销失败', $code));
            }
            
            // 从已锁定列表中移除
            $index = array_search($code, $this->lockedCoupons, true);
            if (false !== $index) {
                unset($this->lockedCoupons[$index]);
            }
        }
    }

    public function logCouponUsage(CalculationContext $context, Contract $contract, PriceResult $priceResult): void
    {
        if (null === $this->couponUsageLogger) {
            return;
        }

        $breakdown = $priceResult->getDetail('coupon_breakdown', []);
        if (!is_array($breakdown) || [] === $breakdown) {
            return;
        }

        $skuMap = $this->buildSkuOrderProductMap($contract);
        $userIdentifier = $context->getUser()->getUserIdentifier();

        foreach ($breakdown as $code => $data) {
            if (!is_array($data)) {
                continue;
            }

            $allocations = $this->prepareAllocationDetails($data['allocations'] ?? [], $skuMap);
            $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
            $this->couponUsageLogger->logUsage(
                (string) $code,
                (string) ($metadata['coupon_type'] ?? ''),
                $userIdentifier,
                $contract->getId() ?? 0,
                $contract->getSn(),
                $this->formatDiscount($data['discount'] ?? '0.00'),
                $allocations,
                $metadata
            );
        }
    }

    /**
     * @param mixed $allocations
     * @param array<string, int> $skuMap
     * @return list<array{sku_id: string, amount: numeric-string, order_product_id: int|null}>
     */
    public function prepareAllocationDetails(mixed $allocations, array $skuMap): array
    {
        if (!is_array($allocations)) {
            return [];
        }

        $result = [];
        foreach ($allocations as $allocation) {
            if (!is_array($allocation)) {
                continue;
            }
            $skuId = isset($allocation['sku_id']) ? (string) $allocation['sku_id'] : '';
            if ('' === $skuId) {
                continue;
            }

            $orderProductId = $skuMap[$skuId] ?? null;
            if (null === $orderProductId) {
                continue;
            }

            $result[] = [
                'sku_id' => $skuId,
                'amount' => $this->normalizePrice($allocation['amount'] ?? '0.00'),
                'order_product_id' => $orderProductId,
            ];
        }

        return $result;
    }

    public function describeExtraItem(string $type): string
    {
        return match ($type) {
            'coupon_gift' => '优惠券赠品',
            'coupon_redeem' => '兑换券赠品',
            default => '优惠券附加项',
        };
    }

    /**
     * @return array<string, int>
     */
    public function buildSkuOrderProductMap(Contract $contract): array
    {
        $map = [];
        foreach ($contract->getProducts() as $orderProduct) {
            $sku = $orderProduct->getSku();
            if (null === $sku) {
                continue;
            }
            $productId = $orderProduct->getId();
            if (null === $productId) {
                continue;
            }
            $skuKey = $this->resolveSkuIdentifier($sku);
            if ('' === $skuKey) {
                continue;
            }
            $map[$skuKey] = $productId;
        }

        return $map;
    }

    public function resolveOrderProductSkuId(OrderProduct $orderProduct): string
    {
        return $this->resolveSkuIdentifier($orderProduct->getSku());
    }

    /**
     * @return numeric-string
     */
    public function normalizePrice(mixed $price): string
    {
        if (is_string($price) && is_numeric($price)) {
            return sprintf('%.2f', (float) $price);
        }

        if (is_numeric($price)) {
            return sprintf('%.2f', (float) $price);
        }

        return '0.00';
    }

    private function formatDiscount(mixed $discount): string
    {
        if (is_string($discount) && is_numeric($discount)) {
            return sprintf('%.2f', (float) $discount);
        }

        if (is_numeric($discount)) {
            return sprintf('%.2f', (float) $discount);
        }

        return '0.00';
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
            if (is_array($gift) && isset($gift['sku_id'])) {
                $skuIds[] = (int) $gift['sku_id'];
            }
        }
        foreach ($rawRedeems as $redeem) {
            if (is_array($redeem) && isset($redeem['sku_id'])) {
                $skuIds[] = (int) $redeem['sku_id'];
            }
        }

        return array_values(array_unique(array_filter($skuIds, static fn (int $skuId): bool => $skuId > 0)));
    }

    /**
     * @param array<int, array<mixed>> $rawGifts
     * @param array<int, Sku> $skuMap
     * @return array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, order_product?: OrderProduct|null}>
     */
    private function buildGiftExtras(array $rawGifts, array $skuMap): array
    {
        $extras = [];
        foreach ($rawGifts as $gift) {
            if (!is_array($gift)) {
                continue;
            }
            $skuId = isset($gift['sku_id']) ? (int) $gift['sku_id'] : 0;
            $quantity = isset($gift['quantity']) ? max(0, (int) $gift['quantity']) : 0;
            if ($skuId <= 0 || $quantity <= 0) {
                continue;
            }

            $sku = $skuMap[$skuId] ?? null;
            if (!$sku instanceof Sku) {
                error_log(sprintf('CouponWorkflowHelper: 优惠券赠品 SKU ID %d 未找到或无效', $skuId));
                throw new CheckoutException(sprintf('优惠券赠品 %d 不存在或已下架', $skuId));
            }

            $extras[] = [
                'item' => new CheckoutItem((string) $skuId, $quantity, true, $sku),
                'type' => 'coupon_gift',
                'unit_price' => '0.00',
                'total_price' => '0.00',
            ];
        }

        return $extras;
    }

    /**
     * @param array<int, array<mixed>> $rawRedeems
     * @param array<int, Sku> $skuMap
     * @return array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}>
     */
    private function buildRedeemExtras(array $rawRedeems, array $skuMap): array
    {
        $extras = [];
        foreach ($rawRedeems as $redeem) {
            if (!is_array($redeem)) {
                continue;
            }
            $skuId = isset($redeem['sku_id']) ? (int) $redeem['sku_id'] : 0;
            $quantity = isset($redeem['quantity']) ? max(0, (int) $redeem['quantity']) : 0;
            if ($skuId <= 0 || $quantity <= 0) {
                continue;
            }

            $sku = $skuMap[$skuId] ?? null;
            if (!$sku instanceof Sku) {
                error_log(sprintf('CouponWorkflowHelper: 兑换券商品 SKU ID %d 未找到或无效', $skuId));
                throw new CheckoutException(sprintf('兑换券商品 %d 不存在或已下架', $skuId));
            }

            $extras[] = [
                'item' => new CheckoutItem((string) $skuId, $quantity, true, $sku),
                'type' => 'coupon_redeem',
                'unit_price' => '0.00',
                'total_price' => '0.00',
                'reference_unit_price' => $this->normalizePrice($redeem['unit_price'] ?? '0.00'),
            ];
        }

        return $extras;
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

        // SKU 服务根据 ID 查找的方法，转换为字符串数组
        /** @var string[] $skuIdStrings */
        $skuIdStrings = array_map('strval', $skuIds);
        $skus = $this->skuService->findByIds($skuIdStrings);

        $map = [];
        foreach ($skus as $sku) {
            if ($sku instanceof Sku) {
                $skuId = $sku->getId();
                if (is_numeric($skuId) && (int)$skuId > 0) {
                    $map[(int)$skuId] = $sku;
                }
            }
        }

        // 记录未找到的 SKU ID
        $notFoundSkuIds = array_diff($skuIds, array_map('intval', array_keys($map)));
        if ([] !== $notFoundSkuIds) {
            error_log('CouponWorkflowHelper: 未找到以下 SKU ID 对应的 SKU: ' . implode(', ', $notFoundSkuIds));
        }

        return $map;
    }

    private function resolveSkuIdentifier(?Sku $sku): string
    {
        if (null === $sku) {
            return '';
        }

        return $sku->getId();
    }

    /**
     * 获取当前已锁定的优惠券码
     *
     * @return string[]
     */
    public function getLockedCoupons(): array
    {
        return $this->lockedCoupons;
    }
}
