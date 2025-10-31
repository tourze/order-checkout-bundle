<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

readonly class ShippingCalculationDetail
{
    public function __construct(
        public string $templateId,
        public string $templateName,
        public string $chargeType,
        public string $unitValue,
        public string $fee,
        public bool $isFreeShipping = false,
        public ?string $areaName = null,
        public ?string $calculation = null,
    ) {
    }

    public function getFormattedUnitValue(): string
    {
        return match ($this->chargeType) {
            'weight' => $this->unitValue . 'kg',
            'quantity' => $this->unitValue . '件',
            'volume' => $this->unitValue . 'm³',
            default => $this->unitValue,
        };
    }

    public function getFormattedFee(): string
    {
        if ($this->isFreeShipping) {
            return '免运费';
        }

        return '¥' . $this->fee;
    }

    public function hasAreaSpecificRates(): bool
    {
        return null !== $this->areaName;
    }

    public function getCalculationDescription(): string
    {
        if (null !== $this->calculation) {
            return $this->calculation;
        }

        if ($this->isFreeShipping) {
            return '满足包邮条件';
        }

        $chargeTypeText = match ($this->chargeType) {
            'weight' => '重量',
            'quantity' => '件数',
            'volume' => '体积',
            default => '计费单位',
        };

        return sprintf('按%s计费：%s', $chargeTypeText, $this->getFormattedUnitValue());
    }
}
