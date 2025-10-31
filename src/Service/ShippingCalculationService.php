<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Tourze\OrderCheckoutBundle\Contract\AddressResolverInterface;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationDetail;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationInput;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationItem;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationResult;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplateArea;
use Tourze\OrderCheckoutBundle\Enum\ChargeType;
use Tourze\OrderCheckoutBundle\Repository\ShippingTemplateAreaRepository;
use Tourze\OrderCheckoutBundle\Repository\ShippingTemplateRepository;

class ShippingCalculationService
{
    public function __construct(
        private readonly ShippingTemplateRepository $shippingTemplateRepository,
        private readonly ShippingTemplateAreaRepository $shippingTemplateAreaRepository,
        private readonly AddressResolverInterface $addressResolver,
    ) {
    }

    public function calculate(ShippingCalculationInput $input): ShippingCalculationResult
    {
        $validationResult = $this->validateInput($input);
        if (null !== $validationResult) {
            return $validationResult;
        }

        $addressData = $this->resolveAndValidateAddress($input->addressId);
        if (!is_array($addressData)) {
            return $addressData;
        }

        return $this->calculateShippingFees($input, $addressData);
    }

    private function validateInput(ShippingCalculationInput $input): ?ShippingCalculationResult
    {
        if (!$input->hasItems()) {
            return new ShippingCalculationResult(
                fee: '0.00',
                errorMessage: '商品列表为空',
                isDeliverable: false,
            );
        }

        return null;
    }

    /**
     * @return array<string, string>|ShippingCalculationResult
     */
    private function resolveAndValidateAddress(string $addressId): array|ShippingCalculationResult
    {
        $addressData = $this->addressResolver->resolveAddress($addressId);
        if (null === $addressData) {
            return new ShippingCalculationResult(
                fee: '0.00',
                errorMessage: '收货地址不存在',
                isDeliverable: false,
            );
        }

        return $addressData;
    }

    /**
     * @param array<string, string> $addressData
     */
    private function calculateShippingFees(ShippingCalculationInput $input, array $addressData): ShippingCalculationResult
    {
        $templateGroups = $this->groupItemsByTemplate($input);
        $details = [];
        $totalFee = '0.00';
        $minFreeShippingThreshold = null;

        foreach ($templateGroups as $templateId => $items) {
            $groupResult = $this->processTemplateGroup($templateId, $items, $addressData);
            if ($groupResult->hasError()) {
                return $groupResult;
            }

            $details = array_merge($details, $groupResult->details);
            $totalFee = bcadd($totalFee, $groupResult->fee, 2);
            $minFreeShippingThreshold = $this->updateMinFreeShippingThreshold($minFreeShippingThreshold, $groupResult->freeShippingThreshold);
        }

        return $this->applyFreeShippingIfEligible($input, $details, $totalFee, $minFreeShippingThreshold);
    }

    /**
     * @param array<ShippingCalculationItem> $items
     * @param array<string, string> $addressData
     */
    private function processTemplateGroup(string $templateId, array $items, array $addressData): ShippingCalculationResult
    {
        $template = $this->getTemplate($templateId);
        if (null === $template) {
            return new ShippingCalculationResult(
                fee: '0.00',
                errorMessage: '运费模板不存在',
                isDeliverable: false,
            );
        }

        if (!$this->isLocationDeliverable($template, $addressData)) {
            return new ShippingCalculationResult(
                fee: '0.00',
                errorMessage: '该地区不支持配送',
                isDeliverable: false,
            );
        }

        return $this->calculateForTemplate($template, $items, $addressData);
    }

    private function updateMinFreeShippingThreshold(?string $current, ?string $new): ?string
    {
        if (null === $new) {
            return $current;
        }

        if (null === $current || bccomp(sprintf('%.2f', $new), sprintf('%.2f', $current), 2) < 0) {
            return $new;
        }

        return $current;
    }

    /**
     * @param ShippingCalculationDetail[] $details
     * @param numeric-string $totalFee
     * @param string|null $minFreeShippingThreshold
     */
    private function applyFreeShippingIfEligible(ShippingCalculationInput $input, array $details, string $totalFee, ?string $minFreeShippingThreshold): ShippingCalculationResult
    {
        $orderValue = $input->getTotalValue();
        /** @var numeric-string $orderValueStr */
        $orderValueStr = sprintf('%.2f', $orderValue);
        $isFreeShipping = null !== $minFreeShippingThreshold && is_numeric($minFreeShippingThreshold) && bccomp($orderValueStr, $minFreeShippingThreshold, 2) >= 0;

        if ($isFreeShipping) {
            $details = $this->markDetailsAsFreeShipping($details);
            $totalFee = '0.00';
        }

        return new ShippingCalculationResult(
            fee: $totalFee,
            freeShippingThreshold: null !== $minFreeShippingThreshold && is_numeric($minFreeShippingThreshold) ? $minFreeShippingThreshold : null,
            isFreeShipping: $isFreeShipping,
            isDeliverable: true,
            details: $details,
        );
    }

