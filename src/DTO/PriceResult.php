<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

/**
 * 价格计算结果
 */
class PriceResult
{
    /** @var numeric-string */
    private readonly string $originalPrice;

    /** @var numeric-string */
    private readonly string $finalPrice;

    /** @var numeric-string */
    private readonly string $discount;

    /** @var array<string, mixed> */
    private readonly array $details;

    /**
     * @var array<array<string, mixed>> 产品信息列表
     */
    private readonly array $products;

    /**
     * @param array<string, mixed> $details 计算明细
     * @param array<array<string, mixed>> $products 产品信息列表
     */
    public function __construct(
        string|float $originalPrice,
        string|float $finalPrice,
        string|float $discount = '0.00',
        array $details = [],
        array $products = [],
    ) {
        $this->originalPrice = self::ensureNumericString($originalPrice);
        $this->finalPrice = self::ensureNumericString($finalPrice);
        $this->discount = self::ensureNumericString($discount);
        $this->details = $details;
        $this->products = $products;
    }

    /**
     * 获取原价
     */
    public function getOriginalPrice(): string
    {
        return $this->originalPrice;
    }

    /**
     * 获取最终价格
     */
    public function getFinalPrice(): string
    {
        return $this->finalPrice;
    }

    /**
     * 获取优惠金额
     */
    public function getDiscount(): string
    {
        return $this->discount;
    }

    /**
     * 获取原价（浮点数格式，用于向后兼容）
     * @deprecated 使用 getOriginalPrice() 返回字符串格式
     */
    public function getOriginalPriceFloat(): float
    {
        return (float) $this->originalPrice;
    }

    /**
     * 获取最终价格（浮点数格式，用于向后兼容）
     * @deprecated 使用 getFinalPrice() 返回字符串格式
     */
    public function getFinalPriceFloat(): float
    {
        return (float) $this->finalPrice;
    }

    /**
     * 获取优惠金额（浮点数格式，用于向后兼容）
     * @deprecated 使用 getDiscount() 返回字符串格式
     */
    public function getDiscountFloat(): float
    {
        return (float) $this->discount;
    }

    /**
     * 获取计算明细
     *
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * 获取产品信息列表
     *
     * @return array<array<string, mixed>>
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * 获取明细项
     */
    public function getDetail(string $key, mixed $default = null): mixed
    {
        return $this->details[$key] ?? $default;
    }

    /**
     * 合并价格结果
     */
    public function merge(PriceResult $other): PriceResult
    {
        /** @var numeric-string $mergedOriginal */
        $mergedOriginal = bcadd($this->originalPrice, $other->originalPrice, 2);
        /** @var numeric-string $mergedFinal */
        $mergedFinal = bcadd($this->finalPrice, $other->finalPrice, 2);
        /** @var numeric-string $mergedDiscount */
        $mergedDiscount = bcadd($this->discount, $other->discount, 2);

        return new self(
            $mergedOriginal,
            $mergedFinal,
            $mergedDiscount,
            array_merge($this->details, $other->details),
            array_merge($this->products, $other->products)
        );
    }

    /**
     * 转换为数组
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'original_price' => $this->originalPrice,
            'final_price' => $this->finalPrice,
            'discount' => $this->discount,
            'details' => $this->details,
            'products' => $this->products,
        ];
    }

    /**
     * 创建空结果
     */
    public static function empty(): self
    {
        return new self('0.00', '0.00', '0.00', [], []);
    }

    /**
     * 创建价格结果，支持多种类型输入
     *
     * @param array<string, mixed> $details 计算明细
     * @param array<array<string, mixed>> $products 产品信息列表
     */
    public static function create(
        string|float $originalPrice,
        string|float $finalPrice,
        string|float $discount = 0.0,
        array $details = [],
        array $products = [],
    ): self {
        return new self(
            $originalPrice,
            $finalPrice,
            $discount,
            $details,
            $products
        );
    }

    /**
     * 确保值为 numeric-string 格式
     *
     * @return numeric-string
     */
    private static function ensureNumericString(string|float $value): string
    {
        if (is_string($value) && is_numeric($value)) {
            return $value;
        }

        return sprintf('%.2f', $value);
    }
}
