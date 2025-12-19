<?php

namespace Tourze\OrderCheckoutBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use OrderCoreBundle\Entity\Contract;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIpBundle\Traits\IpTraceableAware;
use Tourze\DoctrinePrecisionBundle\Attribute\PrecisionColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\OrderCheckoutBundle\Repository\PayOrderRepository;

/**
 * 支付订单实体
 * 记录订单的支付信息
 */
#[ORM\Entity(repositoryClass: PayOrderRepository::class)]
#[ORM\Table(name: 'order_contract_pay_order', options: ['comment' => '支付订单表'])]
class PayOrder implements \Stringable
{
    use TimestampableAware;
    use BlameableAware;
    use SnowflakeKeyAware;
    use IpTraceableAware;

    #[ORM\OneToOne(targetEntity: Contract::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Contract $contract = null;

    #[PrecisionColumn]
    #[TrackColumn]
    #[Groups(groups: ['restful_read', 'admin_curd'])]
    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 2, options: ['comment' => '支付金额'])]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private ?string $amount = '0.00';

    #[TrackColumn]
    #[Groups(groups: ['restful_read', 'admin_curd'])]
    #[ORM\Column(type: Types::STRING, length: 128, nullable: true, options: ['comment' => '交易号'])]
    #[Assert\Length(max: 128)]
    private ?string $tradeNo = null;

    #[TrackColumn]
    #[Groups(groups: ['restful_read', 'admin_curd'])]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '支付时间'])]
    #[Assert\DateTime]
    private ?\DateTimeInterface $payTime = null;

    public function __toString(): string
    {
        if (null === $this->getId()) {
            return '';
        }

        return sprintf('PayOrder#%s', $this->getId());
    }

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): void
    {
        $this->contract = $contract;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): void
    {
        $this->amount = $amount;
    }

    public function getTradeNo(): ?string
    {
        return $this->tradeNo;
    }

    public function setTradeNo(?string $tradeNo): void
    {
        $this->tradeNo = $tradeNo;
    }

    public function getPayTime(): ?\DateTimeInterface
    {
        return $this->payTime;
    }

    public function setPayTime(?\DateTimeInterface $payTime): void
    {
        $this->payTime = $payTime;
    }
}
