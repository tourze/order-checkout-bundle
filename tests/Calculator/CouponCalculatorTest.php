<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Calculator\CouponCalculator;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CouponCalculator::class)]
#[RunTestsInSeparateProcesses]
final class CouponCalculatorTest extends AbstractIntegrationTestCase
{
    private CouponCalculator $calculator;

    protected function onSetUp(): void
    {
        $this->calculator = self::getService(CouponCalculator::class);
    }

    public function testCalculatorCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CouponCalculator::class, $this->calculator);
    }

    public function testSupportsWithEmptyItems(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $context = new CalculationContext($user, [], []);

        $this->assertFalse($this->calculator->supports($context));
    }

    public function testSupportsWithItems(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $items = [
            new CheckoutItem('test-sku-1', 1, true),
        ];
        // 没有优惠券时返回 false
        $context = new CalculationContext($user, $items, []);
        $this->assertFalse($this->calculator->supports($context));

        // 有优惠券时返回 true（第三个参数是 appliedCoupons 数组）
        $contextWithCoupon = new CalculationContext($user, $items, ['TEST-COUPON']);
        $this->assertTrue($this->calculator->supports($contextWithCoupon));
    }

    public function testGetPriority(): void
    {
        $this->assertSame(600, $this->calculator->getPriority());
    }

    public function testGetType(): void
    {
        $this->assertSame('coupon', $this->calculator->getType());
    }

    public function testCalculateWithoutCoupons(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $items = [
            new CheckoutItem('test-sku-1', 1, true),
        ];
        $context = new CalculationContext($user, $items, []);

        $result = $this->calculator->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertSame('0.00', $result->getOriginalPrice());
        $this->assertSame('0.00', $result->getFinalPrice());
        $this->assertSame('0.00', $result->getDiscount());
    }

    public function testCalculateWithCoupons(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $items = [
            new CheckoutItem('test-sku-1', 1, true),
        ];
        $context = new CalculationContext($user, $items, [
            'coupon_code' => 'TEST-COUPON',
        ]);

        $result = $this->calculator->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        // 验证基本结构，具体折扣取决于优惠券配置
        $this->assertIsString($result->getOriginalPrice());
        $this->assertIsString($result->getFinalPrice());
        $this->assertIsString($result->getDiscount());
    }
}