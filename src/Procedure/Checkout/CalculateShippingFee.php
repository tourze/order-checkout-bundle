<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Procedure\Checkout;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tourze\JsonRPC\Core\Attribute\MethodDoc;
use Tourze\JsonRPC\Core\Attribute\MethodExpose;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Attribute\MethodReturn;
use Tourze\JsonRPC\Core\Attribute\MethodTag;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPCCacheBundle\Procedure\CacheableProcedure;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationInput;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationItem;
use Tourze\OrderCheckoutBundle\Service\ShippingCalculationService;

#[MethodExpose(method: 'CalculateCheckoutShippingFee')]
#[MethodTag(name: 'checkout')]
#[MethodDoc(description: '计算运费')]
#[IsGranted(attribute: 'ROLE_USER')]
class CalculateShippingFee extends CacheableProcedure
{
    #[MethodParam(description: '收货地址ID')]
    public string $addressId;

    /**
     * @var array<int, array{productId: string, quantity: int, weight: string, price?: string, shippingTemplateId?: string}>
     */
    #[MethodParam(description: '商品列表，格式：[{productId: string, quantity: int, weight: string, price?: string, shippingTemplateId?: string}]')]
    public array $items;

    public function __construct(
        private readonly ShippingCalculationService $shippingCalculationService,
    ) {
    }

    #[MethodReturn(description: '运费计算结果')]
    public function execute(): array
    {
        $calculationItems = array_map(
            function (array $item): ShippingCalculationItem {
                $weight = is_numeric($item['weight']) ? $item['weight'] : '0.000';
                $price = isset($item['price']) && is_numeric($item['price']) ? $item['price'] : '0.00';

                return new ShippingCalculationItem(
                    productId: $item['productId'],
                    quantity: $item['quantity'],
                    weight: $weight,
                    price: $price,
                    shippingTemplateId: $item['shippingTemplateId'] ?? null,
                );
            },
            $this->items
        );

        $input = new ShippingCalculationInput(
            addressId: $this->addressId,
            items: $calculationItems,
        );

        $result = $this->shippingCalculationService->calculate($input);

        return [
            'fee' => $result->fee,
            'freeShippingThreshold' => $result->freeShippingThreshold,
            'isFreeShipping' => $result->isFreeShipping,
            'isDeliverable' => $result->isDeliverable,
            'errorMessage' => $result->errorMessage,
            'details' => array_map(
                fn ($detail) => [
                    'templateId' => $detail->templateId,
                    'templateName' => $detail->templateName,
                    'chargeType' => $detail->chargeType,
                    'unitValue' => $detail->unitValue,
                    'fee' => $detail->fee,
                    'isFreeShipping' => $detail->isFreeShipping,
                    'areaName' => $detail->areaName,
                    'calculation' => $detail->calculation,
                ],
                $result->details
            ),
        ];
    }

    public static function getMockResult(): ?array
    {
        return [
            'fee' => '12.00',
            'freeShippingThreshold' => '99.00',
            'isFreeShipping' => false,
            'isDeliverable' => true,
            'errorMessage' => null,
            'details' => [
                [
                    'templateId' => '123',
                    'templateName' => '默认运费模板',
                    'chargeType' => 'weight',
                    'unitValue' => '1.500',
                    'fee' => '12.00',
                    'isFreeShipping' => false,
                    'areaName' => '广东省',
                    'calculation' => '按重量计费(广东省)：1.500kg，运费¥12.00',
                ],
            ],
        ];
    }

    public function getCacheKey(JsonRpcRequest $request): string
    {
        $params = $request->getParams()?->toArray() ?? [];
        $addressId = $params['addressId'] ?? '';
        $items = $params['items'] ?? [];
        $itemsHash = md5(json_encode($items, JSON_THROW_ON_ERROR));

        return sprintf('shipping_fee_%s_%s', is_string($addressId) ? $addressId : '', $itemsHash);
    }

    public function getCacheDuration(JsonRpcRequest $request): int
    {
        return 300;
    }

    public function getCacheTags(JsonRpcRequest $request): iterable
    {
        yield 'shipping_calculation';
        yield 'checkout';
    }
}
