<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

readonly class ShippingCalculationItem
{
    /**
     * @param numeric-string $weight
     * @param numeric-string $price
     */
    public function __construct(
        public string $productId,
        public int $quantity,
        public string $weight,
        public string $price = '0.00',
        public ?string $shippingTemplateId = null,
    ) {
    }

    public function getTotalWeight(): string
    {
        return bcmul($this->weight, (string) $this->quantity, 3);
    }

    public function getTotalValue(): string
    {
        return bcmul($this->price, (string) $this->quantity, 2);
    }

    public function hasCustomShippingTemplate(): bool
    {
        return null !== $this->shippingTemplateId;
    }
}
