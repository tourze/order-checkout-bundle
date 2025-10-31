<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\ShippingResult;

/**
 * @internal
 */
#[CoversClass(ShippingResult::class)]
final class ShippingResultTest extends TestCase
{
    public function testShippingResultCanBeInstantiated(): void
    {
        $result = new ShippingResult(15.0);

        $this->assertInstanceOf(ShippingResult::class, $result);
        $this->assertEquals(15.0, $result->getShippingFee());
        $this->assertFalse($result->isFreeShipping());
        $this->assertEquals('standard', $result->getShippingMethod());
        $this->assertEquals([], $result->getDetails());
    }

    public function testShippingResultWithAllParameters(): void
    {
        $details = ['carrier' => 'express', 'estimated_days' => 2];
        $result = new ShippingResult(25.0, true, 'express', $details);

        $this->assertEquals(25.0, $result->getShippingFee());
        $this->assertTrue($result->isFreeShipping());
        $this->assertEquals('express', $result->getShippingMethod());
        $this->assertEquals($details, $result->getDetails());
    }

    public function testGetShippingFee(): void
    {
        $result = new ShippingResult(12.5);

        $this->assertEquals(12.5, $result->getShippingFee());
    }

    public function testGetShippingFeeWithZero(): void
    {
        $result = new ShippingResult(0.0);

        $this->assertEquals(0.0, $result->getShippingFee());
    }

    public function testIsFreeShipping(): void
    {
        $freeResult = new ShippingResult(0.0, true);
        $paidResult = new ShippingResult(10.0, false);

        $this->assertTrue($freeResult->isFreeShipping());
        $this->assertFalse($paidResult->isFreeShipping());
    }

    public function testIsFreeShippingWithDefaultValue(): void
    {
        $result = new ShippingResult(15.0);

        $this->assertFalse($result->isFreeShipping());
    }

    public function testGetShippingMethod(): void
    {
        $standardResult = new ShippingResult(10.0, false, 'standard');
        $expressResult = new ShippingResult(25.0, false, 'express');

        $this->assertEquals('standard', $standardResult->getShippingMethod());
        $this->assertEquals('express', $expressResult->getShippingMethod());
    }

    public function testGetShippingMethodWithDefaultValue(): void
    {
        $result = new ShippingResult(10.0);

        $this->assertEquals('standard', $result->getShippingMethod());
    }

    public function testGetDetails(): void
    {
        $details = [
            'carrier' => 'SF Express',
            'estimated_delivery' => '2024-01-17',
            'tracking_number' => 'SF123456789',
        ];
        $result = new ShippingResult(20.0, false, 'express', $details);

        $this->assertEquals($details, $result->getDetails());
    }

    public function testGetDetailsWithDefaultValue(): void
    {
        $result = new ShippingResult(10.0);

        $this->assertEquals([], $result->getDetails());
        $this->assertIsArray($result->getDetails());
    }

    public function testFreeStaticMethod(): void
    {
        $result = ShippingResult::free();

        $this->assertInstanceOf(ShippingResult::class, $result);
        $this->assertEquals(0.0, $result->getShippingFee());
        $this->assertTrue($result->isFreeShipping());
        $this->assertEquals('free', $result->getShippingMethod());

        $details = $result->getDetails();
        $this->assertEquals('满额包邮', $details['free_reason']);
    }

    public function testFreeWithCustomReason(): void
    {
        $result = ShippingResult::free('新用户免邮');

        $this->assertEquals(0.0, $result->getShippingFee());
        $this->assertTrue($result->isFreeShipping());
        $this->assertEquals('free', $result->getShippingMethod());

        $details = $result->getDetails();
        $this->assertEquals('新用户免邮', $details['free_reason']);
    }

    public function testFreeWithCustomDetails(): void
    {
        $customDetails = ['promotion_id' => 'FREE2024', 'user_level' => 'vip'];
        $result = ShippingResult::free('VIP免邮', $customDetails);

        $expectedDetails = [
            'promotion_id' => 'FREE2024',
            'user_level' => 'vip',
            'free_reason' => 'VIP免邮',
        ];

        $this->assertEquals($expectedDetails, $result->getDetails());
    }

