<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationDetail;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationResult;

/**
 * @internal
 */
#[CoversClass(ShippingCalculationResult::class)]
final class ShippingCalculationResultTest extends TestCase
{
    public function testConstructorInitializesRequiredProperties(): void
    {
        // Arrange & Act: 创建基本的运费计算结果
        $result = new ShippingCalculationResult(fee: '15.00');

        // Assert: 验证必需属性
        $this->assertEquals('15.00', $result->fee);
        $this->assertNull($result->freeShippingThreshold);
        $this->assertFalse($result->isFreeShipping);
        $this->assertTrue($result->isDeliverable);
        $this->assertEmpty($result->details);
        $this->assertNull($result->errorMessage);
    }

    public function testConstructorWithAllProperties(): void
    {
        // Arrange: 准备详情数据
        $details = [
            new ShippingCalculationDetail('template1', '模板1', 'weight', '2.0', '10.00'),
            new ShippingCalculationDetail('template2', '模板2', 'quantity', '3', '5.00'),
        ];

        // Act: 创建包含所有属性的结果
        $result = new ShippingCalculationResult(
            fee: '0.00',
            freeShippingThreshold: '99.00',
            isFreeShipping: true,
            isDeliverable: true,
            details: $details,
            errorMessage: null
        );

        // Assert: 验证所有属性
        $this->assertEquals('0.00', $result->fee);
        $this->assertEquals('99.00', $result->freeShippingThreshold);
        $this->assertTrue($result->isFreeShipping);
        $this->assertTrue($result->isDeliverable);
        $this->assertCount(2, $result->details);
        $this->assertNull($result->errorMessage);
    }

    public function testIsSuccessWithValidResult(): void
    {
        // Arrange: 创建成功的结果
        $result = new ShippingCalculationResult(
            fee: '12.00',
            isDeliverable: true,
            errorMessage: null
        );

        // Act: 检查是否成功
        $isSuccess = $result->isSuccess();

        // Assert: 验证成功状态
        $this->assertTrue($isSuccess);
    }

    public function testIsSuccessWithError(): void
    {
        // Arrange: 创建有错误的结果
        $result = new ShippingCalculationResult(
            fee: '0.00',
            isDeliverable: true,
            errorMessage: '不支持配送到该地区'
        );

        // Act: 检查是否成功
        $isSuccess = $result->isSuccess();

        // Assert: 验证失败状态
        $this->assertFalse($isSuccess);
    }

    public function testIsSuccessWithNonDeliverable(): void
    {
        // Arrange: 创建不可配送的结果
        $result = new ShippingCalculationResult(
            fee: '0.00',
            isDeliverable: false,
            errorMessage: null
        );

        // Act: 检查是否成功
        $isSuccess = $result->isSuccess();

        // Assert: 验证失败状态
        $this->assertFalse($isSuccess);
    }

    public function testHasErrorWithoutError(): void
    {
        // Arrange: 创建没有错误的结果
        $result = new ShippingCalculationResult(fee: '10.00');

        // Act: 检查是否有错误
        $hasError = $result->hasError();

        // Assert: 验证没有错误
        $this->assertFalse($hasError);
    }

    public function testHasErrorWithError(): void
    {
        // Arrange: 创建有错误的结果
        $result = new ShippingCalculationResult(
            fee: '0.00',
            errorMessage: '配送地址不在服务范围内'
        );

        // Act: 检查是否有错误
        $hasError = $result->hasError();

        // Assert: 验证有错误
        $this->assertTrue($hasError);
    }

    public function testIsFreeWithFreeShippingFlag(): void
    {
        // Arrange: 创建标记为免运费的结果
        $result = new ShippingCalculationResult(
            fee: '0.00',
            isFreeShipping: true
        );

        // Act: 检查是否免费
        $isFree = $result->isFree();

        // Assert: 验证免费状态
        $this->assertTrue($isFree);
    }

    public function testIsFreeWithZeroFee(): void
    {
        // Arrange: 创建运费为0的结果
        $result = new ShippingCalculationResult(
            fee: '0.00',
            isFreeShipping: false
        );

        // Act: 检查是否免费
        $isFree = $result->isFree();

        // Assert: 验证免费状态（运费为0也算免费）
        $this->assertTrue($isFree);
    }

    public function testIsFreeWithNonZeroFee(): void
    {
        // Arrange: 创建有运费的结果
        $result = new ShippingCalculationResult(
            fee: '15.50',
            isFreeShipping: false
        );

        // Act: 检查是否免费
        $isFree = $result->isFree();

        // Assert: 验证非免费状态
        $this->assertFalse($isFree);
    }

    public function testGetTotalFee(): void
    {
        // Arrange: 创建结果
        $result = new ShippingCalculationResult(fee: '25.75');

        // Act: 获取总运费
        $totalFee = $result->getTotalFee();

        // Assert: 验证总运费
        $this->assertEquals('25.75', $totalFee);
    }

