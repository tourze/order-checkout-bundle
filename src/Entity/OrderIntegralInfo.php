<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\OrderCheckoutBundle\Repository\OrderIntegralInfoRepository;

#[ORM\Entity(repositoryClass: OrderIntegralInfoRepository::class)]
#[ORM\Table(name: 'order_integral_info', options: ['comment' => '订单积分信息表'])]
class OrderIntegralInfo implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;

    #[IndexColumn]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['comment' => '订单ID'])]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private int $orderId;

    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['comment' => '所需积分数量'])]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private int $integralRequired;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: false, options: ['comment' => '积分操作ID（扣减流水号）'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $integralOperationId;

    #[ORM\Column(type: Types::BOOLEAN, nullable: false, options: ['default' => false, 'comment' => '是否已退还'])]
    #[Assert\NotNull]
    private bool $isRefunded = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '退还时间'])]
    #[Assert\Type(type: \DateTimeInterface::class)]
    private ?\DateTimeImmutable $refundedTime = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => '退还操作ID'])]
    #[Assert\Type(type: 'string')]
    #[Assert\Length(max: 64)]
    private ?string $refundOperationId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '备注'])]
    #[Assert\Type(type: 'string')]
    private ?string $remark = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: false, options: ['comment' => '扣减时间'])]
    #[Assert\NotBlank]
    private \DateTimeImmutable $deductedTime;

    #[IndexColumn]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['comment' => '用户ID'])]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private int $userId;

    #[IndexColumn]
    #[ORM\Column(type: Types::BIGINT, nullable: true, options: ['comment' => '商品ID'])]
    #[Assert\Positive]
    private ?int $productId = null;

    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['comment' => '扣减前余额'])]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private int $balanceBefore;

    #[ORM\Column(type: Types::INTEGER, nullable: false, options: ['comment' => '扣减后余额'])]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private int $balanceAfter;

    public function __toString(): string
    {
        return sprintf(
            'OrderIntegralInfo[orderId=%d, userId=%d, integralRequired=%d]',
            $this->orderId ?? 0,
            $this->userId ?? 0,
            $this->integralRequired ?? 0
        );
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function setOrderId(int $orderId): self
    {
        $this->orderId = $orderId;
        return $this;
    }

    public function getIntegralRequired(): int
    {
        return $this->integralRequired;
    }

    public function setIntegralRequired(int $integralRequired): self
    {
        $this->integralRequired = $integralRequired;
        return $this;
    }

    public function getIntegralOperationId(): string
    {
        return $this->integralOperationId;
    }

    public function setIntegralOperationId(string $integralOperationId): self
    {
        $this->integralOperationId = $integralOperationId;
        return $this;
    }

    public function isRefunded(): bool
    {
        return $this->isRefunded;
    }

    public function setIsRefunded(bool $isRefunded): self
    {
        $this->isRefunded = $isRefunded;
        return $this;
    }

    public function getRefundedTime(): ?\DateTimeImmutable
    {
        return $this->refundedTime;
    }

    public function setRefundedTime(?\DateTimeImmutable $refundedTime): self
    {
        $this->refundedTime = $refundedTime;
        return $this;
    }

    public function getRefundOperationId(): ?string
    {
        return $this->refundOperationId;
    }

    public function setRefundOperationId(?string $refundOperationId): self
    {
        $this->refundOperationId = $refundOperationId;
        return $this;
    }

    public function getRemark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): self
    {
        $this->remark = $remark;
        return $this;
    }

    public function getDeductedTime(): \DateTimeImmutable
    {
        return $this->deductedTime;
    }

    public function setDeductedTime(\DateTimeImmutable $deductedTime): self
    {
        $this->deductedTime = $deductedTime;
        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(?int $productId): self
    {
        $this->productId = $productId;
        return $this;
    }

    public function getBalanceBefore(): int
    {
        return $this->balanceBefore;
    }

    public function setBalanceBefore(int $balanceBefore): self
    {
        $this->balanceBefore = $balanceBefore;
        return $this;
    }

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function setBalanceAfter(int $balanceAfter): self
    {
        $this->balanceAfter = $balanceAfter;
        return $this;
    }
}
