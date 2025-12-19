<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Param\Checkout;

use Symfony\Component\Validator\Constraints as Assert;
use Tourze\JsonRPC\Core\Attribute\MethodParam;
use Tourze\JsonRPC\Core\Contracts\RpcParamInterface;

final class CalculateShippingFeeParam implements RpcParamInterface
{
    #[MethodParam(description: '收货地址ID')]
    #[Assert\NotBlank(message: '收货地址ID不能为空')]
    public string $addressId;

    /**
     * @var array<int, array{productId: string, quantity: int, weight: string, price?: string, shippingTemplateId?: string}>
     */
    #[MethodParam(description: '商品列表,格式:[{productId: string, quantity: int, weight: string, price?: string, shippingTemplateId?: string}]')]
    #[Assert\NotBlank(message: '商品列表不能为空')]
    #[Assert\Type(type: 'array', message: '商品列表必须是数组')]
    #[Assert\Count(min: 1, minMessage: '至少需要一个商品')]
    public array $items;
}
