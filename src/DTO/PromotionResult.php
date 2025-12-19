<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

/**
 * 促销匹配结果
 */
final class PromotionResult
{
    /**
     * @param array<string, mixed> $promotions 匹配到的促销活动
     * @param array<string, mixed> $details    促销详情
     */
    public function __construct(
        private readonly array $promotions = [],
        private readonly float $discount = 0.0,
        private readonly array $details = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPromotions(): array
    {
        return $this->promotions;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function hasPromotions(): bool
    {
        return [] !== $this->promotions;
    }

    /**
     * 合并促销结果
     */
    public function merge(PromotionResult $other): PromotionResult
    {
        return new self(
            array_merge($this->promotions, $other->promotions),
            $this->discount + $other->discount,
            array_merge($this->details, $other->details)
        );
    }

    /**
     * 创建空结果
     */
    public static function empty(): self
    {
        return new self();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'promotions' => $this->promotions,
            'discount' => $this->discount,
            'details' => $this->details,
        ];
    }
}