    public function testGetFormattedFeeForFreeShipping(): void
    {
        // Arrange: 创建免运费结果
        $result = new ShippingCalculationResult(
            fee: '0.00',
            isFreeShipping: true
        );

        // Act: 获取格式化运费
        $formatted = $result->getFormattedFee();

        // Assert: 验证免运费显示
        $this->assertEquals('免运费', $formatted);
    }

    public function testGetFormattedFeeForPaidShipping(): void
    {
        // Arrange: 创建收费运费结果
        $result = new ShippingCalculationResult(fee: '18.88');

        // Act: 获取格式化运费
        $formatted = $result->getFormattedFee();

        // Assert: 验证运费格式
        $this->assertEquals('¥18.88', $formatted);
    }

    public function testGetFormattedFreeShippingThresholdWithoutThreshold(): void
    {
        // Arrange: 创建没有免运门槛的结果
        $result = new ShippingCalculationResult(fee: '10.00');

        // Act: 获取格式化免运门槛
        $formatted = $result->getFormattedFreeShippingThreshold();

        // Assert: 验证返回null
        $this->assertNull($formatted);
    }

    public function testGetFormattedFreeShippingThresholdWithThreshold(): void
    {
        // Arrange: 创建有免运门槛的结果
        $result = new ShippingCalculationResult(
            fee: '8.00',
            freeShippingThreshold: '99.00'
        );

        // Act: 获取格式化免运门槛
        $formatted = $result->getFormattedFreeShippingThreshold();

        // Assert: 验证免运门槛格式
        $this->assertEquals('¥99.00', $formatted);
    }

    public function testHasDetailsWithoutDetails(): void
    {
        // Arrange: 创建没有详情的结果
        $result = new ShippingCalculationResult(fee: '12.00');

        // Act: 检查是否有详情
        $hasDetails = $result->hasDetails();

        // Assert: 验证没有详情
        $this->assertFalse($hasDetails);
    }

    public function testHasDetailsWithDetails(): void
    {
        // Arrange: 创建有详情的结果
        $details = [
            new ShippingCalculationDetail('template1', '模板1', 'weight', '1.0', '8.00'),
        ];
        $result = new ShippingCalculationResult(
            fee: '8.00',
            details: $details
        );

        // Act: 检查是否有详情
        $hasDetails = $result->hasDetails();

        // Assert: 验证有详情
        $this->assertTrue($hasDetails);
    }

    public function testGetDetailsByTemplateId(): void
    {
        // Arrange: 创建包含多个模板详情的结果
        $details = [
            new ShippingCalculationDetail('template1', '模板1', 'weight', '1.0', '8.00'),
            new ShippingCalculationDetail('template2', '模板2', 'quantity', '2', '5.00'),
            new ShippingCalculationDetail('template1', '模板1区域', 'weight', '1.5', '10.00'),
        ];
        $result = new ShippingCalculationResult(
            fee: '23.00',
            details: $details
        );

        // Act: 按模板ID获取详情
        $template1Details = $result->getDetailsByTemplateId('template1');
        $template2Details = $result->getDetailsByTemplateId('template2');
        $nonExistentDetails = $result->getDetailsByTemplateId('template3');

        // Assert: 验证筛选结果
        $this->assertCount(2, $template1Details);
        $this->assertCount(1, $template2Details);
        $this->assertCount(0, $nonExistentDetails);

        // 验证返回的详情类型
        foreach ($template1Details as $detail) {
            $this->assertInstanceOf(ShippingCalculationDetail::class, $detail);
            $this->assertEquals('template1', $detail->templateId);
        }
    }

    public function testBccompPrecisionInIsFree(): void
    {
        // Arrange: 测试微小运费的判断
        $testCases = [
            ['0.00', true],
            ['0.001', false], // 小于精度但不为0
            ['0.01', false],
            ['-0.01', false], // 负数
        ];

        foreach ($testCases as [$fee, $expectedIsFree]) {
            // Act: 创建结果并检查是否免费
            $result = new ShippingCalculationResult(
                fee: $fee,
                isFreeShipping: false
            );
            $isFree = $result->isFree();

            // Assert: 验证bccomp精度判断
            $this->assertEquals(
                $expectedIsFree,
                $isFree,
                "Fee {$fee} should " . ($expectedIsFree ? '' : 'not ') . 'be free'
            );
        }
    }

    public function testReadOnlyPropertiesAreAccessible(): void
    {
        // Arrange: 创建结果对象
        $details = [new ShippingCalculationDetail('test', '测试', 'weight', '1.0', '5.00')];
        $result = new ShippingCalculationResult(
            fee: '99.99',
            freeShippingThreshold: '199.00',
            isFreeShipping: true,
            isDeliverable: false,
            details: $details,
            errorMessage: '测试错误'
        );

        // Act & Assert: 验证所有只读属性都可访问
        $this->assertEquals('99.99', $result->fee);
        $this->assertEquals('199.00', $result->freeShippingThreshold);
        $this->assertTrue($result->isFreeShipping);
        $this->assertFalse($result->isDeliverable);
        $this->assertCount(1, $result->details);
        $this->assertEquals('测试错误', $result->errorMessage);
    }
}
