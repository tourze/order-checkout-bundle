<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

/**
 * 运费计算结果
 */
final class ShippingResult
{
    /**
     * @param array<string, mixed> $details 计算详情
     */
    public function __construct(
        private readonly float $shippingFee,
        private readonly bool $freeShipping = false,
        private readonly string $shippingMethod = 'standard',
        private readonly array $details = [],
    ) {
    }

    /**
     * 获取运费
     */
    public function getShippingFee(): float
    {
        return $this->shippingFee;
    }

    /**
     * 是否免邮
     */
    public function isFreeShipping(): bool
    {
        return $this->freeShipping;
    }

    /**
     * 获取配送方式
     */
    public function getShippingMethod(): string
    {
        return $this->shippingMethod;
    }

    /**
     * 获取计算详情
     *
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * 创建免邮结果
     *
     * @param array<string, mixed> $details
     */
    public static function free(string $reason = '满额包邮', array $details = []): self
    {
        return new self(
            shippingFee: 0.0,
            freeShipping: true,
            shippingMethod: 'free',
            details: array_merge($details, ['free_reason' => $reason])
        );
    }

    /**
     * 创建收费结果
     *
     * @param array<string, mixed> $details
     */
    public static function paid(float $fee, string $method = 'standard', array $details = []): self
    {
        return new self(
            shippingFee: $fee,
            freeShipping: false,
            shippingMethod: $method,
            details: $details
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
            'shipping_fee' => $this->shippingFee,
            'free_shipping' => $this->freeShipping,
            'shipping_method' => $this->shippingMethod,
            'details' => $this->details,
        ];
    }
}
