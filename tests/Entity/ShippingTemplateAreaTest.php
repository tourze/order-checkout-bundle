<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplate;
use Tourze\OrderCheckoutBundle\Entity\ShippingTemplateArea;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(ShippingTemplateArea::class)]
final class ShippingTemplateAreaTest extends AbstractEntityTestCase
{
    private ShippingTemplateArea $area;

    protected function createEntity(): object
    {
        return new ShippingTemplateArea();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->area = new ShippingTemplateArea();
    }

    public function testSetAndGetShippingTemplate(): void
    {
        $template = new ShippingTemplate();
        $this->area->setShippingTemplate($template);
        $this->assertSame($template, $this->area->getShippingTemplate());
    }

    public function testSetAndGetProvinceCode(): void
    {
        $provinceCode = '44';
        $this->area->setProvinceCode($provinceCode);
        $this->assertSame($provinceCode, $this->area->getProvinceCode());
    }

    public function testSetAndGetProvinceName(): void
    {
        $provinceName = '广东省';
        $this->area->setProvinceName($provinceName);
        $this->assertSame($provinceName, $this->area->getProvinceName());
    }

    public function testSetAndGetCityCode(): void
    {
        $cityCode = '4401';
        $this->area->setCityCode($cityCode);
        $this->assertSame($cityCode, $this->area->getCityCode());

        $this->area->setCityCode(null);
        $this->assertNull($this->area->getCityCode());
    }

    public function testSetAndGetCityName(): void
    {
        $cityName = '广州市';
        $this->area->setCityName($cityName);
        $this->assertSame($cityName, $this->area->getCityName());
    }

    public function testSetAndGetAreaCode(): void
    {
        $areaCode = '440101';
        $this->area->setAreaCode($areaCode);
        $this->assertSame($areaCode, $this->area->getAreaCode());

        $this->area->setAreaCode(null);
        $this->assertNull($this->area->getAreaCode());
    }

    public function testSetAndGetAreaName(): void
    {
        $areaName = '荔湾区';
        $this->area->setAreaName($areaName);
        $this->assertSame($areaName, $this->area->getAreaName());
    }

    public function testSetAndGetFirstUnit(): void
    {
        $firstUnit = '1.000';
        $this->area->setFirstUnit($firstUnit);
        $this->assertSame($firstUnit, $this->area->getFirstUnit());
    }

    public function testSetAndGetFirstUnitFee(): void
    {
        $firstUnitFee = '10.00';
        $this->area->setFirstUnitFee($firstUnitFee);
        $this->assertSame($firstUnitFee, $this->area->getFirstUnitFee());
    }

    public function testSetAndGetAdditionalUnit(): void
    {
        $additionalUnit = '0.500';
        $this->area->setAdditionalUnit($additionalUnit);
        $this->assertSame($additionalUnit, $this->area->getAdditionalUnit());
    }

    public function testSetAndGetAdditionalUnitFee(): void
    {
        $additionalUnitFee = '5.00';
        $this->area->setAdditionalUnitFee($additionalUnitFee);
        $this->assertSame($additionalUnitFee, $this->area->getAdditionalUnitFee());
    }

    public function testSetAndGetFreeShippingThreshold(): void
    {
        $threshold = '88.00';
        $this->area->setFreeShippingThreshold($threshold);
        $this->assertSame($threshold, $this->area->getFreeShippingThreshold());
    }

    public function testSetAndGetIsDeliverable(): void
    {
        $this->assertTrue($this->area->isDeliverable());

        $this->area->setIsDeliverable(false);
        $this->assertFalse($this->area->isDeliverable());
    }

    public function testSetAndGetExtendedConfig(): void
    {
        $config = ['special' => 'rule'];
        $this->area->setExtendedConfig($config);
        $this->assertSame($config, $this->area->getExtendedConfig());
    }

    public function testMatchesLocationProvince(): void
    {
        $this->area->setProvinceCode('44');

        $this->assertTrue($this->area->matchesLocation('44'));
        $this->assertFalse($this->area->matchesLocation('11'));
    }

    public function testMatchesLocationProvinceAndCity(): void
    {
        $this->area->setProvinceCode('44');
        $this->area->setCityCode('4401');

        $this->assertTrue($this->area->matchesLocation('44'));
        $this->assertTrue($this->area->matchesLocation('44', '4401'));
        $this->assertFalse($this->area->matchesLocation('44', '4403'));
        $this->assertFalse($this->area->matchesLocation('11'));
    }

