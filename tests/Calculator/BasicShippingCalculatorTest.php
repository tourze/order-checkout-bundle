<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Calculator\BasicShippingCalculator;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\ShippingContext;
use Tourze\OrderCheckoutBundle\DTO\ShippingResult;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(BasicShippingCalculator::class)]
#[RunTestsInSeparateProcesses]
final class BasicShippingCalculatorTest extends AbstractIntegrationTestCase
{
    private BasicShippingCalculator $calculator;

    protected function onSetUp(): void
    {
        $this->calculator = self::getService(BasicShippingCalculator::class);
    }

    public function testCalculatorCanBeInstantiated(): void
    {
        $this->assertInstanceOf(BasicShippingCalculator::class, $this->calculator);
    }

    public function testSupports(): void
    {
        $items = [
            new CheckoutItem('test-sku-1', 1, true),
        ];
        $this->assertTrue($this->calculator->supports($items, 'CN'));
    }

    public function testCalculateWithEmptyItems(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $context = new ShippingContext($user, [], 'CN', []);

        $result = $this->calculator->calculate($context);

        $this->assertInstanceOf(ShippingResult::class, $result);
        // 空购物车总价值为 0，不满足免邮条件（99元），会收取默认运费
        $this->assertSame(12.0, $result->getShippingFee());
        $this->assertFalse($result->isFreeShipping());
    }

    public function testGetPriority(): void
    {
        $this->assertSame(100, $this->calculator->getPriority());
    }

    public function testGetType(): void
    {
        $this->assertSame('basic_shipping', $this->calculator->getType());
    }

    public function testCalculateWithItems(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $items = [
            new CheckoutItem('test-sku-1', 1, true),
            new CheckoutItem('test-sku-2', 2, true),
        ];

        $context = new ShippingContext($user, $items, 'CN', []);

        $result = $this->calculator->calculate($context);

        $this->assertInstanceOf(ShippingResult::class, $result);
        // 验证基本结构，具体值取决于配置
        $this->assertIsFloat($result->getShippingFee());
    }
}