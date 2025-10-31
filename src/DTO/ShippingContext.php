<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 运费计算上下文
 */
class ShippingContext
{
    /**
     * @param CheckoutItem[]       $items    购物车商品数组
     * @param array<string, mixed> $metadata 元数据
     */
    public function __construct(
        private readonly UserInterface $user,
        /** @var CheckoutItem[] */
        private readonly array $items,
        private readonly string $region,
        /** @var array<string, mixed> */
        private readonly array $metadata = [],
    ) {
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }

    /**
     * @return CheckoutItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * 计算商品总重量
     * 注意：当前 SKU 实体没有重量字段，返回默认值
     */
    public function getTotalWeight(): float
    {
        $weight = 0.0;
        foreach ($this->items as $item) {
            if (!$item->isSelected()) {
                continue;
            }

            // SKU 实体暂无重量字段，使用默认值
            $itemWeight = 0.5; // 默认每个商品 0.5kg
            $quantity = $item->getQuantity();
            $weight += $itemWeight * $quantity;
        }

        return $weight;
    }

    /**
     * 计算商品总价值
     */
    public function getTotalValue(): float
    {
        $value = 0.0;
        foreach ($this->items as $item) {
            if (!$item->isSelected()) {
                continue;
            }

            $sku = $item->getSku();
            $price = 100.0; // 默认价格
            if (null !== $sku) {
                $marketPrice = $sku->getMarketPrice();
                if (null !== $marketPrice) {
                    $price = (float) $marketPrice;
                }
            }
            $value += $price * $item->getQuantity();
        }

        return $value;
    }

    /**
     * 获取选中商品总数量
     */
    public function getTotalQuantity(): int
    {
        $quantity = 0;
        foreach ($this->items as $item) {
            if (!$item->isSelected()) {
                continue;
            }
            $quantity += $item->getQuantity();
        }

        return $quantity;
    }
}
