<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Service\MoneyCalculator;

class MoneyCalculatorTest extends TestCase
{
    public function testAdd(): void
    {
        $this->assertSame('3.33', MoneyCalculator::add('1.11', '2.22'));
        $this->assertSame('0.30', MoneyCalculator::add('0.10', '0.20'));
        $this->assertSame('100.00', MoneyCalculator::add('99.99', '0.01'));
    }

    public function testSubtract(): void
    {
        $this->assertSame('1.11', MoneyCalculator::subtract('3.33', '2.22'));
        $this->assertSame('0.01', MoneyCalculator::subtract('0.11', '0.10'));
        $this->assertSame('99.99', MoneyCalculator::subtract('100.00', '0.01'));
    }

    public function testMultiply(): void
    {
        $this->assertSame('2.22', MoneyCalculator::multiply('1.11', '2'));
        $this->assertSame('0.02', MoneyCalculator::multiply('0.10', '0.20'));
        $this->assertSame('150.00', MoneyCalculator::multiply('100.00', '1.5'));
    }

    public function testDivide(): void
    {
        $this->assertSame('2.22', MoneyCalculator::divide('4.44', '2'));
        $this->assertSame('0.50', MoneyCalculator::divide('1.00', '2'));
        $this->assertSame('33.33', MoneyCalculator::divide('100.00', '3'));
    }

    public function testDivideByZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('除数不能为零');
        
        MoneyCalculator::divide('100.00', '0');
    }

    public function testCompare(): void
    {
        $this->assertSame(0, MoneyCalculator::compare('1.00', '1.00'));
        $this->assertSame(1, MoneyCalculator::compare('2.00', '1.00'));
        $this->assertSame(-1, MoneyCalculator::compare('1.00', '2.00'));
    }

    public function testEquals(): void
    {
        $this->assertTrue(MoneyCalculator::equals('1.00', '1.00'));
        $this->assertFalse(MoneyCalculator::equals('1.00', '1.01'));
    }

    public function testGreaterThan(): void
    {
        $this->assertTrue(MoneyCalculator::greaterThan('2.00', '1.00'));
        $this->assertFalse(MoneyCalculator::greaterThan('1.00', '2.00'));
        $this->assertFalse(MoneyCalculator::greaterThan('1.00', '1.00'));
    }

    public function testLessThan(): void
    {
        $this->assertTrue(MoneyCalculator::lessThan('1.00', '2.00'));
        $this->assertFalse(MoneyCalculator::lessThan('2.00', '1.00'));
        $this->assertFalse(MoneyCalculator::lessThan('1.00', '1.00'));
    }

    public function testFormat(): void
    {
        $this->assertSame('1,234.56', MoneyCalculator::format('1234.56'));
        $this->assertSame('1,000.00', MoneyCalculator::format('1000'));
        $this->assertSame('0.99', MoneyCalculator::format('0.99'));
    }

    public function testToCents(): void
    {
        $this->assertSame(100, MoneyCalculator::toCents('1.00'));
        $this->assertSame(150, MoneyCalculator::toCents('1.50'));
        $this->assertSame(1, MoneyCalculator::toCents('0.01'));
    }

    public function testFromCents(): void
    {
        $this->assertSame('1.00', MoneyCalculator::fromCents(100));
        $this->assertSame('1.50', MoneyCalculator::fromCents(150));
        $this->assertSame('0.01', MoneyCalculator::fromCents(1));
    }

    public function testSum(): void
    {
        $amounts = ['1.11', '2.22', '3.33'];
        $this->assertSame('6.66', MoneyCalculator::sum($amounts));
        
        $amounts = ['0.10', '0.20', '0.30'];
        $this->assertSame('0.60', MoneyCalculator::sum($amounts));
        
        $this->assertSame('0.00', MoneyCalculator::sum([]));
    }

    public function testPercentage(): void
    {
        $this->assertSame('10.00', MoneyCalculator::percentage('100.00', '10'));
        $this->assertSame('1.50', MoneyCalculator::percentage('100.00', '1.5'));
        $this->assertSame('0.05', MoneyCalculator::percentage('1.00', '5'));
    }

    public function testRound(): void
    {
        $this->assertSame('1.23', MoneyCalculator::round('1.234'));
        $this->assertSame('1.24', MoneyCalculator::round('1.235'));
        $this->assertSame('2', MoneyCalculator::round('1.99', 0)); // 0位小数应该不包含小数点
    }

    public function testFloatingPointPrecisionIssue(): void
    {
        // 这个测试演示了浮点数精度问题
        $float_result = 0.1 + 0.2;
        $this->assertNotEquals(0.3, $float_result); // 浮点数有精度问题
        
        // 使用 MoneyCalculator 解决精度问题
        $precise_result = MoneyCalculator::add('0.1', '0.2');
        $this->assertSame('0.30', $precise_result); // 精确计算
    }

    public function testComplexCalculation(): void
    {
        // 模拟订单金额计算
        $basePrice = '99.99';
        $shipping = '5.00';
        $tax = MoneyCalculator::percentage($basePrice, '8.5'); // 8.5% 税率
        $discount = '10.00';
        
        $subtotal = MoneyCalculator::add($basePrice, $shipping);
        $subtotal = MoneyCalculator::add($subtotal, $tax);
        $total = MoneyCalculator::subtract($subtotal, $discount);
        
        // 验证计算结果
        $this->assertSame('8.49', $tax); // 99.99 * 8.5% = 8.4915, 保留两位小数为 8.49
        $this->assertSame('104.99', MoneyCalculator::add($basePrice, $shipping));
        $this->assertSame('113.48', MoneyCalculator::add(MoneyCalculator::add($basePrice, $shipping), $tax));
        $this->assertSame('103.48', $total);
    }
}