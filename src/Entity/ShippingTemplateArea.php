<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineIpBundle\Traits\IpTraceableAware;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\OrderCheckoutBundle\Repository\ShippingTemplateAreaRepository;

#[ORM\Entity(repositoryClass: ShippingTemplateAreaRepository::class)]
#[ORM\Table(name: 'order_shipping_template_area', options: ['comment' => '物流配送模板区域表'])]
#[ORM\UniqueConstraint(name: 'uniq_shipping_area_location', columns: ['shipping_template_id', 'province_code', 'city_code', 'area_code'])]
class ShippingTemplateArea implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;
    use BlameableAware;
    use IpTraceableAware;

    #[ORM\ManyToOne(targetEntity: ShippingTemplate::class, inversedBy: 'areas')]
    #[ORM\JoinColumn(name: 'shipping_template_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?ShippingTemplate $shippingTemplate = null;

    #[ORM\Column(type: Types::STRING, length: 10, options: ['comment' => '省份代码'])]
    #[Assert\NotBlank(message: '省份代码不能为空')]
    #[Assert\Length(max: 10, maxMessage: '省份代码不能超过{{ limit }}个字符')]
    #[IndexColumn]
    private string $provinceCode;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['comment' => '省份名称'])]
    #[Assert\NotBlank(message: '省份名称不能为空')]
    #[Assert\Length(max: 50, maxMessage: '省份名称不能超过{{ limit }}个字符')]
    private string $provinceName;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, options: ['comment' => '城市代码（为空表示全省）'])]
    #[Assert\Length(max: 10, maxMessage: '城市代码不能超过{{ limit }}个字符')]
    #[IndexColumn]
    private ?string $cityCode = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '城市名称'])]
    #[Assert\Length(max: 50, maxMessage: '城市名称不能超过{{ limit }}个字符')]
    private ?string $cityName = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true, options: ['comment' => '区县代码（为空表示全市）'])]
    #[Assert\Length(max: 10, maxMessage: '区县代码不能超过{{ limit }}个字符')]
    #[IndexColumn]
    private ?string $areaCode = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '区县名称'])]
    #[Assert\Length(max: 50, maxMessage: '区县名称不能超过{{ limit }}个字符')]
    private ?string $areaName = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3, nullable: true, options: ['comment' => '区域首重/首件'])]
    #[Assert\Positive(message: '区域首重/首件必须为正数')]
    private ?string $firstUnit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '区域首重/首件运费'])]
    #[Assert\PositiveOrZero(message: '区域首重/首件运费必须为非负数')]
    private ?string $firstUnitFee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 3, nullable: true, options: ['comment' => '区域续重/续件'])]
    #[Assert\Positive(message: '区域续重/续件必须为正数')]
    private ?string $additionalUnit = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '区域续重/续件运费'])]
    #[Assert\PositiveOrZero(message: '区域续重/续件运费必须为非负数')]
    private ?string $additionalUnitFee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true, options: ['comment' => '区域包邮门槛金额'])]
    #[Assert\PositiveOrZero(message: '区域包邮门槛金额必须为非负数')]
    private ?string $freeShippingThreshold = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否支持配送到该区域'])]
    #[Assert\Type(type: 'bool', message: '配送支持标识必须是布尔值')]
    private bool $isDeliverable = true;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '区域扩展配置'])]
    #[Assert\Type(type: 'array', message: '区域扩展配置必须是数组')]
    private array $extendedConfig = [];

    public function getShippingTemplate(): ?ShippingTemplate
    {
        return $this->shippingTemplate;
    }

    public function setShippingTemplate(?ShippingTemplate $shippingTemplate): void
    {
        $this->shippingTemplate = $shippingTemplate;
    }

    public function getProvinceCode(): string
    {
        return $this->provinceCode;
    }

    public function setProvinceCode(string $provinceCode): void
    {
        $this->provinceCode = $provinceCode;
    }

    public function getProvinceName(): string
    {
        return $this->provinceName;
    }

    public function setProvinceName(string $provinceName): void
    {
        $this->provinceName = $provinceName;
    }

    public function getCityCode(): ?string
    {
        return $this->cityCode;
    }

    public function setCityCode(?string $cityCode): void
    {
        $this->cityCode = $cityCode;
    }

    public function getCityName(): ?string
    {
        return $this->cityName;
    }

    public function setCityName(?string $cityName): void
    {
        $this->cityName = $cityName;
    }

    public function getAreaCode(): ?string
    {
        return $this->areaCode;
    }

    public function setAreaCode(?string $areaCode): void
    {
        $this->areaCode = $areaCode;
    }

    public function getAreaName(): ?string
    {
        return $this->areaName;
    }

    public function setAreaName(?string $areaName): void
    {
        $this->areaName = $areaName;
    }

    public function getFirstUnit(): ?string
    {
        return $this->firstUnit;
    }

    public function setFirstUnit(?string $firstUnit): void
    {
        $this->firstUnit = $firstUnit;
    }

    public function getFirstUnitFee(): ?string
    {
        return $this->firstUnitFee;
    }

    public function setFirstUnitFee(?string $firstUnitFee): void
    {
        $this->firstUnitFee = $firstUnitFee;
    }

    public function getAdditionalUnit(): ?string
    {
        return $this->additionalUnit;
    }

    public function setAdditionalUnit(?string $additionalUnit): void
    {
        $this->additionalUnit = $additionalUnit;
    }

    public function getAdditionalUnitFee(): ?string
    {
        return $this->additionalUnitFee;
    }

    public function setAdditionalUnitFee(?string $additionalUnitFee): void
    {
        $this->additionalUnitFee = $additionalUnitFee;
    }

    public function getFreeShippingThreshold(): ?string
    {
        return $this->freeShippingThreshold;
    }

    public function setFreeShippingThreshold(?string $freeShippingThreshold): void
    {
        $this->freeShippingThreshold = $freeShippingThreshold;
    }

    public function isDeliverable(): bool
    {
        return $this->isDeliverable;
    }

    public function setIsDeliverable(bool $isDeliverable): void
    {
        $this->isDeliverable = $isDeliverable;
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

    public function matchesLocation(string $provinceCode, ?string $cityCode = null, ?string $areaCode = null): bool
    {
        if ($this->provinceCode !== $provinceCode) {
            return false;
        }

        if (null !== $this->cityCode && null !== $cityCode && $this->cityCode !== $cityCode) {
            return false;
        }

        if (null !== $this->areaCode && null !== $areaCode && $this->areaCode !== $areaCode) {
            return false;
        }

        return true;
    }

    public function matchesLocationByName(string $province, ?string $city = null, ?string $area = null): bool
    {
        if ($this->provinceName !== $province) {
            return false;
        }

        if (null !== $this->cityName && null !== $city && $this->cityName !== $city) {
            return false;
        }

        if (null !== $this->areaName && null !== $area && $this->areaName !== $area) {
            return false;
        }

        return true;
    }

    public function getLocationLevel(): int
    {
        if (null !== $this->areaCode) {
            return 3;
        }
        if (null !== $this->cityCode) {
            return 2;
        }

        return 1;
    }

    public function hasCustomRates(): bool
    {
        return null !== $this->firstUnit || null !== $this->firstUnitFee;
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

        $threshold = $this->freeShippingThreshold ?? '0';
        assert(is_numeric($threshold));

        return bccomp($totalAmount, $threshold, 2) >= 0;
    }

    /**
     * @param numeric-string $unitValue
     * @return numeric-string
     */
    public function calculateFee(string $unitValue): string
    {
        if (!$this->hasCustomRates()) {
            return '0.00';
        }

        if (null === $this->firstUnit || null === $this->firstUnitFee) {
            return '0.00';
        }

        $fee = $this->firstUnitFee;
        $firstUnit = $this->firstUnit;
        assert(is_numeric($fee));
        assert(is_numeric($firstUnit));

        if (bccomp($unitValue, $firstUnit, 3) > 0) {
            $additionalValue = bcsub($unitValue, $firstUnit, 3);

            if (null !== $this->additionalUnit && null !== $this->additionalUnitFee) {
                $additionalUnit = $this->additionalUnit;
                $additionalUnitFee = $this->additionalUnitFee;
                assert(is_numeric($additionalUnit));
                assert(is_numeric($additionalUnitFee));

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
        $location = $this->provinceName;
        if (null !== $this->cityName) {
            $location .= ' ' . $this->cityName;
        }
        if (null !== $this->areaName) {
            $location .= ' ' . $this->areaName;
        }

        return $location;
    }
}
