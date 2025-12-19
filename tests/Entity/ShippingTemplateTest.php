<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplateArea;
use Tourze\OrderCheckoutBundle\Enum\ChargeType;
use Tourze\OrderCheckoutBundle\Enum\ShippingTemplateStatus;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(ShippingTemplate::class)]
final class ShippingTemplateTest extends AbstractEntityTestCase
{
    private ShippingTemplate $template;

    protected function createEntity(): object
    {
        return new ShippingTemplate();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->template = new ShippingTemplate();
    }

    public function testSetAndGetName(): void
    {
        $name = '默认运费模板';
        $this->template->setName($name);
        $this->assertSame($name, $this->template->getName());
    }

    public function testSetAndGetDescription(): void
    {
        $description = '这是默认的运费模板';
        $this->template->setDescription($description);
        $this->assertSame($description, $this->template->getDescription());

        $this->template->setDescription(null);
        $this->assertNull($this->template->getDescription());
    }

    public function testSetAndGetChargeType(): void
    {
        $chargeType = ChargeType::WEIGHT;
        $this->template->setChargeType($chargeType);
        $this->assertSame($chargeType, $this->template->getChargeType());
    }

    public function testSetAndGetIsDefault(): void
    {
        $this->assertFalse($this->template->isDefault());

        $this->template->setIsDefault(true);
        $this->assertTrue($this->template->isDefault());
    }

    public function testSetAndGetStatus(): void
    {
        $this->assertSame(ShippingTemplateStatus::ACTIVE, $this->template->getStatus());

        $this->template->setStatus(ShippingTemplateStatus::INACTIVE);
        $this->assertSame(ShippingTemplateStatus::INACTIVE, $this->template->getStatus());
    }

    public function testSetAndGetFreeShippingThreshold(): void
    {
        $threshold = '99.00';
        $this->template->setFreeShippingThreshold($threshold);
        $this->assertSame($threshold, $this->template->getFreeShippingThreshold());

        $this->template->setFreeShippingThreshold(null);
        $this->assertNull($this->template->getFreeShippingThreshold());
    }

    public function testSetAndGetFirstUnit(): void
    {
        $firstUnit = '1.000';
        $this->template->setFirstUnit($firstUnit);
        $this->assertSame($firstUnit, $this->template->getFirstUnit());
    }

    public function testSetAndGetFirstUnitFee(): void
    {
        $firstUnitFee = '8.00';
        $this->template->setFirstUnitFee($firstUnitFee);
        $this->assertSame($firstUnitFee, $this->template->getFirstUnitFee());
    }

    public function testSetAndGetAdditionalUnit(): void
    {
        $additionalUnit = '0.500';
        $this->template->setAdditionalUnit($additionalUnit);
        $this->assertSame($additionalUnit, $this->template->getAdditionalUnit());
    }

    public function testSetAndGetAdditionalUnitFee(): void
    {
        $additionalUnitFee = '3.00';
        $this->template->setAdditionalUnitFee($additionalUnitFee);
        $this->assertSame($additionalUnitFee, $this->template->getAdditionalUnitFee());
    }

    public function testSetAndGetExtendedConfig(): void
    {
        $config = ['key' => 'value'];
        $this->template->setExtendedConfig($config);
        $this->assertSame($config, $this->template->getExtendedConfig());
    }

    public function testAreasManagement(): void
    {
        $area = new ShippingTemplateArea();

        $this->assertCount(0, $this->template->getAreas());

        $this->template->addArea($area);
        $this->assertCount(1, $this->template->getAreas());
        $this->assertTrue($this->template->getAreas()->contains($area));
        $this->assertSame($this->template, $area->getShippingTemplate());

        $this->template->removeArea($area);
        $this->assertCount(0, $this->template->getAreas());
        $this->assertFalse($this->template->getAreas()->contains($area));
    }

    public function testIsActive(): void
    {
        $this->template->setStatus(ShippingTemplateStatus::ACTIVE);
        $this->assertTrue($this->template->isActive());

        $this->template->setStatus(ShippingTemplateStatus::INACTIVE);
        $this->assertFalse($this->template->isActive());
    }

    public function testHasFreeShipping(): void
    {
        $this->assertFalse($this->template->hasFreeShipping());

        $this->template->setFreeShippingThreshold('99.00');
        $this->assertTrue($this->template->hasFreeShipping());

        $this->template->setFreeShippingThreshold(null);
        $this->assertFalse($this->template->hasFreeShipping());
    }

    public function testIsFreeShippingEligible(): void
    {
        $this->template->setFreeShippingThreshold('99.00');

        $this->assertFalse($this->template->isFreeShippingEligible('98.99'));
        $this->assertTrue($this->template->isFreeShippingEligible('99.00'));
        $this->assertTrue($this->template->isFreeShippingEligible('100.00'));

        $this->template->setFreeShippingThreshold(null);
        $this->assertFalse($this->template->isFreeShippingEligible('100.00'));
    }

    public function testCalculateBasicFeeWithoutConfiguration(): void
    {
        $fee = $this->template->calculateBasicFee('1.500');
        $this->assertSame('0.00', $fee);
    }

    public function testCalculateBasicFeeWithFirstUnitOnly(): void
    {
        $this->template->setFirstUnit('1.000');
        $this->template->setFirstUnitFee('8.00');

        $fee = $this->template->calculateBasicFee('0.500');
        $this->assertSame('8.00', $fee);

        $fee = $this->template->calculateBasicFee('1.000');
        $this->assertSame('8.00', $fee);
    }

    public function testCalculateBasicFeeWithAdditionalUnits(): void
    {
        $this->template->setFirstUnit('1.000');
        $this->template->setFirstUnitFee('8.00');
        $this->template->setAdditionalUnit('0.500');
        $this->template->setAdditionalUnitFee('3.00');

        $fee = $this->template->calculateBasicFee('1.500');
        $this->assertSame('11.00', $fee);

        $fee = $this->template->calculateBasicFee('2.000');
        $this->assertSame('14.00', $fee);

        $fee = $this->template->calculateBasicFee('2.200');
        $this->assertSame('17.00', $fee);
    }

    public function testCalculateBasicFeeWithZeroAdditionalUnit(): void
    {
        $this->template->setFirstUnit('1.000');
        $this->template->setFirstUnitFee('8.00');
        $this->template->setAdditionalUnit('0.000');
        $this->template->setAdditionalUnitFee('3.00');

        $fee = $this->template->calculateBasicFee('2.000');
        $this->assertSame('8.00', $fee);
    }

    public function testCalculateBasicFeeWithoutAdditionalUnitFee(): void
    {
        $this->template->setFirstUnit('1.000');
        $this->template->setFirstUnitFee('8.00');
        $this->template->setAdditionalUnit('0.500');

        $fee = $this->template->calculateBasicFee('2.000');
        $this->assertSame('8.00', $fee);
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield ['name', '默认运费模板'];
        yield ['description', '这是默认的运费模板'];
        yield ['chargeType', ChargeType::WEIGHT];
        yield ['status', ShippingTemplateStatus::INACTIVE];
        yield ['freeShippingThreshold', '99.00'];
        yield ['firstUnit', '1.000'];
        yield ['firstUnitFee', '8.00'];
        yield ['additionalUnit', '0.500'];
        yield ['additionalUnitFee', '3.00'];
        yield ['extendedConfig', ['key' => 'value']];
        // Note: isDefault 属性跳过自动测试，因为方法命名模式与 AbstractEntityTestCase 不兼容
        // 该属性已在专用测试方法中充分测试
    }
}
