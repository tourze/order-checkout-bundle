<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service\Coupon;

use Monolog\Attribute\WithMonologChannel;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderProduct;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\Provider\CouponProviderChain;
use Tourze\ProductCoreBundle\Entity\Sku;

/**
 * 处理结算流程中的优惠券附加逻辑。
 * 重构版本：使用CouponProviderChain而非直接操作Entity
 */
#[WithMonologChannel(channel: 'order_checkout')]
final class CouponWorkflowHelper
{
    /** @var string[] 已锁定的优惠券码 */
    private array $lockedCoupons = [];

    public function __construct(
        private readonly CouponProviderChain $providerChain,
        private readonly CouponExtraItemBuilder $extraItemBuilder,
        private readonly LoggerInterface $logger,
        private readonly ?CouponUsageLogger $couponUsageLogger = null,
    ) {
    }

    /**
     * @return array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}>
     */
    public function extractCouponExtraItems(PriceResult $priceResult): array
    {
        $details = $priceResult->getDetails();
        $this->logger->debug('开始合并兑换-赠送的商品数据', ['details' => $details]);

        $rawGifts = is_array($details['coupon_gift_items'] ?? null) ? $details['coupon_gift_items'] : [];
        $rawRedeems = is_array($details['coupon_redeem_items'] ?? null) ? $details['coupon_redeem_items'] : [];

        $skuMap = $this->extraItemBuilder->loadSkusForExtras($rawGifts, $rawRedeems);
        $this->logger->debug('开始合并兑换-赠送的商品数据', [
            'rawGifts' => $rawGifts,
            'rawRedeems' => $rawRedeems,
            'skuMap' => $skuMap,
        ]);

        return array_merge(
            $this->extraItemBuilder->buildGiftExtras($rawGifts, $skuMap),
            $this->extraItemBuilder->buildRedeemExtras($rawRedeems, $skuMap)
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
            if (!$this->tryLockCode($code, $user, $locked)) {
                $this->rollbackLockedCodes($locked, $user);
                throw new CheckoutException(sprintf('优惠券已失效'));
            }
            $locked[] = $code;
            $this->lockedCoupons[] = $code;
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
            $this->removeFromLockedList($code);
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

        $metadata = [
            'orderId' => $contract->getId(),
            'orderNumber' => $contract->getSn(),
        ];

        foreach ($codes as $code) {
            if (!$this->providerChain->redeem($code, $user, $metadata)) {
                throw new CheckoutException(sprintf('优惠券 %s 核销失败', $code));
            }
            $this->removeFromLockedList($code);
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
            $this->logSingleCouponUsage($code, $data, $skuMap, $userIdentifier, $contract);
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
            $detail = $this->buildAllocationDetail($allocation, $skuMap);
            if (null !== $detail) {
                $result[] = $detail;
            }
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
            $entry = $this->buildMapEntry($orderProduct);
            if (null !== $entry) {
                $map[$entry['key']] = $entry['value'];
            }
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
        return $this->extraItemBuilder->normalizePrice($price);
    }

    /**
     * @return string[]
     */
    public function getLockedCoupons(): array
    {
        return $this->lockedCoupons;
    }

    /**
     * @param string[] $alreadyLocked
     */
    private function tryLockCode(string $code, UserInterface $user, array $alreadyLocked): bool
    {
        return $this->providerChain->lock($code, $user);
    }

    /**
     * @param string[] $locked
     */
    private function rollbackLockedCodes(array $locked, UserInterface $user): void
    {
        foreach ($locked as $lockedCode) {
            $this->providerChain->unlock($lockedCode, $user);
        }
    }

    private function removeFromLockedList(string $code): void
    {
        $index = array_search($code, $this->lockedCoupons, true);
        if (false !== $index) {
            unset($this->lockedCoupons[$index]);
        }
    }

    /**
     * @param array<string, int> $skuMap
     */
    private function logSingleCouponUsage(
        string|int $code,
        mixed $data,
        array $skuMap,
        string $userIdentifier,
        Contract $contract
    ): void {
        if (!is_array($data)) {
            return;
        }

        $allocations = $this->prepareAllocationDetails($data['allocations'] ?? [], $skuMap);
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        $this->couponUsageLogger?->logUsage(
            (string) $code,
            (string) ($metadata['coupon_type'] ?? ''),
            $userIdentifier,
            $contract->getId() ?? 0,
            $contract->getSn(),
            $this->normalizePrice($data['discount'] ?? '0.00'),
            $allocations,
            $metadata
        );
    }

    /**
     * @param array<string, int> $skuMap
     * @return array{sku_id: string, amount: numeric-string, order_product_id: int|null}|null
     */
    private function buildAllocationDetail(mixed $allocation, array $skuMap): ?array
    {
        if (!is_array($allocation)) {
            return null;
        }

        $skuId = isset($allocation['sku_id']) ? (string) $allocation['sku_id'] : '';
        if ('' === $skuId) {
            return null;
        }

        $orderProductId = $skuMap[$skuId] ?? null;
        if (null === $orderProductId) {
            return null;
        }

        return [
            'sku_id' => $skuId,
            'amount' => $this->normalizePrice($allocation['amount'] ?? '0.00'),
            'order_product_id' => $orderProductId,
        ];
    }

    /**
     * @return array{key: string, value: int}|null
     */
    private function buildMapEntry(OrderProduct $orderProduct): ?array
    {
        $sku = $orderProduct->getSku();
        if (null === $sku) {
            return null;
        }

        $productId = $orderProduct->getId();
        if (null === $productId) {
            return null;
        }

        $skuKey = $this->resolveSkuIdentifier($sku);
        if ('' === $skuKey) {
            return null;
        }

        return ['key' => $skuKey, 'value' => $productId];
    }

    private function resolveSkuIdentifier(?Sku $sku): string
    {
        if (null === $sku) {
            return '';
        }

        return $sku->getId();
    }
}