    public function testPaidStaticMethod(): void
    {
        $result = ShippingResult::paid(15.0);

        $this->assertInstanceOf(ShippingResult::class, $result);
        $this->assertEquals(15.0, $result->getShippingFee());
        $this->assertFalse($result->isFreeShipping());
        $this->assertEquals('standard', $result->getShippingMethod());
        $this->assertEquals([], $result->getDetails());
    }

    public function testPaidWithCustomMethod(): void
    {
        $result = ShippingResult::paid(30.0, 'express');

        $this->assertEquals(30.0, $result->getShippingFee());
        $this->assertFalse($result->isFreeShipping());
        $this->assertEquals('express', $result->getShippingMethod());
    }

    public function testPaidWithDetails(): void
    {
        $details = ['carrier' => 'DHL', 'insurance' => true];
        $result = ShippingResult::paid(45.0, 'international', $details);

        $this->assertEquals(45.0, $result->getShippingFee());
        $this->assertFalse($result->isFreeShipping());
        $this->assertEquals('international', $result->getShippingMethod());
        $this->assertEquals($details, $result->getDetails());
    }

    public function testToArray(): void
    {
        $details = ['carrier' => 'YTO', 'estimated_days' => 3];
        $result = new ShippingResult(12.0, false, 'economy', $details);

        $array = $result->toArray();

        $expected = [
            'shipping_fee' => 12.0,
            'free_shipping' => false,
            'shipping_method' => 'economy',
            'details' => $details,
        ];

        $this->assertEquals($expected, $array);
    }

    public function testToArrayWithFreeShipping(): void
    {
        $result = ShippingResult::free('会员免邮');

        $array = $result->toArray();

        $expected = [
            'shipping_fee' => 0.0,
            'free_shipping' => true,
            'shipping_method' => 'free',
            'details' => ['free_reason' => '会员免邮'],
        ];

        $this->assertEquals($expected, $array);
    }

    public function testToArrayWithMinimalData(): void
    {
        $result = new ShippingResult(10.0);

        $array = $result->toArray();

        $expected = [
            'shipping_fee' => 10.0,
            'free_shipping' => false,
            'shipping_method' => 'standard',
            'details' => [],
        ];

        $this->assertEquals($expected, $array);
    }

    public function testNegativeShippingFee(): void
    {
        // Test with negative fee (discount or refund scenario)
        $result = new ShippingResult(-5.0, false, 'refund');

        $this->assertEquals(-5.0, $result->getShippingFee());
        $this->assertFalse($result->isFreeShipping());
        $this->assertEquals('refund', $result->getShippingMethod());
    }

    public function testFloatPrecision(): void
    {
        $result = new ShippingResult(12.99);

        $this->assertEquals(12.99, $result->getShippingFee());
    }

    public function testComplexScenarios(): void
    {
        // Free shipping with fee (promotional scenario)
        $promotionalResult = new ShippingResult(15.0, true, 'promotional', ['promo' => 'FREESHIP']);
        $this->assertEquals(15.0, $promotionalResult->getShippingFee());
        $this->assertTrue($promotionalResult->isFreeShipping());

        // Zero fee but not marked as free shipping
        $zeroFeeResult = new ShippingResult(0.0, false, 'pickup');
        $this->assertEquals(0.0, $zeroFeeResult->getShippingFee());
        $this->assertFalse($zeroFeeResult->isFreeShipping());
    }

    public function testDetailsMerging(): void
    {
        $details = ['base' => 'value', 'override' => 'original'];
        $result = ShippingResult::free('测试原因', $details);

        $expectedDetails = [
            'base' => 'value',
            'override' => 'original',
            'free_reason' => '测试原因',
        ];

        $this->assertEquals($expectedDetails, $result->getDetails());
    }

    public function testDetailsMergingOverride(): void
    {
        $details = ['free_reason' => 'will_be_overridden', 'other' => 'preserved'];
        $result = ShippingResult::free('final_reason', $details);

        $expectedDetails = [
            'free_reason' => 'final_reason',
            'other' => 'preserved',
        ];

        $this->assertEquals($expectedDetails, $result->getDetails());
    }

    public function testStaticMethodsReturnNewInstances(): void
    {
        $free1 = ShippingResult::free();
        $free2 = ShippingResult::free();
        $paid1 = ShippingResult::paid(10.0);
        $paid2 = ShippingResult::paid(10.0);

        $this->assertNotSame($free1, $free2);
        $this->assertNotSame($paid1, $paid2);
        $this->assertNotSame($free1, $paid1);
    }
}
