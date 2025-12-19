<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Calculator\PromotionCalculator;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(PromotionCalculator::class)]
#[RunTestsInSeparateProcesses]
final class PromotionCalculatorTest extends AbstractIntegrationTestCase
{
    private PromotionCalculator $calculator;

    protected function onSetUp(): void
    {
        $this->calculator = self::getService(PromotionCalculator::class);
    }

    public function testCalculatorCanBeInstantiated(): void
    {
        $this->assertInstanceOf(PromotionCalculator::class, $this->calculator);
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
        $context = new CalculationContext($user, $items, []);

        // 没有 matchers 时返回 false
        $this->assertFalse($this->calculator->supports($context));

        // 添加一个 matcher 后返回 true
        $matcher = $this->createMock(\Tourze\OrderCheckoutBundle\Contract\PromotionMatcherInterface::class);
        $matcher->method('getPriority')->willReturn(100);
        $this->calculator->addMatcher($matcher);

        $this->assertTrue($this->calculator->supports($context));
    }

    public function testGetPriority(): void
    {
        $this->assertSame(800, $this->calculator->getPriority());
    }

    public function testGetType(): void
    {
        $this->assertSame('promotion', $this->calculator->getType());
    }

    public function testCalculateWithEmptyItems(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $context = new CalculationContext($user, [], []);

        $result = $this->calculator->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertSame('0.00', $result->getOriginalPrice());
        $this->assertSame('0.00', $result->getFinalPrice());
        $this->assertSame('0.00', $result->getDiscount());
    }

    public function testCalculateWithItems(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $items = [
            new CheckoutItem('test-sku-1', 2, true),
            new CheckoutItem('test-sku-2', 1, true),
        ];
        $context = new CalculationContext($user, $items, []);

        $result = $this->calculator->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        // 验证基本结构，具体折扣取决于促销配置
        $this->assertIsString($result->getOriginalPrice());
        $this->assertIsString($result->getFinalPrice());
        $this->assertIsString($result->getDiscount());
    }

    public function testAddMatcher(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $items = [new CheckoutItem('test-sku-1', 1, true)];
        $context = new CalculationContext($user, $items, []);

        // 创建测试用的 matcher
        $matcher1 = $this->createMock(\Tourze\OrderCheckoutBundle\Contract\PromotionMatcherInterface::class);
        $matcher1->method('getPriority')->willReturn(100);
        $matcher1->method('supports')->willReturn(true);
        $matcher1->method('match')->willReturn(\Tourze\OrderCheckoutBundle\DTO\PromotionResult::empty());

        $matcher2 = $this->createMock(\Tourze\OrderCheckoutBundle\Contract\PromotionMatcherInterface::class);
        $matcher2->method('getPriority')->willReturn(200);
        $matcher2->method('supports')->willReturn(true);
        $matcher2->method('match')->willReturn(\Tourze\OrderCheckoutBundle\DTO\PromotionResult::empty());

        // 添加 matcher
        $this->calculator->addMatcher($matcher1);
        $this->calculator->addMatcher($matcher2);

        // 验证 supports 返回 true（因为有 matcher 且有 items）
        $this->assertTrue($this->calculator->supports($context));

        // 验证可以计算（不抛出异常）
        $result = $this->calculator->calculate($context);
        $this->assertInstanceOf(PriceResult::class, $result);
    }
}