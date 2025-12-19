<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

use Tourze\OrderCheckoutBundle\Exception\InvalidCartItemException;
use Tourze\OrderCheckoutBundle\Exception\MissingRequiredFieldException;
use Tourze\ProductCoreBundle\Entity\Sku;

/**
 * 结算项目 - 用于结算流程的数据传输对象
 */
final class CheckoutItem
{
    public function __construct(
        private readonly int|string $skuId,
        private readonly int $quantity,
        private readonly bool $selected = true,
        private readonly ?Sku $sku = null,
        private readonly int|string|null $id = null,
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

    public function getId(): int|string|null
    {
        return $this->id;
    }

    /**
     * 从数组创建 CheckoutItem
     *
     * @param array{id?: int, skuId?: int|string, quantity?: int, selected?: bool} $data
     */
    public static function fromArray(array $data): self
    {
        $skuId = $data['skuId'] ?? null;
        if (null === $skuId) {
            throw new MissingRequiredFieldException('skuId');
        }

        return new self(
            skuId: $skuId,
            quantity: $data['quantity'] ?? 1,
            selected: $data['selected'] ?? true,
            id: $data['id'] ?? null
        );
    }

    /**
     * 从 CartItem 实体创建 CheckoutItem
     * @param object{getSku: callable, getQuantity: callable} $cartItem
     */
    public static function fromCartItem(object $cartItem): self
    {
        self::validateCartItem($cartItem);

        /** @var object|null $sku */
        $sku = method_exists($cartItem, 'getSku') ? $cartItem->getSku() : null;
        $skuId = self::extractSkuId($sku);
        $quantity = self::extractQuantity($cartItem);
        $selected = self::extractSelected($cartItem);
        $id = self::extractCartItemId($cartItem);
        $validatedSku = self::validateSku($sku);

        return new self(
            skuId: $skuId,
            quantity: $quantity,
            selected: $selected,
            sku: $validatedSku,
            id: $id
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
     * @param object{getQuantity: callable} $cartItem
     */
    private static function extractQuantity(object $cartItem): int
    {
        /** @var mixed $quantity */
        $quantity = method_exists($cartItem, 'getQuantity') ? $cartItem->getQuantity() : 1;

        return is_int($quantity) ? $quantity : 1;
    }

    /**
     * 安全地提取选中状态
     */
    private static function extractSelected(object $cartItem): bool
    {
        if (!method_exists($cartItem, 'isSelected')) {
            return true;
        }

        $selected = $cartItem->isSelected();

        return is_bool($selected) ? $selected : true;
    }

    /**
     * 安全地提取 CartItem ID
     */
    private static function extractCartItemId(object $cartItem): int|string|null
    {
        if (!method_exists($cartItem, 'getId')) {
            return null;
        }

        $cartItemId = $cartItem->getId();
        if (is_int($cartItemId) || is_string($cartItemId)) {
            return $cartItemId;
        }

        return null;
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
            id: $this->id
        );
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'skuId' => $this->skuId,
            'quantity' => $this->quantity,
            'selected' => $this->selected,
            'sku' => $this->sku,
        ];
    }
}
