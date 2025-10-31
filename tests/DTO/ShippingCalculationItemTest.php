<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationItem;

/**
 * @internal
 */
#[CoversClass(ShippingCalculationItem::class)]
final class ShippingCalculationItemTest extends TestCase
{
    public function testConstructorInitializesAllRequiredProperties(): void
    {
        // Arrange & Act: 创建基本的运费计算项目
        $item = new ShippingCalculationItem(
            productId: 'product_123',
            quantity: 2,
            weight: '1.5'
        );

        // Assert: 验证必需属性
        $this->assertEquals('product_123', $item->productId);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals('1.5', $item->weight);
        $this->assertEquals('0.00', $item->price);
        $this->assertNull($item->shippingTemplateId);
    }

    public function testConstructorWithAllProperties(): void
    {
        // Arrange & Act: 创建包含所有属性的运费计算项目
        $item = new ShippingCalculationItem(
            productId: 'product_456',
            quantity: 5,
            weight: '2.8',
            price: '99.50',
            shippingTemplateId: 'template_789'
        );

        // Assert: 验证所有属性
        $this->assertEquals('product_456', $item->productId);
        $this->assertEquals(5, $item->quantity);
        $this->assertEquals('2.8', $item->weight);
        $this->assertEquals('99.50', $item->price);
        $this->assertEquals('template_789', $item->shippingTemplateId);
    }

    public function testGetTotalWeightCalculatesCorrectly(): void
    {
        // Arrange: 创建运费计算项目
        $item = new ShippingCalculationItem(
            productId: 'product_weight_test',
            quantity: 3,
            weight: '2.5'
        );

        // Act: 计算总重量
        $totalWeight = $item->getTotalWeight();

        // Assert: 验证总重量计算（2.5 * 3 = 7.5）
        $this->assertEquals('7.500', $totalWeight);
    }

    public function testGetTotalWeightWithDecimalQuantity(): void
    {
        // Arrange: 创建带小数重量的项目
        $item = new ShippingCalculationItem(
            productId: 'product_decimal',
            quantity: 4,
            weight: '1.25'
        );

        // Act: 计算总重量
        $totalWeight = $item->getTotalWeight();

        // Assert: 验证小数重量计算（1.25 * 4 = 5.0）
        $this->assertEquals('5.000', $totalWeight);
    }

    public function testGetTotalValueCalculatesCorrectly(): void
    {
        // Arrange: 创建有价格的运费计算项目
        $item = new ShippingCalculationItem(
            productId: 'product_value_test',
            quantity: 2,
            weight: '1.0',
            price: '50.25'
        );

        // Act: 计算总价值
        $totalValue = $item->getTotalValue();

        // Assert: 验证总价值计算（50.25 * 2 = 100.50）
        $this->assertEquals('100.50', $totalValue);
    }

    public function testGetTotalValueWithZeroPrice(): void
    {
        // Arrange: 创建零价格的项目
        $item = new ShippingCalculationItem(
            productId: 'product_zero_price',
            quantity: 10,
            weight: '5.0',
            price: '0.00'
        );

        // Act: 计算总价值
        $totalValue = $item->getTotalValue();

        // Assert: 验证零价格总价值
        $this->assertEquals('0.00', $totalValue);
    }

    public function testGetTotalValueWithHighPrecisionPrice(): void
    {
        // Arrange: 创建高精度价格的项目
        $item = new ShippingCalculationItem(
            productId: 'product_precision',
            quantity: 3,
            weight: '1.0',
            price: '33.333'
        );

        // Act: 计算总价值
        $totalValue = $item->getTotalValue();

        // Assert: 验证高精度价格计算（保留2位小数：99.999 -> 99.99）
        $this->assertEquals('99.99', $totalValue);
    }

    public function testHasCustomShippingTemplateWithoutTemplate(): void
    {
        // Arrange: 创建没有自定义模板的项目
        $item = new ShippingCalculationItem(
            productId: 'product_no_template',
            quantity: 1,
            weight: '1.0'
        );

        // Act: 检查是否有自定义运费模板
        $hasTemplate = $item->hasCustomShippingTemplate();

        // Assert: 验证没有自定义模板
        $this->assertFalse($hasTemplate);
    }

    public function testHasCustomShippingTemplateWithTemplate(): void
    {
        // Arrange: 创建有自定义模板的项目
        $item = new ShippingCalculationItem(
            productId: 'product_with_template',
            quantity: 1,
            weight: '1.0',
            price: '10.00',
            shippingTemplateId: 'custom_template_123'
        );

        // Act: 检查是否有自定义运费模板
        $hasTemplate = $item->hasCustomShippingTemplate();

        // Assert: 验证有自定义模板
        $this->assertTrue($hasTemplate);
    }

    public function testZeroQuantityCalculations(): void
    {
        // Arrange: 创建数量为0的项目（边界情况）
        $item = new ShippingCalculationItem(
            productId: 'product_zero_qty',
            quantity: 0,
            weight: '5.0',
            price: '100.00'
        );

        // Act: 计算总重量和总价值
        $totalWeight = $item->getTotalWeight();
        $totalValue = $item->getTotalValue();

        // Assert: 验证零数量结果
        $this->assertEquals('0.000', $totalWeight);
        $this->assertEquals('0.00', $totalValue);
    }

    public function testLargeQuantityCalculations(): void
    {
        // Arrange: 创建大数量的项目
        $item = new ShippingCalculationItem(
            productId: 'product_large_qty',
            quantity: 1000,
            weight: '0.5',
            price: '1.99'
        );

        // Act: 计算总重量和总价值
        $totalWeight = $item->getTotalWeight();
        $totalValue = $item->getTotalValue();

        // Assert: 验证大数量计算
        $this->assertEquals('500.000', $totalWeight);
        $this->assertEquals('1990.00', $totalValue);
    }

    public function testReadOnlyPropertiesAreAccessible(): void
    {
        // Arrange: 创建项目对象
        $item = new ShippingCalculationItem(
            productId: 'readonly_test',
            quantity: 7,
            weight: '3.14',
            price: '42.00',
            shippingTemplateId: 'readonly_template'
        );

        // Act & Assert: 验证所有只读属性都可访问
        $this->assertEquals('readonly_test', $item->productId);
        $this->assertEquals(7, $item->quantity);
        $this->assertEquals('3.14', $item->weight);
        $this->assertEquals('42.00', $item->price);
        $this->assertEquals('readonly_template', $item->shippingTemplateId);
    }

    public function testStringNumericTypes(): void
    {
        // Arrange: 创建项目验证字符串数值类型
        $item = new ShippingCalculationItem(
            productId: 'numeric_string_test',
            quantity: 1,
            weight: '0.001', // 很小的重量
            price: '999999.99' // 很大的价格
        );

        // Act: 进行计算
        $totalWeight = $item->getTotalWeight();
        $totalValue = $item->getTotalValue();

        // Assert: 验证字符串数值计算精度
        $this->assertEquals('0.001', $totalWeight);
        $this->assertEquals('999999.99', $totalValue);
        $this->assertIsString($item->weight);
        $this->assertIsString($item->price);
    }
}