    /**
     * @param ShippingCalculationDetail[] $details
     * @return ShippingCalculationDetail[]
     */
    private function markDetailsAsFreeShipping(array $details): array
    {
        $result = [];
        foreach ($details as $detail) {
            $result[] = new ShippingCalculationDetail(
                templateId: $detail->templateId,
                templateName: $detail->templateName,
                chargeType: $detail->chargeType,
                unitValue: $detail->unitValue,
                fee: '0.00',
                isFreeShipping: true,
                areaName: $detail->areaName,
                calculation: '满足包邮条件',
            );
        }

        return $result;
    }

    /**
     * @return array<string, array<ShippingCalculationItem>>
     */
    private function groupItemsByTemplate(ShippingCalculationInput $input): array
    {
        $groups = [];

        foreach ($input->items as $item) {
            $templateId = $item->shippingTemplateId ?? 'default';
            $groups[$templateId][] = $item;
        }

        return $groups;
    }

    private function getTemplate(?string $templateId): ?ShippingTemplate
    {
        if (null === $templateId || 'default' === $templateId) {
            return $this->shippingTemplateRepository->findDefault();
        }

        return $this->shippingTemplateRepository->find($templateId);
    }

    /**
     * @param array<string, string> $addressData
     */
    private function isLocationDeliverable(ShippingTemplate $template, array $addressData): bool
    {
        return $this->shippingTemplateAreaRepository->isLocationDeliverableByName(
            $template,
            $addressData['province'],
            $addressData['city'],
            $addressData['district']
        );
    }

    /**
     * @param array<ShippingCalculationItem> $items
     * @param array<string, string> $addressData
     */
    private function calculateForTemplate(ShippingTemplate $template, array $items, array $addressData): ShippingCalculationResult
    {
        $totalWeight = '0.000';
        $totalQuantity = 0;
        $totalValue = '0.00';

        foreach ($items as $item) {
            $totalWeight = bcadd($totalWeight, sprintf('%.3f', $item->getTotalWeight()), 3);
            $totalQuantity += $item->quantity;
            $totalValue = bcadd($totalValue, sprintf('%.2f', $item->getTotalValue()), 2);
        }

        $unitValue = match ($template->getChargeType()) {
            ChargeType::WEIGHT => $totalWeight,
            ChargeType::QUANTITY => sprintf('%d', $totalQuantity),
            ChargeType::VOLUME => $totalWeight,
        };

        $areaConfig = $this->shippingTemplateAreaRepository->findBestMatchForLocationByName(
            $template,
            $addressData['province'],
            $addressData['city'],
            $addressData['district']
        );

        $fee = $this->calculateFeeForArea($template, $areaConfig, $unitValue);
        $freeShippingThreshold = $this->getFreeShippingThreshold($template, $areaConfig);

        $detail = new ShippingCalculationDetail(
            templateId: (string) $template->getId(),
            templateName: $template->getName(),
            chargeType: $template->getChargeType()->value,
            unitValue: $unitValue,
            fee: $fee,
            isFreeShipping: false,
            areaName: $areaConfig?->getAreaName() ?? $areaConfig?->getCityName() ?? $areaConfig?->getProvinceName(),
            calculation: $this->buildCalculationDescription($template, $areaConfig, $unitValue, $fee),
        );

        return new ShippingCalculationResult(
            fee: $fee,
            freeShippingThreshold: $freeShippingThreshold,
            details: [$detail],
        );
    }

    /**
     * @param numeric-string $unitValue
     * @return numeric-string
     */
    private function calculateFeeForArea(ShippingTemplate $template, ?ShippingTemplateArea $areaConfig, string $unitValue): string
    {
        if (null !== $areaConfig && $areaConfig->hasCustomRates()) {
            return $areaConfig->calculateFee($unitValue);
        }

        return $template->calculateBasicFee($unitValue);
    }

    /**
     * @return numeric-string|null
     */
    private function getFreeShippingThreshold(ShippingTemplate $template, ?ShippingTemplateArea $areaConfig): ?string
    {
        if (null !== $areaConfig && $areaConfig->hasFreeShipping()) {
            $threshold = $areaConfig->getFreeShippingThreshold();

            return is_string($threshold) && is_numeric($threshold) ? $threshold : null;
        }

        $threshold = $template->getFreeShippingThreshold();

        return is_string($threshold) && is_numeric($threshold) ? $threshold : null;
    }

    private function buildCalculationDescription(ShippingTemplate $template, ?ShippingTemplateArea $areaConfig, string $unitValue, string $fee): string
    {
        $chargeTypeText = match ($template->getChargeType()) {
            ChargeType::WEIGHT => '重量',
            ChargeType::QUANTITY => '件数',
            ChargeType::VOLUME => '体积',
        };

        $unitText = match ($template->getChargeType()) {
            ChargeType::WEIGHT => $unitValue . 'kg',
            ChargeType::QUANTITY => $unitValue . '件',
            ChargeType::VOLUME => $unitValue . 'm³',
        };

        $areaText = '';
        if (null !== $areaConfig) {
            $areaText = '(' . ($areaConfig->getAreaName() ?? $areaConfig->getCityName() ?? $areaConfig->getProvinceName()) . ')';
        }

        return sprintf('按%s计费%s：%s，运费¥%s', $chargeTypeText, $areaText, $unitText, $fee);
    }
}
