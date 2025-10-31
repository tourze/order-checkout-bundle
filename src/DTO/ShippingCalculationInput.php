<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

readonly class ShippingCalculationInput
{
    /**
     * @param ShippingCalculationItem[] $items
     */
    public function __construct(
        public string $addressId,
        public array $items,
    ) {
    }

    public function getTotalWeight(): string
    {
        $totalWeight = '0.000';

        foreach ($this->items as $item) {
            $itemWeight = bcmul($item->weight, (string) $item->quantity, 3);
            $totalWeight = bcadd($totalWeight, $itemWeight, 3);
        }

        return $totalWeight;
    }

    public function getTotalQuantity(): int
    {
        $totalQuantity = 0;

        foreach ($this->items as $item) {
            $totalQuantity += $item->quantity;
        }

        return $totalQuantity;
    }

    public function getTotalValue(): string
    {
        $totalValue = '0.00';

        foreach ($this->items as $item) {
            $itemValue = bcmul($item->price, (string) $item->quantity, 2);
            $totalValue = bcadd($totalValue, $itemValue, 2);
        }

        return $totalValue;
    }

    public function hasItems(): bool
    {
        return count($this->items) > 0;
    }

    /**
     * @return string[]
     */
    public function getProductIds(): array
    {
        return array_map(fn (ShippingCalculationItem $item) => $item->productId, $this->items);
    }

    public function getItemByProductId(string $productId): ?ShippingCalculationItem
    {
        foreach ($this->items as $item) {
            if ($item->productId === $productId) {
                return $item;
            }
        }

        return null;
    }
}
