<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationDetail;

/**
 * @internal
 */
#[CoversClass(ShippingCalculationDetail::class)]
final class ShippingCalculationDetailTest extends TestCase
{
    public function testConstructorInitializesAllRequiredProperties(): void
    {
        // Arrange & Act: 创建基本的运费计算详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_123',
            templateName: '标准运费模板',
            chargeType: 'weight',
            unitValue: '2.5',
            fee: '15.00'
        );

        // Assert: 验证必需属性
        $this->assertEquals('template_123', $detail->templateId);
        $this->assertEquals('标准运费模板', $detail->templateName);
        $this->assertEquals('weight', $detail->chargeType);
        $this->assertEquals('2.5', $detail->unitValue);
        $this->assertEquals('15.00', $detail->fee);
        $this->assertFalse($detail->isFreeShipping);
        $this->assertNull($detail->areaName);
        $this->assertNull($detail->calculation);
    }

    public function testConstructorWithAllOptionalProperties(): void
    {
        // Arrange & Act: 创建包含所有可选属性的运费计算详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_456',
            templateName: '偏远地区运费模板',
            chargeType: 'quantity',
            unitValue: '5',
            fee: '25.00',
            isFreeShipping: true,
            areaName: '新疆维吾尔自治区',
            calculation: '首件20元，续件5元/件'
        );

        // Assert: 验证所有属性
        $this->assertEquals('template_456', $detail->templateId);
        $this->assertEquals('偏远地区运费模板', $detail->templateName);
        $this->assertEquals('quantity', $detail->chargeType);
        $this->assertEquals('5', $detail->unitValue);
        $this->assertEquals('25.00', $detail->fee);
        $this->assertTrue($detail->isFreeShipping);
        $this->assertEquals('新疆维吾尔自治区', $detail->areaName);
        $this->assertEquals('首件20元，续件5元/件', $detail->calculation);
    }

    public function testGetFormattedUnitValueWithWeightChargeType(): void
    {
        // Arrange: 创建按重量计费的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_weight',
            templateName: '重量计费模板',
            chargeType: 'weight',
            unitValue: '3.2',
            fee: '18.00'
        );

        // Act: 获取格式化后的单位值
        $formatted = $detail->getFormattedUnitValue();

        // Assert: 验证重量单位格式
        $this->assertEquals('3.2kg', $formatted);
    }

    public function testGetFormattedUnitValueWithQuantityChargeType(): void
    {
        // Arrange: 创建按件数计费的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_quantity',
            templateName: '件数计费模板',
            chargeType: 'quantity',
            unitValue: '10',
            fee: '30.00'
        );

        // Act: 获取格式化后的单位值
        $formatted = $detail->getFormattedUnitValue();

        // Assert: 验证件数单位格式
        $this->assertEquals('10件', $formatted);
    }

    public function testGetFormattedUnitValueWithVolumeChargeType(): void
    {
        // Arrange: 创建按体积计费的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_volume',
            templateName: '体积计费模板',
            chargeType: 'volume',
            unitValue: '0.5',
            fee: '12.00'
        );

        // Act: 获取格式化后的单位值
        $formatted = $detail->getFormattedUnitValue();

        // Assert: 验证体积单位格式
        $this->assertEquals('0.5m³', $formatted);
    }

    public function testGetFormattedUnitValueWithUnknownChargeType(): void
    {
        // Arrange: 创建未知计费类型的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_unknown',
            templateName: '未知计费模板',
            chargeType: 'unknown_type',
            unitValue: '100',
            fee: '50.00'
        );

        // Act: 获取格式化后的单位值
        $formatted = $detail->getFormattedUnitValue();

        // Assert: 验证未知类型返回原值
        $this->assertEquals('100', $formatted);
    }

    public function testGetFormattedFeeForFreeShipping(): void
    {
        // Arrange: 创建免运费的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_free',
            templateName: '免运费模板',
            chargeType: 'weight',
            unitValue: '2.0',
            fee: '0.00',
            isFreeShipping: true
        );

        // Act: 获取格式化后的运费
        $formatted = $detail->getFormattedFee();

        // Assert: 验证免运费显示
        $this->assertEquals('免运费', $formatted);
    }

    public function testGetFormattedFeeForPaidShipping(): void
    {
        // Arrange: 创建收费运输的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_paid',
            templateName: '收费运输模板',
            chargeType: 'weight',
            unitValue: '1.5',
            fee: '8.50'
        );

        // Act: 获取格式化后的运费
        $formatted = $detail->getFormattedFee();

        // Assert: 验证运费格式
        $this->assertEquals('¥8.50', $formatted);
    }

    public function testHasAreaSpecificRatesWithoutAreaName(): void
    {
        // Arrange: 创建没有指定区域的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_general',
            templateName: '通用模板',
            chargeType: 'weight',
            unitValue: '1.0',
            fee: '10.00'
        );

        // Act: 检查是否有区域特定费率
        $hasAreaRates = $detail->hasAreaSpecificRates();

        // Assert: 验证没有区域特定费率
        $this->assertFalse($hasAreaRates);
    }

    public function testHasAreaSpecificRatesWithAreaName(): void
    {
        // Arrange: 创建有指定区域的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_area',
            templateName: '区域模板',
            chargeType: 'weight',
            unitValue: '1.0',
            fee: '15.00',
            isFreeShipping: false,
            areaName: '西藏自治区'
        );

        // Act: 检查是否有区域特定费率
        $hasAreaRates = $detail->hasAreaSpecificRates();

        // Assert: 验证有区域特定费率
        $this->assertTrue($hasAreaRates);
    }

    public function testGetCalculationDescriptionWithCustomCalculation(): void
    {
        // Arrange: 创建有自定义计算说明的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_custom',
            templateName: '自定义计算模板',
            chargeType: 'quantity',
            unitValue: '3',
            fee: '20.00',
            isFreeShipping: false,
            areaName: null,
            calculation: '首件10元，续件每件5元，超过5件包邮'
        );

        // Act: 获取计算说明
        $description = $detail->getCalculationDescription();

        // Assert: 验证自定义计算说明
        $this->assertEquals('首件10元，续件每件5元，超过5件包邮', $description);
    }

    public function testGetCalculationDescriptionForFreeShipping(): void
    {
        // Arrange: 创建免运费且无自定义说明的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_free_auto',
            templateName: '自动免运费模板',
            chargeType: 'weight',
            unitValue: '2.0',
            fee: '0.00',
            isFreeShipping: true
        );

        // Act: 获取计算说明
        $description = $detail->getCalculationDescription();

        // Assert: 验证免运费说明
        $this->assertEquals('满足包邮条件', $description);
    }

    public function testGetCalculationDescriptionWithWeightChargeType(): void
    {
        // Arrange: 创建按重量计费且无自定义说明的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_weight_desc',
            templateName: '重量计费说明模板',
            chargeType: 'weight',
            unitValue: '1.8',
            fee: '12.00'
        );

        // Act: 获取计算说明
        $description = $detail->getCalculationDescription();

        // Assert: 验证重量计费说明
        $this->assertEquals('按重量计费：1.8kg', $description);
    }

    public function testGetCalculationDescriptionWithQuantityChargeType(): void
    {
        // Arrange: 创建按件数计费且无自定义说明的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_quantity_desc',
            templateName: '件数计费说明模板',
            chargeType: 'quantity',
            unitValue: '4',
            fee: '16.00'
        );

        // Act: 获取计算说明
        $description = $detail->getCalculationDescription();

        // Assert: 验证件数计费说明
        $this->assertEquals('按件数计费：4件', $description);
    }

    public function testGetCalculationDescriptionWithVolumeChargeType(): void
    {
        // Arrange: 创建按体积计费且无自定义说明的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_volume_desc',
            templateName: '体积计费说明模板',
            chargeType: 'volume',
            unitValue: '0.8',
            fee: '14.00'
        );

        // Act: 获取计算说明
        $description = $detail->getCalculationDescription();

        // Assert: 验证体积计费说明
        $this->assertEquals('按体积计费：0.8m³', $description);
    }

    public function testGetCalculationDescriptionWithUnknownChargeType(): void
    {
        // Arrange: 创建未知计费类型且无自定义说明的详情
        $detail = new ShippingCalculationDetail(
            templateId: 'template_unknown_desc',
            templateName: '未知计费说明模板',
            chargeType: 'special_type',
            unitValue: '999',
            fee: '99.99'
        );

        // Act: 获取计算说明
        $description = $detail->getCalculationDescription();

        // Assert: 验证未知类型计费说明
        $this->assertEquals('按计费单位计费：999', $description);
    }

    public function testReadOnlyPropertiesAreAccessible(): void
    {
        // Arrange: 创建详情对象
        $detail = new ShippingCalculationDetail(
            templateId: 'readonly_test',
            templateName: '只读属性测试',
            chargeType: 'weight',
            unitValue: '5.0',
            fee: '25.00',
            isFreeShipping: true,
            areaName: '测试区域',
            calculation: '测试计算'
        );

        // Act & Assert: 验证所有只读属性都可访问
        $this->assertEquals('readonly_test', $detail->templateId);
        $this->assertEquals('只读属性测试', $detail->templateName);
        $this->assertEquals('weight', $detail->chargeType);
        $this->assertEquals('5.0', $detail->unitValue);
        $this->assertEquals('25.00', $detail->fee);
        $this->assertTrue($detail->isFreeShipping);
        $this->assertEquals('测试区域', $detail->areaName);
        $this->assertEquals('测试计算', $detail->calculation);
    }
}
