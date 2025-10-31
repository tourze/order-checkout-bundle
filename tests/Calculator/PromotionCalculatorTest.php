<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Calculator\PromotionCalculator;
use Tourze\OrderCheckoutBundle\Contract\PromotionMatcherInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\DTO\PromotionResult;

/**
 * @internal
 */
#[CoversClass(PromotionCalculator::class)]
final class PromotionCalculatorTest extends TestCase
{
    public function testPromotionCalculatorCanBeInstantiated(): void
    {
        $calculator = new PromotionCalculator();

        $this->assertInstanceOf(PromotionCalculator::class, $calculator);
    }

    public function testCalculateReturnsPromotionResult(): void
    {
        $calculator = new PromotionCalculator();
        $context = $this->createMock(CalculationContext::class);

        $result = $calculator->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
    }

    public function testAddMatcher(): void
    {
        $calculator = new PromotionCalculator();
        $matcher = $this->createMock(PromotionMatcherInterface::class);

        // 使用反射获取内部的 matchers 数组
        $reflection = new \ReflectionClass($calculator);
        $property = $reflection->getProperty('matchers');
        $property->setAccessible(true);

        // 添加前检查 matchers 为空
        $this->assertEmpty($property->getValue($calculator));

        $calculator->addMatcher($matcher);

        // 添加后检查 matchers 包含添加的 matcher
        $matchers = $property->getValue($calculator);
        $this->assertIsArray($matchers);
        $this->assertCount(1, $matchers);
        $this->assertSame($matcher, $matchers[0]);
    }

    public function testSupportsWithEmptyMatchers(): void
    {
        $calculator = new PromotionCalculator();
        $context = $this->createMock(CalculationContext::class);
        $context->method('getItems')->willReturn(['some_item']);

        $this->assertFalse($calculator->supports($context));
    }

    public function testSupportsWithMatchersAndItems(): void
    {
        $calculator = new PromotionCalculator();
        $matcher = $this->createMock(PromotionMatcherInterface::class);
        $calculator->addMatcher($matcher);

        $context = $this->createMock(CalculationContext::class);
        $context->method('getItems')->willReturn(['some_item']);

        $this->assertTrue($calculator->supports($context));
    }

    public function testSupportsWithMatchersButEmptyItems(): void
    {
        $calculator = new PromotionCalculator();
        $matcher = $this->createMock(PromotionMatcherInterface::class);
        $calculator->addMatcher($matcher);

        $context = $this->createMock(CalculationContext::class);
        $context->method('getItems')->willReturn([]);

        $this->assertFalse($calculator->supports($context));
    }

    public function testGetPriority(): void
    {
        $calculator = new PromotionCalculator();

        $this->assertSame(800, $calculator->getPriority());
    }

    public function testGetType(): void
    {
        $calculator = new PromotionCalculator();

        $this->assertSame('promotion', $calculator->getType());
    }
}
