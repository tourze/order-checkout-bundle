<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineIpBundle\Traits\IpTraceableAware;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\OrderCheckoutBundle\Enum\ChargeType;
use Tourze\OrderCheckoutBundle\Enum\ShippingTemplateStatus;
use Tourze\OrderCheckoutBundle\Repository\ShippingTemplateRepository;

#[ORM\Entity(repositoryClass: ShippingTemplateRepository::class)]
#[ORM\Table(name: 'order_shipping_template', options: ['comment' => '物流配送模板表'])]
class ShippingTemplate implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;
    use BlameableAware;
    use IpTraceableAware;

    /**
     * @deprecated Use ShippingTemplateStatus::ACTIVE instead
     */
    public const STATUS_ACTIVE = 'active';
    /**
     * @deprecated Use ShippingTemplateStatus::INACTIVE instead
     */
    public const STATUS_INACTIVE = 'inactive';

    /**
     * @deprecated Use ChargeType::WEIGHT instead
     */
    public const CHARGE_TYPE_WEIGHT = 'weight';
    /**
     * @deprecated Use ChargeType::QUANTITY instead
     */
    public const CHARGE_TYPE_QUANTITY = 'quantity';
    /**
     * @deprecated Use ChargeType::VOLUME instead
     */
    public const CHARGE_TYPE_VOLUME = 'volume';

    #[ORM\Column(type: Types::STRING, length: 100, options: ['comment' => '模板名称'])]
    #[Assert\NotBlank(message: '模板名称不能为空')]
    #[Assert\Length(max: 100, maxMessage: '模板名称不能超过{{ limit }}个字符')]
    #[IndexColumn]
    private string $name;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '模板描述'])]
    #[Assert\Length(max: 500, maxMessage: '模板描述不能超过{{ limit }}个字符')]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ChargeType::class, options: ['comment' => '计费方式：weight-按重量、quantity-按件数、volume-按体积'])]
    #[Assert\NotBlank(message: '计费方式不能为空')]
    #[Assert\Choice(callback: [ChargeType::class, 'cases'], message: '无效的计费方式')]
    private ChargeType $chargeType;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否为默认模板'])]
    #[Assert\Type(type: 'bool', message: '默认模板标识必须是布尔值')]
    #[IndexColumn]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: ShippingTemplateStatus::class, options: ['comment' => '状态：active-启用、inactive-禁用'])]
    #[Assert\NotBlank(message: '状态不能为空')]
    #[Assert\Choice(callback: [ShippingTemplateStatus::class, 'cases'], message: '无效的状态')]
    #[IndexColumn]
    private ShippingTemplateStatus $status = ShippingTemplateStatus::ACTIVE;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '包邮门槛金额'])]
    #[Assert\PositiveOrZero(message: '包邮门槛金额必须为非负数')]
    private ?string $freeShippingThreshold = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3, nullable: true, options: ['comment' => '首重/首件'])]
    #[Assert\Positive(message: '首重/首件必须为正数')]
    private ?string $firstUnit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '首重/首件运费'])]
    #[Assert\PositiveOrZero(message: '首重/首件运费必须为非负数')]
    private ?string $firstUnitFee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3, nullable: true, options: ['comment' => '续重/续件'])]
    #[Assert\Positive(message: '续重/续件必须为正数')]
    private ?string $additionalUnit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '续重/续件运费'])]
    #[Assert\PositiveOrZero(message: '续重/续件运费必须为非负数')]
    private ?string $additionalUnitFee = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '扩展配置（如特殊商品规则、促销等）'])]
    #[Assert\Type(type: 'array', message: '扩展配置必须是数组')]
    private array $extendedConfig = [];

    /**
     * @var Collection<int, ShippingTemplateArea>
     */
    #[ORM\OneToMany(mappedBy: 'shippingTemplate', targetEntity: ShippingTemplateArea::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $areas;

    public function __construct()
    {
        $this->areas = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getChargeType(): ChargeType
    {
        return $this->chargeType;
    }

    public function setChargeType(ChargeType $chargeType): void
    {
        $this->chargeType = $chargeType;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function getStatus(): ShippingTemplateStatus
    {
        return $this->status;
    }

    public function setStatus(ShippingTemplateStatus $status): void
    {
        $this->status = $status;
    }

    /**
     * @return numeric-string|null
     */
    public function getFreeShippingThreshold(): ?string
    {
        /** @var numeric-string|null */
        return $this->freeShippingThreshold;
    }

    public function setFreeShippingThreshold(?string $freeShippingThreshold): void
    {
        $this->freeShippingThreshold = $freeShippingThreshold;
    }

    /**
     * @return numeric-string|null
     */
    public function getFirstUnit(): ?string
    {
        /** @var numeric-string|null */
        return $this->firstUnit;
    }

    public function setFirstUnit(?string $firstUnit): void
    {
        $this->firstUnit = $firstUnit;
    }

    /**
     * @return numeric-string|null
     */
    public function getFirstUnitFee(): ?string
    {
        /** @var numeric-string|null */
        return $this->firstUnitFee;
    }

    public function setFirstUnitFee(?string $firstUnitFee): void
    {
        $this->firstUnitFee = $firstUnitFee;
    }

    /**
     * @return numeric-string|null
     */
    public function getAdditionalUnit(): ?string
    {
        /** @var numeric-string|null */
        return $this->additionalUnit;
    }

    public function setAdditionalUnit(?string $additionalUnit): void
    {
        $this->additionalUnit = $additionalUnit;
    }

    /**
     * @return numeric-string|null
     */
    public function getAdditionalUnitFee(): ?string
    {
        /** @var numeric-string|null */
        return $this->additionalUnitFee;
    }

    public function setAdditionalUnitFee(?string $additionalUnitFee): void
    {
        $this->additionalUnitFee = $additionalUnitFee;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtendedConfig(): array
    {
        return $this->extendedConfig;
    }

    /**
     * @param array<string, mixed>|null $extendedConfig
     */
    public function setExtendedConfig(?array $extendedConfig): void
    {
        $this->extendedConfig = $extendedConfig ?? [];
    }

    /**
     * @return Collection<int, ShippingTemplateArea>
     */
    public function getAreas(): Collection
    {
        return $this->areas;
    }

    public function addArea(ShippingTemplateArea $area): self
    {
        if (!$this->areas->contains($area)) {
            $this->areas->add($area);
            $area->setShippingTemplate($this);
        }

        return $this;
    }

    public function removeArea(ShippingTemplateArea $area): self
    {
        if ($this->areas->removeElement($area)) {
            if ($area->getShippingTemplate() === $this) {
                $area->setShippingTemplate(null);
            }
        }

        return $this;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function hasFreeShipping(): bool
    {
        return null !== $this->freeShippingThreshold;
    }

    /**
     * @param numeric-string $totalAmount
     */
    public function isFreeShippingEligible(string $totalAmount): bool
    {
        if (!$this->hasFreeShipping()) {
            return false;
        }

        /** @var numeric-string */
        $threshold = $this->freeShippingThreshold ?? '0';

        return bccomp($totalAmount, $threshold, 2) >= 0;
    }

    /**
     * @param numeric-string $unitValue
     * @return numeric-string
     */
    public function calculateBasicFee(string $unitValue): string
    {
        if (null === $this->firstUnit || null === $this->firstUnitFee) {
            return '0.00';
        }

        /** @var numeric-string */
        $fee = $this->firstUnitFee;
        /** @var numeric-string */
        $firstUnit = $this->firstUnit;

        if (bccomp($unitValue, $firstUnit, 3) > 0) {
            $additionalValue = bcsub($unitValue, $firstUnit, 3);

            if (null !== $this->additionalUnit && null !== $this->additionalUnitFee) {
                /** @var numeric-string */
                $additionalUnit = $this->additionalUnit;
                /** @var numeric-string */
                $additionalUnitFee = $this->additionalUnitFee;

                if (bccomp($additionalUnit, '0', 3) > 0) {
                    $additionalUnits = ceil((float) bcdiv($additionalValue, $additionalUnit, 6));
                    $additionalFee = bcmul((string) $additionalUnits, $additionalUnitFee, 2);
                    $fee = bcadd($fee, $additionalFee, 2);
                }
            }
        }

        return $fee;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
