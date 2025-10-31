<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Calculator;

use Tourze\OrderCheckoutBundle\Contract\PriceCalculatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceCalculationItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\Exception\InvalidSkuTypeException;
use Tourze\OrderCheckoutBundle\Exception\SkuNotFoundException;
use Tourze\OrderCheckoutBundle\Exception\UnsupportedItemTypeException;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductServiceContracts\SkuLoaderInterface;

/**
 * 基础价格计算器
 * 负责计算商品的原价总和
 */
class BasePriceCalculator implements PriceCalculatorInterface
{
    public function __construct(
        private readonly SkuLoaderInterface $skuLoader,
    ) {
    }

    public function calculate(CalculationContext $context): PriceResult
    {
        /** @var numeric-string $totalPrice */
        $totalPrice = '0.00';
        $details = [];
        $products = [];

        foreach ($context->getItems() as $item) {
            $calculationItem = $this->normalizeItem($item);

            if (!$calculationItem->isSelected()) {
                continue;
            }

            // 确保被选中的商品有 SKU 数据
            $calculationItem = $this->ensureSkuLoaded($calculationItem);

            $unitPrice = $calculationItem->getEffectiveUnitPrice(); // 现在返回 string
            $quantity = $calculationItem->getQuantity();
            /** @var numeric-string $itemTotal */
            $itemTotal = $calculationItem->getSubtotal(); // 使用 BCMath 计算的小计
            $totalPrice = bcadd($totalPrice, $itemTotal, 2);

            $sku = $calculationItem->getSku();
            $details[] = [
                'type' => 'base_price',
                'sku_id' => $calculationItem->getSkuId(),
                'sku_code' => (null !== $sku) ? ($sku->getGtin() ?? $sku->getMpn() ?? '') : '',
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'total_price' => $itemTotal,
            ];

            // 收集产品信息
            if (null !== $sku) {
                $products[] = [
                    'skuId' => $calculationItem->getSkuId(),
                    'spuId' => $sku->getSpu()?->getId(),
                    'quantity' => $quantity,
                    'payablePrice' => $itemTotal,
                    'unitPrice' => $unitPrice,
                    'mainThumb' => $sku->getMainThumb(),
                    'productName' => $sku->getFullName(),
                    'specifications' => $sku->getDisplayAttribute(),
                ];
            }
        }

        return new PriceResult(
            originalPrice: $totalPrice,
            finalPrice: $totalPrice,
            discount: '0.00',
            details: ['base_price' => $details, 'base_total' => $totalPrice],
            products: $products
        );
    }

    /**
     * 将不同格式的商品数据规范化为 PriceCalculationItem
     */
    private function normalizeItem(mixed $item): PriceCalculationItem
    {
        // 如果已经是 PriceCalculationItem，直接返回
        if ($item instanceof PriceCalculationItem) {
            return $this->ensureSkuLoaded($item);
        }

        // 如果是 CheckoutItem，使用专门的转换方法
        if ($item instanceof CheckoutItem) {
            return new PriceCalculationItem(
                skuId: $item->getSkuId(),
                quantity: $item->getQuantity(),
                selected: $item->isSelected(),
                sku: $item->getSku()
            );
        }

        // 如果是 CartItem 等其他实体
        if (is_object($item) && method_exists($item, 'getSku') && method_exists($item, 'getQuantity')) {
            return PriceCalculationItem::fromCartItem($item);
        }

        // 如果是数组格式
        if (is_array($item)) {
            /** @var array{id?: int, skuId: int|string, quantity: int, selected?: bool} $item */
            return PriceCalculationItem::fromArray($item);
        }

        throw new UnsupportedItemTypeException(get_debug_type($item));
    }

    /**
     * 确保 PriceCalculationItem 包含 SKU 信息（始终加载以获取准确价格）
     */
    private function ensureSkuLoaded(PriceCalculationItem $item): PriceCalculationItem
    {
        // 如果已经有 SKU 信息，直接返回
        if (null !== $item->getSku()) {
            return $item;
        }

        // 总是加载 SKU 以获取最新价格
        $sku = $this->skuLoader->loadSkuByIdentifier((string) $item->getSkuId());
        if (null === $sku) {
            throw new SkuNotFoundException((string) $item->getSkuId());
        }

        // 类型转换：SkuLoaderInterface 返回的是接口类型，需要转换为实体类型
        if (!$sku instanceof Sku) {
            throw new InvalidSkuTypeException(get_class($sku));
        }

        return $item->withSku($sku);
    }

    public function supports(CalculationContext $context): bool
    {
        // 基础价格计算器始终支持
        return count($context->getItems()) > 0;
    }

    public function getPriority(): int
    {
        // 最高优先级，首先计算基础价格
        return 1000;
    }

    public function getType(): string
    {
        return 'base_price';
    }
}
