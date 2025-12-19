<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 价格计算上下文
 */
final class CalculationContext
{
    /**
     * @param CheckoutItem[]   $items          购物车项列表
     * @param string[]         $appliedCoupons 已应用的优惠券代码
     * @param array<string, mixed> $metadata       元数据（如地区、时间等）
     */
    public function __construct(
        private readonly UserInterface $user,
        /** @var CheckoutItem[] */
        private readonly array $items,
        /** @var string[] */
        private readonly array $appliedCoupons = [],
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

    /**
     * @return string[]
     */
    public function getAppliedCoupons(): array
    {
        return $this->appliedCoupons;
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

    public function getRegion(): ?string
    {
        $region = $this->getMetadataValue('region');

        return is_string($region) ? $region : null;
    }

    public function getCalculateTime(): \DateTimeInterface
    {
        $time = $this->getMetadataValue('calculate_time', new \DateTimeImmutable());

        return $time instanceof \DateTimeInterface ? $time : new \DateTimeImmutable();
    }

    /**
     * 创建新的上下文实例（不可变对象）
     *
     * @param array<string, mixed> $newMetadata
     */
    public function withMetadata(array $newMetadata): self
    {
        return new self(
            $this->user,
            $this->items,
            $this->appliedCoupons,
            array_merge($this->metadata, $newMetadata)
        );
    }

    /**
     * 添加优惠券
     *
     * @param string[] $coupons
     */
    public function withCoupons(array $coupons): self
    {
        return new self(
            $this->user,
            $this->items,
            array_unique(array_merge($this->appliedCoupons, $coupons)),
            $this->metadata
        );
    }
}