    public function testMatchesLocationProvinceAndCityAndArea(): void
    {
        $this->area->setProvinceCode('44');
        $this->area->setCityCode('4401');
        $this->area->setAreaCode('440101');

        $this->assertTrue($this->area->matchesLocation('44'));
        $this->assertTrue($this->area->matchesLocation('44', '4401'));
        $this->assertTrue($this->area->matchesLocation('44', '4401', '440101'));
        $this->assertFalse($this->area->matchesLocation('44', '4401', '440102'));
        $this->assertFalse($this->area->matchesLocation('44', '4403'));
    }

    public function testGetLocationLevel(): void
    {
        $this->area->setProvinceCode('44');
        $this->assertSame(1, $this->area->getLocationLevel());

        $this->area->setCityCode('4401');
        $this->assertSame(2, $this->area->getLocationLevel());

        $this->area->setAreaCode('440101');
        $this->assertSame(3, $this->area->getLocationLevel());
    }

    public function testHasCustomRates(): void
    {
        $this->assertFalse($this->area->hasCustomRates());

        $this->area->setFirstUnit('1.000');
        $this->assertTrue($this->area->hasCustomRates());

        $this->area->setFirstUnit(null);
        $this->area->setFirstUnitFee('10.00');
        $this->assertTrue($this->area->hasCustomRates());
    }

    public function testHasFreeShipping(): void
    {
        $this->assertFalse($this->area->hasFreeShipping());

        $this->area->setFreeShippingThreshold('88.00');
        $this->assertTrue($this->area->hasFreeShipping());

        $this->area->setFreeShippingThreshold(null);
        $this->assertFalse($this->area->hasFreeShipping());
    }

    public function testIsFreeShippingEligible(): void
    {
        $this->area->setFreeShippingThreshold('88.00');

        $this->assertFalse($this->area->isFreeShippingEligible('87.99'));
        $this->assertTrue($this->area->isFreeShippingEligible('88.00'));
        $this->assertTrue($this->area->isFreeShippingEligible('100.00'));

        $this->area->setFreeShippingThreshold(null);
        $this->assertFalse($this->area->isFreeShippingEligible('100.00'));
    }

    public function testCalculateFeeWithoutCustomRates(): void
    {
        $fee = $this->area->calculateFee('1.500');
        $this->assertSame('0.00', $fee);
    }

    public function testCalculateFeeWithCustomRates(): void
    {
        $this->area->setFirstUnit('1.000');
        $this->area->setFirstUnitFee('10.00');
        $this->area->setAdditionalUnit('0.500');
        $this->area->setAdditionalUnitFee('5.00');

        $fee = $this->area->calculateFee('0.500');
        $this->assertSame('10.00', $fee);

        $fee = $this->area->calculateFee('1.000');
        $this->assertSame('10.00', $fee);

        $fee = $this->area->calculateFee('1.500');
        $this->assertSame('15.00', $fee);

        $fee = $this->area->calculateFee('2.000');
        $this->assertSame('20.00', $fee);
    }

    public function testCalculateFeeWithIncompleteConfiguration(): void
    {
        $this->area->setFirstUnit('1.000');

        $fee = $this->area->calculateFee('1.500');
        $this->assertSame('0.00', $fee);

        $this->area->setFirstUnitFee('10.00');

        $fee = $this->area->calculateFee('1.500');
        $this->assertSame('10.00', $fee);
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield ['provinceCode', '44'];
        yield ['provinceName', '广东省'];
        yield ['cityCode', '4401'];
        yield ['cityName', '广州市'];
        yield ['areaCode', '440101'];
        yield ['areaName', '荔湾区'];
        yield ['firstUnit', '1.000'];
        yield ['firstUnitFee', '10.00'];
        yield ['additionalUnit', '0.500'];
        yield ['additionalUnitFee', '5.00'];
        yield ['freeShippingThreshold', '88.00'];
        yield ['extendedConfig', ['special' => 'rule']];
        // Note: isDeliverable 属性跳过自动测试，因为方法命名模式与 AbstractEntityTestCase 不兼容
        // 该属性已在专用测试方法中充分测试
    }
}
