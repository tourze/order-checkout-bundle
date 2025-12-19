<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service\Order;

use Doctrine\ORM\EntityManagerInterface;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderPrice;
use OrderCoreBundle\Entity\OrderProduct;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\DTO\ShippingResult;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponWorkflowHelper;
use Tourze\ProductCoreBundle\Enum\PriceType;

/**
 * 订单价格构建器
 * 负责创建和管理订单价格实体
 */
final class OrderPriceBuilder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CouponWorkflowHelper $couponHelper,
    ) {
    }

    /**
     * @param OrderProduct[] $baseOrderProducts
     * @param OrderProduct[] $extraOrderProducts
     * @param array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}> $extraItems
     */
    public function createOrderPrices(
        Contract $contract,
        array $baseOrderProducts,
        array $extraOrderProducts,
        PriceResult $priceResult,
        ?ShippingResult $shippingResult = null,
        array $extraItems = []
    ): void {
        $this->createProductPrices($contract, $baseOrderProducts, $extraOrderProducts, $priceResult, $extraItems);
        $this->createShippingPriceIfNeeded($contract, $shippingResult);
    }

    /**
     * @param OrderProduct[] $baseOrderProducts
     * @param OrderProduct[] $extraOrderProducts
     * @param array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}> $extraItems
     */
    private function createProductPrices(
        Contract $contract,
        array $baseOrderProducts,
        array $extraOrderProducts,
        PriceResult $priceResult,
        array $extraItems
    ): void {
        $priceDetails = $priceResult->getDetails();
        $baseDetails = $priceDetails['base_price'] ?? [];
        $allocationMap = $this->buildAllocationMap($priceDetails['coupon_allocations'] ?? []);

        $productsBySkuId = $this->buildProductsBySkuIdMapping($baseOrderProducts);
        $this->processProductPriceDetails($contract, $productsBySkuId, $baseDetails, $allocationMap);

        $this->createExtraProductPrices($contract, $extraItems);
    }

    private function createShippingPriceIfNeeded(Contract $contract, ?ShippingResult $shippingResult): void
    {
        if (null === $shippingResult || $shippingResult->getShippingFee() <= 0) {
            return;
        }

        $shippingPrice = $this->buildShippingPrice($contract, $shippingResult);
        $contract->addPrice($shippingPrice);
        $this->entityManager->persist($shippingPrice);
    }

    private function buildShippingPrice(Contract $contract, ShippingResult $shippingResult): OrderPrice
    {
        $shippingPrice = new OrderPrice();
        $shippingPrice->setContract($contract);
        $shippingPrice->setCurrency('CNY');
        $shippingPrice->setType(PriceType::FREIGHT);
        $shippingPrice->setName('运费');
        $shippingPrice->setMoney(sprintf('%.2f', $shippingResult->getShippingFee()));
        $shippingPrice->setCanRefund(true);
        $shippingPrice->setPaid(false);
        $shippingPrice->setRefund(false);

        return $shippingPrice;
    }

    /**
     * @param OrderProduct[] $orderProducts
     * @return array<string, OrderProduct>
     */
    private function buildProductsBySkuIdMapping(array $orderProducts): array
    {
        $productsBySkuId = [];
        foreach ($orderProducts as $orderProduct) {
            $sku = $orderProduct->getSku();
            if (null !== $sku) {
                $productsBySkuId[(string) $sku->getId()] = $orderProduct;
            }
        }

        return $productsBySkuId;
    }

    /**
     * @param array<string, OrderProduct> $productsBySkuId
     * @param array<int, array<string, mixed>>|mixed $baseDetails
     * @param array<string, string> $allocationMap
     */
    private function processProductPriceDetails(
        Contract $contract,
        array $productsBySkuId,
        mixed $baseDetails,
        array $allocationMap
    ): void {
        if (!is_array($baseDetails)) {
            return;
        }

        foreach ($baseDetails as $detail) {
            if (is_array($detail)) {
                /** @var array<string, mixed> $detail */
                $this->createSingleProductPrice($contract, $productsBySkuId, $detail, $allocationMap);
            }
        }
    }

    /**
     * @param array<string, OrderProduct> $productsBySkuId
     * @param array<string, mixed> $detail
     * @param array<string, string> $allocationMap
     */
    private function createSingleProductPrice(
        Contract $contract,
        array $productsBySkuId,
        array $detail,
        array $allocationMap
    ): void {
        $skuId = $this->extractValidSkuId($detail);
        if ('' === $skuId) {
            return;
        }

        $orderProduct = $productsBySkuId[$skuId] ?? null;
        if (null === $orderProduct) {
            return;
        }

        $allocation = $this->normalizePrice($allocationMap[$skuId] ?? '0.00');
        $originalTotal = $this->normalizePrice($detail['total_price'] ?? 0);
        $quantity = isset($detail['quantity']) ? max(1, (int) $detail['quantity']) : 1;

        $saleDetail = $detail;
        $saleDetail['total_price'] = $originalTotal;
        $saleDetail['unit_price'] = bcdiv($originalTotal, sprintf('%.0f', $quantity), 2);

        $salePrice = $this->buildOrderPrice($contract, $orderProduct, $saleDetail);
        $contract->addPrice($salePrice);
        $this->entityManager->persist($salePrice);

        if (bccomp($allocation, '0.00', 2) > 0) {
            $couponPrice = $this->createCouponDiscountPrice($contract, $orderProduct, $allocation);
            $contract->addPrice($couponPrice);
            $this->entityManager->persist($couponPrice);
        }
    }

    /**
     * @param mixed $allocationDetails
     * @return array<string, string>
     */
    private function buildAllocationMap(mixed $allocationDetails): array
    {
        if (!is_array($allocationDetails)) {
            return [];
        }

        $map = [];
        foreach ($allocationDetails as $allocation) {
            if (!is_array($allocation)) {
                continue;
            }

            $skuId = isset($allocation['sku_id']) ? (string) $allocation['sku_id'] : '';
            if ('' === $skuId) {
                continue;
            }

            $amount = $this->normalizePrice($allocation['amount'] ?? '0.00');
            if (!isset($map[$skuId])) {
                $map[$skuId] = '0.00';
            }
            $map[$skuId] = bcadd($map[$skuId], $amount, 2);
        }

        return $map;
    }

    /**
     * @param array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}> $extraItems
     */
    private function createExtraProductPrices(Contract $contract, array $extraItems): void
    {
        foreach ($extraItems as $extra) {
            $orderProduct = $extra['order_product'] ?? null;
            if (!$orderProduct instanceof OrderProduct) {
                continue;
            }

            $detail = [
                'sku_id' => $this->couponHelper->resolveOrderProductSkuId($orderProduct),
                'total_price' => $extra['total_price'] ?? '0.00',
                'unit_price' => $extra['unit_price'] ?? '0.00',
            ];

            $productPrice = $this->buildOrderPrice($contract, $orderProduct, $detail);
            $contract->addPrice($productPrice);
            $this->entityManager->persist($productPrice);
        }
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function extractValidSkuId(array $detail): string
    {
        $skuIdValue = $detail['sku_id'] ?? null;

        if (null === $skuIdValue) {
            return '';
        }

        if (is_string($skuIdValue) || is_int($skuIdValue)) {
            return (string) $skuIdValue;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function buildOrderPrice(Contract $contract, OrderProduct $orderProduct, array $detail): OrderPrice
    {
        $productPrice = $this->createOrderPriceBase($contract, $orderProduct);
        $this->setProductPricing($productPrice, $detail);
        $this->setOrderPriceFlags($productPrice);

        return $productPrice;
    }

    private function createOrderPriceBase(Contract $contract, OrderProduct $orderProduct): OrderPrice
    {
        $productPrice = new OrderPrice();
        $productPrice->setContract($contract);
        $productPrice->setProduct($orderProduct);
        $productPrice->setCurrency('CNY');
        $productPrice->setType(PriceType::SALE);
        $productPrice->setName($orderProduct->getSpuTitle() ?? 'Unknown Product');

        return $productPrice;
    }

    private function setOrderPriceFlags(OrderPrice $productPrice): void
    {
        $productPrice->setCanRefund(true);
        $productPrice->setPaid(false);
        $productPrice->setRefund(false);
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function setProductPricing(OrderPrice $productPrice, array $detail): void
    {
        $totalPrice = $this->normalizePrice($detail['total_price'] ?? 0);
        $productPrice->setMoney($totalPrice);

        $unitPrice = $this->normalizePrice($detail['unit_price'] ?? 0);
        $productPrice->setUnitPrice($unitPrice);
    }

    private function createCouponDiscountPrice(Contract $contract, OrderProduct $orderProduct, string $discountAmount): OrderPrice
    {
        $couponPrice = new OrderPrice();
        $couponPrice->setContract($contract);
        $couponPrice->setProduct($orderProduct);
        $couponPrice->setCurrency('CNY');
        $couponPrice->setType(PriceType::COUPON_DISCOUNT);
        $couponPrice->setName('优惠券优惠');
        $couponPrice->setMoney('-' . $discountAmount);
        $couponPrice->setUnitPrice('0.00');
        $couponPrice->setCanRefund(true);
        $couponPrice->setPaid(false);
        $couponPrice->setRefund(false);

        return $couponPrice;
    }

    /**
     * @return numeric-string
     */
    public function normalizePrice(mixed $price): string
    {
        if (is_string($price) && is_numeric($price)) {
            return sprintf('%.2f', (float) $price);
        }

        return sprintf('%.2f', is_numeric($price) ? (float) $price : 0.0);
    }
}
