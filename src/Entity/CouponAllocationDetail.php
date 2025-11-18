<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\OrderCheckoutBundle\Repository\CouponAllocationDetailRepository;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: CouponAllocationDetailRepository::class)]
#[ORM\Table(name: 'coupon_allocation_detail', options: ['comment' => '优惠券分摊明细'])]
class CouponAllocationDetail implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => 'ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '优惠券码'])]
    #[Assert\NotBlank()]
    private string $couponCode = '';

    #[Assert\NotNull()]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '订单ID'])]
    private int $orderId = 0;

    #[Assert\Type(type: 'integer')]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '订单商品ID'])]
    private ?int $orderProductId = null;

    #[Assert\NotBlank()]
    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => 'SKU标识'])]
    private string $skuId = '';

    #[Assert\NotBlank()]
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['comment' => '分摊金额'])]
    private string $allocatedAmount = '0.00';

    #[Assert\NotBlank()]
    #[ORM\Column(type: Types::STRING, length: 64, options: ['comment' => '分摊规则'])]
    private string $allocationRule = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCouponCode(): string
    {
        return $this->couponCode;
    }

    public function setCouponCode(string $couponCode): void
    {
        $this->couponCode = $couponCode;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function setOrderId(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderProductId(): ?int
    {
        return $this->orderProductId;
    }

    public function setOrderProductId(?int $orderProductId): void
    {
        $this->orderProductId = $orderProductId;
    }

    public function getSkuId(): string
    {
        return $this->skuId;
    }

    public function setSkuId(string $skuId): void
    {
        $this->skuId = $skuId;
    }

    public function getAllocatedAmount(): string
    {
        return $this->allocatedAmount;
    }

    public function setAllocatedAmount(string $allocatedAmount): void
    {
        $this->allocatedAmount = $allocatedAmount;
    }

    public function getAllocationRule(): string
    {
        return $this->allocationRule;
    }

    public function setAllocationRule(string $allocationRule): void
    {
        $this->allocationRule = $allocationRule;
    }

    public function __toString(): string
    {
        return sprintf('%s:%s', $this->couponCode, $this->skuId);
    }
}
