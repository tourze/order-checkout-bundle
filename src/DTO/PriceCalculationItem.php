<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

use Tourze\OrderCheckoutBundle\Exception\InvalidCartItemException;
use Tourze\OrderCheckoutBundle\Exception\MissingRequiredFieldException;
use Tourze\ProductCoreBundle\Entity\Sku;

/**
 * 价格计算项目 - 独立于具体业务实体的数据传输对象
 */
class PriceCalculationItem
{
    public function __construct(
        private readonly int|string $skuId,
        private readonly int $quantity,
        private readonly bool $selected = true,
        private readonly ?Sku $sku = null,
        private readonly ?float $unitPrice = null,
    ) {
    }

    public function getSkuId(): int|string
    {
        return $this->skuId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function getSku(): ?Sku
    {
        return $this->sku;
    }

    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    /**
     * 从数组创建 PriceCalculationItem
     *
     * @param array{id?: int|string, skuId?: int|string, quantity?: int, selected?: bool} $data
     */
    public static function fromArray(array $data): self
    {
        $skuId = $data['skuId'] ?? $data['id'] ?? null;
        if (null === $skuId) {
            throw new MissingRequiredFieldException('skuId');
        }

        return new self(
            skuId: $skuId,
            quantity: $data['quantity'] ?? 1,
            selected: $data['selected'] ?? true,
            unitPrice: null // 价格将从 SKU 获取，不再依赖外部传入
        );
    }

    /**
     * 从 CartItem 实体创建 PriceCalculationItem
     * @param object $cartItem
     */
    public static function fromCartItem(object $cartItem): self
    {
        self::validateCartItem($cartItem);

        $sku = $cartItem->getSku();
        $skuId = self::extractSkuId($sku);
        $quantity = self::extractQuantity($cartItem);
        $selected = self::extractSelected($cartItem);
        $validatedSku = self::validateSku($sku);

        return new self(
            skuId: $skuId,
            quantity: $quantity,
            selected: $selected,
            sku: $validatedSku
        );
    }

    /**
     * 验证 CartItem 对象是否具有必需的方法
     */
    private static function validateCartItem(object $cartItem): void
    {
        if (!method_exists($cartItem, 'getSku') || !method_exists($cartItem, 'getQuantity')) {
            throw new InvalidCartItemException();
        }
    }

    /**
     * 安全地提取 SKU ID
     */
    private static function extractSkuId(?object $sku): int|string
    {
        if (null === $sku || !method_exists($sku, 'getId')) {
            return '0';
        }

        $skuIdValue = $sku->getId();
        if (is_int($skuIdValue) || is_string($skuIdValue)) {
            return $skuIdValue;
        }

        return '0';
    }

    /**
     * 安全地提取数量
     */
    private static function extractQuantity(object $cartItem): int
    {
        $quantityValue = $cartItem->getQuantity();

        return is_int($quantityValue) ? $quantityValue : 1;
    }

    /**
     * 安全地提取选中状态
     */
    private static function extractSelected(object $cartItem): bool
    {
        if (!method_exists($cartItem, 'isSelected')) {
            return true;
        }

        $selectedValue = $cartItem->isSelected();

        return is_bool($selectedValue) ? $selectedValue : true;
    }

    /**
     * 验证并返回 SKU 对象
     */
    private static function validateSku(?object $sku): ?Sku
    {
        return ($sku instanceof Sku) ? $sku : null;
    }

    /**
     * 创建带有 SKU 信息的新实例
     */
    public function withSku(Sku $sku): self
    {
        return new self(
            skuId: $this->skuId,
            quantity: $this->quantity,
            selected: $this->selected,
            sku: $sku,
            unitPrice: $this->unitPrice
        );
    }

    /**
     * 获取有效的单价（从 SKU 获取市场价格）
     * @return numeric-string
     */
    public function getEffectiveUnitPrice(): string
    {
        if (null === $this->sku) {
            return '0.00';
        }

        $marketPrice = $this->sku->getMarketPrice();

        return null !== $marketPrice ? sprintf('%.2f', $marketPrice) : '0.00';
    }

    /**
     * 获取有效的单价（浮点数格式，用于向后兼容）
     * @deprecated 使用 getEffectiveUnitPrice() 返回字符串格式
     */
    public function getEffectiveUnitPriceFloat(): float
    {
        return (float) $this->getEffectiveUnitPrice();
    }

    /**
     * 计算小计金额
     */
    public function getSubtotal(): string
    {
        $unitPrice = $this->getEffectiveUnitPrice();

        return bcmul($unitPrice, sprintf('%.0f', $this->quantity), 2);
    }

    /**
     * 计算小计金额（浮点数格式，用于向后兼容）
     * @deprecated 使用 getSubtotal() 返回字符串格式
     */
    public function getSubtotalFloat(): float
    {
        return (float) $this->getSubtotal();
    }
}
