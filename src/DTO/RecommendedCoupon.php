<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

/**
 * 推荐优惠券数据传输对象
 */
class RecommendedCoupon
{
    /**
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $metadata
     * @param list<array{skuId: int, gtin: string|null, quantity: int, name: string|null}> $giftItems
     * @param list<array{skuId: int, quantity: int, unitPrice: string, name: string|null, subtotal: string}> $redeemItems
     */
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly string $type,
        private readonly string $expectedDiscount,
        private readonly string $description,
        private readonly ?string $validFrom = null,
        private readonly ?string $validTo = null,
        private readonly array $conditions = [],
        private readonly array $metadata = [],
        private readonly array $giftItems = [],
        private readonly array $redeemItems = []
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getExpectedDiscount(): string
    {
        return $this->expectedDiscount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getValidFrom(): ?string
    {
        return $this->validFrom;
    }

    public function getValidTo(): ?string
    {
        return $this->validTo;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return list<array{skuId: int, gtin: string|null, quantity: int, name: string|null}>
     */
    public function getGiftItems(): array
    {
        return $this->giftItems;
    }

    /**
     * @return list<array{skuId: int, quantity: int, unitPrice: string, name: string|null, subtotal: string}>
     */
    public function getRedeemItems(): array
    {
        return $this->redeemItems;
    }

    /**
     * 是否有赠品（包括 giftItems 和 redeemItems）
     */
    public function hasGifts(): bool
    {
        return [] !== $this->giftItems || [] !== $this->redeemItems;
    }

    /**
     * 转换为数组格式
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'expectedDiscount' => (float) $this->expectedDiscount,
            'description' => $this->description,
            'validFrom' => $this->validFrom,
            'validTo' => $this->validTo,
            'conditions' => $this->conditions,
            'metadata' => $this->metadata,
            'giftItems' => $this->giftItems,
            'redeemItems' => $this->redeemItems,
            'hasGifts' => $this->hasGifts(),
        ];
    }

    /**
     * 转换为数组格式
     *
     * @return array<string, mixed>
     */
    public function formatApiData(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'expectedDiscount' => (float) $this->expectedDiscount,
            'description' => $this->description,
            'validFrom' => $this->validFrom,
            'validTo' => $this->validTo,
            'conditions' => $this->conditions,
            'thirdType' => $this->getThirdType($this->type),
            'giftItems' => $this->giftItems,
            'redeemItems' => $this->redeemItems,
            'hasGifts' => $this->hasGifts(),
        ];
    }

    /**
     * 获取显示用的优惠标签
     */
    public function getDiscountLabel(): string
    {
        $discount = (float) $this->expectedDiscount;
        
        if ($discount <= 0) {
            return '';
        }

        return match ($this->type) {
            'full_reduction' => sprintf('立减¥%.2f', $discount),
            'buy_gift' => '买赠优惠',
            'full_gift' => '满赠优惠',
            'redeem' => '兑换优惠',
            default => sprintf('优惠¥%.2f', $discount),
        };
    }

    public function getThirdType(string $type): int
    {
        return match ($type) {
            'full_reduction' => 0,
            'buy_gift' => 10,
            'full_gift' => 9,
            'redeem' => 6,
            default => 0,
        };
    }

    /**
     * 获取使用条件描述
     */
    public function getConditionDescription(): string
    {
        $minAmount = $this->conditions['min_amount'] ?? 0;
        $maxAmount = $this->conditions['max_amount'] ?? null;

        $parts = [];

        if ($minAmount > 0) {
            $parts[] = sprintf('满¥%.2f可用', (float) $minAmount);
        }

        if (null !== $maxAmount && $maxAmount > 0) {
            $parts[] = sprintf('最多优惠¥%.2f', (float) $maxAmount);
        }

        /** @var array<string, mixed> $applicableProducts */
        $applicableProducts = $this->conditions['applicable_products'] ?? [];
        if (is_array($applicableProducts) && isset($applicableProducts['type']) && 'all' !== $applicableProducts['type']) {
            $parts[] = '限指定商品';
        }

        return implode('，', $parts);
    }

    /**
     * 判断是否为最优惠券（优惠金额最高）
     * 
     * @param array<int, RecommendedCoupon> $allRecommendations
     */
    public function isBestDiscount(array $allRecommendations): bool
    {
        if ([] === $allRecommendations) {
            return true;
        }

        foreach ($allRecommendations as $recommendation) {
            if (!$recommendation instanceof self) {
                continue;
            }
            
            /** @var numeric-string $recommendationDiscount */
            $recommendationDiscount = $recommendation->getExpectedDiscount();
            /** @var numeric-string $thisDiscount */
            $thisDiscount = $this->expectedDiscount;
            if (bccomp($recommendationDiscount, $thisDiscount, 2) > 0) {
                return false;
            }
        }
        
        return true;
    }
}