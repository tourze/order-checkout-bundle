<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service\Order;

use Doctrine\ORM\EntityManagerInterface;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderProduct;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponWorkflowHelper;

/**
 * 订单商品构建器
 * 负责创建和管理订单商品实体
 */
final class OrderProductBuilder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CouponWorkflowHelper $couponHelper,
    ) {
    }

    /**
     * @param CheckoutItem[] $items
     * @param array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}> $extraItems
     * @return array{base: array<OrderProduct>, extra: array<OrderProduct>, extraItems: array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}>}
     */
    public function createOrderProducts(Contract $contract, array $items, array $extraItems, ?\Tourze\OrderCheckoutBundle\DTO\CalculationContext $context = null): array
    {
        $baseProducts = $this->createBaseProducts($contract, $items, $context);
        [$extraProducts, $updatedExtraItems] = $this->createExtraProducts($contract, $extraItems, $context);

        return [
            'base' => $baseProducts,
            'extra' => $extraProducts,
            'extraItems' => $updatedExtraItems,
        ];
    }

    /**
     * @param CheckoutItem[] $items
     * @return OrderProduct[]
     */
    private function createBaseProducts(Contract $contract, array $items, ?\Tourze\OrderCheckoutBundle\DTO\CalculationContext $context = null): array
    {
        $baseProducts = [];
        foreach ($items as $item) {
            $orderProduct = $this->buildOrderProduct($contract, $item, $context);
            $orderProduct->setIsGift(false);
            $orderProduct->setSource('normal');
            $contract->addProduct($orderProduct);
            $this->entityManager->persist($orderProduct);
            $baseProducts[] = $orderProduct;
        }

        return $baseProducts;
    }

    /**
     * @param array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}> $extraItems
     * @return array{0: OrderProduct[], 1: array<int, array{item: CheckoutItem, type: string, unit_price: string, total_price: string, reference_unit_price?: string, order_product?: OrderProduct|null}>}
     */
    private function createExtraProducts(Contract $contract, array $extraItems, ?\Tourze\OrderCheckoutBundle\DTO\CalculationContext $context = null): array
    {
        $extraProducts = [];
        foreach ($extraItems as $index => $extra) {
            $checkoutItem = $extra['item'] ?? null;
            if (!$checkoutItem instanceof CheckoutItem) {
                continue;
            }

            $orderProduct = $this->buildOrderProduct($contract, $checkoutItem, $context);
            $type = $extra['type'] ?? 'coupon';
            $orderProduct->setRemark($this->couponHelper->describeExtraItem($type));
            $orderProduct->setSource($type);
            $orderProduct->setIsGift(in_array($type, ['coupon_gift', 'coupon_redeem'], true));
            $contract->addProduct($orderProduct);
            $this->entityManager->persist($orderProduct);
            $extraItems[$index]['order_product'] = $orderProduct;
            $extraProducts[] = $orderProduct;
        }

        return [$extraProducts, $extraItems];
    }

    private function buildOrderProduct(Contract $contract, CheckoutItem $item, ?\Tourze\OrderCheckoutBundle\DTO\CalculationContext $context = null): OrderProduct
    {
        $orderProduct = $this->createOrderProductBase($contract, $item);
        $this->setOrderProductDetails($orderProduct, $item, $context);

        return $orderProduct;
    }

    private function createOrderProductBase(Contract $contract, CheckoutItem $item): OrderProduct
    {
        $orderProduct = new OrderProduct();
        $orderProduct->setContract($contract);
        $orderProduct->setSku($item->getSku());
        $orderProduct->setValid(true);

        return $orderProduct;
    }

    private function setOrderProductDetails(OrderProduct $orderProduct, CheckoutItem $item, ?\Tourze\OrderCheckoutBundle\DTO\CalculationContext $context = null): void
    {
        $sku = $item->getSku();
        $orderProduct->setSpu($sku?->getSpu());
        $orderProduct->setSpuTitle($sku?->getSpu()?->getTitle() ?? '');
        $orderProduct->setQuantity($item->getQuantity());

        if (null !== $sku) {
            $paymentMode = $context?->getMetadataValue('paymentMode', 'CASH_ONLY') ?? 'CASH_ONLY';
            $useIntegralAmount = $context?->getMetadataValue('useIntegralAmount', 0) ?? 0;
            $integralPrice = $this->calculateIntegralPrice($sku, $item->getQuantity(), $paymentMode, $useIntegralAmount);
            $orderProduct->setIntegralPrice($integralPrice);
        }
    }

    private function calculateIntegralPrice(\Tourze\ProductCoreBundle\Entity\Sku $sku, int $quantity, string $paymentMode, int $useIntegralAmount): int
    {
        $integralPrice = $sku->getIntegralPrice() ?? 0;

        return match ($paymentMode) {
            'INTEGRAL_ONLY' => $integralPrice,
            'MIXED' => $this->calculateMixedIntegralPrice($integralPrice, $quantity, $useIntegralAmount),
            default => 0,
        };
    }

    private function calculateMixedIntegralPrice(int $integralPrice, int $quantity, int $useIntegralAmount): int
    {
        if ($integralPrice <= 0 || $useIntegralAmount <= 0) {
            return 0;
        }

        $totalIntegralNeeded = $integralPrice * $quantity;
        $integralUsedForThisItem = min($totalIntegralNeeded, $useIntegralAmount);
        $integralRatio = $totalIntegralNeeded > 0 ? ($integralUsedForThisItem / $totalIntegralNeeded) : 0;

        return (int)($integralPrice * $integralRatio);
    }
}
