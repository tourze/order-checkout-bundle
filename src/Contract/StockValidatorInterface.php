<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Contract;

use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\StockValidationResult;
use Tourze\ProductServiceContracts\SKU;

/**
 * 库存验证器接口
 */
interface StockValidatorInterface
{
    /**
     * 验证购物车商品库存
     *
     * @param CheckoutItem[] $items 待验证的商品项数组
     */
    public function validate(array $items): StockValidationResult;

    /**
     * 获取指定SKU的可用库存数量
     */
    public function getAvailableQuantity(SKU $sku): int;

    /**
     * 批量获取SKU库存
     *
     * @param SKU[] $skus
     * @return array<string, int> SKU ID => 库存数量
     */
    public function getAvailableQuantities(array $skus): array;
}
