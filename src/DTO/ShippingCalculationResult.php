<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

readonly class ShippingCalculationResult
{
    /**
     * @param numeric-string $fee
     * @param numeric-string|null $freeShippingThreshold
     * @param ShippingCalculationDetail[] $details
     */
    public function __construct(
        /** @var numeric-string */
        public string $fee,
        /** @var numeric-string|null */
        public ?string $freeShippingThreshold = null,
        public bool $isFreeShipping = false,
        public bool $isDeliverable = true,
        /** @var ShippingCalculationDetail[] */
        public array $details = [],
        public ?string $errorMessage = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return null === $this->errorMessage && $this->isDeliverable;
    }

    public function hasError(): bool
    {
        return null !== $this->errorMessage;
    }

    public function isFree(): bool
    {
        return $this->isFreeShipping || 0 === bccomp($this->fee, '0.00', 3);
    }

    public function getTotalFee(): string
    {
        return $this->fee;
    }

    public function getFormattedFee(): string
    {
        if ($this->isFree()) {
            return '免运费';
        }

        return '¥' . $this->fee;
    }

    public function getFormattedFreeShippingThreshold(): ?string
    {
        if (null === $this->freeShippingThreshold) {
            return null;
        }

        return '¥' . $this->freeShippingThreshold;
    }

    public function hasDetails(): bool
    {
        return count($this->details) > 0;
    }

    /**
     * @return ShippingCalculationDetail[]
     */
    public function getDetailsByTemplateId(string $templateId): array
    {
        return array_filter($this->details, fn (ShippingCalculationDetail $detail) => $detail->templateId === $templateId);
    }
}
