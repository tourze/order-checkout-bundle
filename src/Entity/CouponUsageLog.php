<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\OrderCheckoutBundle\Repository\CouponUsageLogRepository;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: CouponUsageLogRepository::class)]
#[ORM\Table(name: 'coupon_usage_log', options: ['comment' => '优惠券使用日志'])]
class CouponUsageLog implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => 'ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '优惠券码'])]
    #[Assert\NotBlank()]
    private string $couponCode = '';

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '用户标识'])]
    #[Assert\NotBlank()]
    private string $userIdentifier = '';

    #[Assert\NotNull()]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '订单ID'])]
    private int $orderId = 0;

    #[ORM\Column(type: Types::STRING, length: 64, options: ['comment' => '订单号'])]
    #[Assert\NotBlank()]
    private string $orderNumber = '';

    #[Assert\NotNull()]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '使用时间'])]
    private \DateTimeImmutable $usageTime;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['comment' => '优惠金额'])]
    #[Assert\NotNull()]
    private string $discountAmount = '0.00';

    #[Assert\NotBlank()]
    #[ORM\Column(type: Types::STRING, length: 64, options: ['comment' => '优惠券类型'])]
    private string $couponType = '';

    /**
     * @var array<string, mixed>|null
     */
    #[Assert\Type(type: 'array')]
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '附加信息'])]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->usageTime = new \DateTimeImmutable();
    }

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

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function setOrderId(int $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(string $orderNumber): void
    {
        $this->orderNumber = $orderNumber;
    }

    public function getUsageTime(): \DateTimeImmutable
    {
        return $this->usageTime;
    }

    public function setUsageTime(\DateTimeImmutable $usageTime): void
    {
        $this->usageTime = $usageTime;
    }

    public function getDiscountAmount(): string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(string $discountAmount): void
    {
        $this->discountAmount = $discountAmount;
    }

    public function getCouponType(): string
    {
        return $this->couponType;
    }

    public function setCouponType(string $couponType): void
    {
        $this->couponType = $couponType;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function __toString(): string
    {
        return sprintf('%s:%s', $this->couponCode, $this->orderNumber);
    }
}
