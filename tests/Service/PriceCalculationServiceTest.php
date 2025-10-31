<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\OrderCheckoutBundle\Contract\PriceCalculatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationFailureException;
use Tourze\OrderCheckoutBundle\Service\PriceCalculationService;

/**
 * @internal
 */
#[CoversClass(PriceCalculationService::class)]
final class PriceCalculationServiceTest extends TestCase
{
    private PriceCalculationService $priceCalculationService;

    protected function setUp(): void
    {
        $this->priceCalculationService = new PriceCalculationService();
    }

    public function testPriceCalculationServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(PriceCalculationService::class, $this->priceCalculationService);
    }

    public function testAddCalculator(): void
    {
        $calculator = $this->createMock(PriceCalculatorInterface::class);
        $calculator->method('getPriority')->willReturn(10);

        $this->priceCalculationService->addCalculator($calculator);

        $calculators = $this->priceCalculationService->getCalculators();
        $this->assertCount(1, $calculators);
        $this->assertSame($calculator, $calculators[0]);
    }

    public function testAddMultipleCalculatorsWithPrioritySorting(): void
    {
        $calculator1 = $this->createMock(PriceCalculatorInterface::class);
        $calculator1->method('getPriority')->willReturn(10);
        $calculator1->method('getType')->willReturn('type1');

        $calculator2 = $this->createMock(PriceCalculatorInterface::class);
        $calculator2->method('getPriority')->willReturn(20);
        $calculator2->method('getType')->willReturn('type2');

        $calculator3 = $this->createMock(PriceCalculatorInterface::class);
        $calculator3->method('getPriority')->willReturn(15);
        $calculator3->method('getType')->willReturn('type3');

        $this->priceCalculationService->addCalculator($calculator1);
        $this->priceCalculationService->addCalculator($calculator2);
        $this->priceCalculationService->addCalculator($calculator3);

        $calculators = $this->priceCalculationService->getCalculators();
        $this->assertCount(3, $calculators);
        $this->assertSame($calculator2, $calculators[0]);
        $this->assertSame($calculator3, $calculators[1]);
        $this->assertSame($calculator1, $calculators[2]);
    }

    public function testCalculateWithEmptyItems(): void
    {
        $user = $this->createMock(UserInterface::class);
        $context = new CalculationContext($user, [], [], []);

        $result = $this->priceCalculationService->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertEquals(0.0, $result->getFinalPrice());
    }

    public function testCalculateWithNoSupportingCalculators(): void
    {
        $calculator = $this->createMock(PriceCalculatorInterface::class);
        $calculator->method('getPriority')->willReturn(10);
        $calculator->method('supports')->willReturn(false);

        $this->priceCalculationService->addCalculator($calculator);

        $user = $this->createMock(UserInterface::class);
        $checkoutItems = [new CheckoutItem(1, 1)];
        $context = new CalculationContext($user, $checkoutItems, [], []);

        $result = $this->priceCalculationService->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertEquals(0.0, $result->getFinalPrice());
    }

    public function testCalculateWithSupportingCalculator(): void
    {
        $calculator = $this->createMock(PriceCalculatorInterface::class);
        $calculator->method('getPriority')->willReturn(10);
        $calculator->method('supports')->willReturn(true);

        $calculatorResult = PriceResult::empty();
        $calculator->method('calculate')->willReturn($calculatorResult);

        $this->priceCalculationService->addCalculator($calculator);

        $user = $this->createMock(UserInterface::class);
        $checkoutItems = [new CheckoutItem(1, 1)];
        $context = new CalculationContext($user, $checkoutItems, [], []);

        $result = $this->priceCalculationService->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertEquals(0.0, $result->getFinalPrice());
    }

    public function testCalculateWithCalculatorException(): void
    {
        $calculator = $this->createMock(PriceCalculatorInterface::class);
        $calculator->method('getPriority')->willReturn(10);
        $calculator->method('supports')->willReturn(true);
        $calculator->method('getType')->willReturn('test_calculator');
        $calculator->method('calculate')->willThrowException(new \RuntimeException('Calculation failed'));

        $this->priceCalculationService->addCalculator($calculator);

        $user = $this->createMock(UserInterface::class);
        $checkoutItems = [new CheckoutItem(1, 1)];
        $context = new CalculationContext($user, $checkoutItems, [], []);

        $this->expectException(PriceCalculationFailureException::class);
        $this->expectExceptionMessage('价格计算器 test_calculator 执行失败: Calculation failed');

        $this->priceCalculationService->calculate($context);
    }

    public function testCalculateWithMultipleCalculators(): void
    {
        $calculator1 = $this->createMock(PriceCalculatorInterface::class);
        $calculator1->method('getPriority')->willReturn(20);
        $calculator1->method('supports')->willReturn(true);
        $calculator1->method('getType')->willReturn('base');

        $priceResult1 = new PriceResult(100.0, 100.0, 0.0, ['subtotal' => 100]);
        $calculator1->method('calculate')->willReturn($priceResult1);

        $calculator2 = $this->createMock(PriceCalculatorInterface::class);
        $calculator2->method('getPriority')->willReturn(10);
        $calculator2->method('supports')->willReturn(true);
        $calculator2->method('getType')->willReturn('promotion');

        $priceResult2 = new PriceResult(0.0, 80.0, 20.0, ['discount' => 20]);
        $calculator2->method('calculate')->willReturn($priceResult2);

        $this->priceCalculationService->addCalculator($calculator1);
        $this->priceCalculationService->addCalculator($calculator2);

        $user = $this->createMock(UserInterface::class);
        $checkoutItems = [new CheckoutItem(1, 1)];
        $context = new CalculationContext($user, $checkoutItems, [], []);

        $result = $this->priceCalculationService->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertEquals(100.0, $result->getOriginalPrice());
        $this->assertEquals(20.0, $result->getDiscount());
        $this->assertEquals(180.0, $result->getFinalPrice());
    }

    public function testGetCalculatorByType(): void
    {
        $calculator1 = $this->createMock(PriceCalculatorInterface::class);
        $calculator1->method('getPriority')->willReturn(10);
        $calculator1->method('getType')->willReturn('base');

        $calculator2 = $this->createMock(PriceCalculatorInterface::class);
        $calculator2->method('getPriority')->willReturn(20);
        $calculator2->method('getType')->willReturn('promotion');

        $this->priceCalculationService->addCalculator($calculator1);
        $this->priceCalculationService->addCalculator($calculator2);

        $foundCalculator = $this->priceCalculationService->getCalculatorByType('promotion');
        $this->assertSame($calculator2, $foundCalculator);

        $notFoundCalculator = $this->priceCalculationService->getCalculatorByType('nonexistent');
        $this->assertNull($notFoundCalculator);
    }

    public function testGetCalculators(): void
    {
        $calculator = $this->createMock(PriceCalculatorInterface::class);
        $calculator->method('getPriority')->willReturn(10);

        $this->priceCalculationService->addCalculator($calculator);

        $calculators = $this->priceCalculationService->getCalculators();
        $this->assertCount(1, $calculators);
        $this->assertSame($calculator, $calculators[0]);
    }

    public function testCalculateWithMixedCalculators(): void
    {
        // 测试部分支持、部分不支持的计算器组合
        $supportedCalculator = $this->createMock(PriceCalculatorInterface::class);
        $supportedCalculator->method('getPriority')->willReturn(10);
        $supportedCalculator->method('supports')->willReturn(true);
        $supportedCalculator->method('getType')->willReturn('supported');
        $supportedCalculator->method('calculate')->willReturn(new PriceResult(50.0, 45.0, 5.0, ['supported' => true]));

        $unsupportedCalculator = $this->createMock(PriceCalculatorInterface::class);
        $unsupportedCalculator->method('getPriority')->willReturn(20);
        $unsupportedCalculator->method('supports')->willReturn(false);
        $unsupportedCalculator->method('getType')->willReturn('unsupported');
        $unsupportedCalculator->expects($this->never())->method('calculate');

        $this->priceCalculationService->addCalculator($supportedCalculator);
        $this->priceCalculationService->addCalculator($unsupportedCalculator);

        $user = $this->createMock(UserInterface::class);
        $checkoutItems = [new CheckoutItem(1, 1)];
        $context = new CalculationContext($user, $checkoutItems, [], []);

        $result = $this->priceCalculationService->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertEquals(50.0, $result->getOriginalPrice());
        $this->assertEquals(45.0, $result->getFinalPrice());
        $this->assertEquals(5.0, $result->getDiscount());
    }

    public function testCalculateWithNestedExceptions(): void
    {
        $calculator1 = $this->createMock(PriceCalculatorInterface::class);
        $calculator1->method('getPriority')->willReturn(10);
        $calculator1->method('supports')->willReturn(true);
        $calculator1->method('getType')->willReturn('failing_calculator');

        // 模拟嵌套异常
        $innerException = new \InvalidArgumentException('参数错误');
        $calculator1->method('calculate')->willThrowException($innerException);

        $this->priceCalculationService->addCalculator($calculator1);

        $user = $this->createMock(UserInterface::class);
        $checkoutItems = [new CheckoutItem(1, 1)];
        $context = new CalculationContext($user, $checkoutItems, [], []);

        try {
            $this->priceCalculationService->calculate($context);
            self::fail('应该抛出 PriceCalculationFailureException');
        } catch (PriceCalculationFailureException $e) {
            $this->assertEquals('价格计算器 failing_calculator 执行失败: 参数错误', $e->getMessage());
            $this->assertSame($innerException, $e->getPrevious());
        }
    }

    public function testCalculateWithComplexPriceResultMerge(): void
    {
        // 测试复杂的价格结果合并
        $baseCalculator = $this->createMock(PriceCalculatorInterface::class);
        $baseCalculator->method('getPriority')->willReturn(30);
        $baseCalculator->method('supports')->willReturn(true);
        $baseCalculator->method('getType')->willReturn('base');
        $baseCalculator->method('calculate')->willReturn(
            new PriceResult(100.0, 100.0, 0.0, ['base_price' => 100, 'items_count' => 2])
        );

        $discountCalculator = $this->createMock(PriceCalculatorInterface::class);
        $discountCalculator->method('getPriority')->willReturn(20);
        $discountCalculator->method('supports')->willReturn(true);
        $discountCalculator->method('getType')->willReturn('discount');
        $discountCalculator->method('calculate')->willReturn(
            new PriceResult(0.0, 85.0, 15.0, ['discount_type' => 'percentage', 'discount_rate' => 15])
        );

        $taxCalculator = $this->createMock(PriceCalculatorInterface::class);
        $taxCalculator->method('getPriority')->willReturn(10);
        $taxCalculator->method('supports')->willReturn(true);
        $taxCalculator->method('getType')->willReturn('tax');
        $taxCalculator->method('calculate')->willReturn(
            new PriceResult(8.5, 93.5, 0.0, ['tax_rate' => 0.1, 'tax_amount' => 8.5])
        );

        $this->priceCalculationService->addCalculator($baseCalculator);
        $this->priceCalculationService->addCalculator($discountCalculator);
        $this->priceCalculationService->addCalculator($taxCalculator);

        $user = $this->createMock(UserInterface::class);
        $checkoutItems = [new CheckoutItem(1, 1)];
        $context = new CalculationContext($user, $checkoutItems, ['DISCOUNT15'], []);

        $result = $this->priceCalculationService->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertEquals(108.5, $result->getOriginalPrice()); // 100 + 0 + 8.5
        $this->assertEquals(278.5, $result->getFinalPrice()); // 100 + 85 + 93.5
        $this->assertEquals(15.0, $result->getDiscount());

        $details = $result->getDetails();
        $this->assertArrayHasKey('base_price', $details);
        $this->assertArrayHasKey('discount_type', $details);
        $this->assertArrayHasKey('tax_amount', $details);
    }

    public function testCalculateWithEmptyCalculatorsList(): void
    {
        // 没有添加任何计算器的情况
        $user = $this->createMock(UserInterface::class);
        $checkoutItems = [new CheckoutItem(1, 1)];
        $context = new CalculationContext($user, $checkoutItems, [], []);

        $result = $this->priceCalculationService->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertEquals(0.0, $result->getFinalPrice());
        $this->assertEquals(0.0, $result->getOriginalPrice());
        $this->assertEquals(0.0, $result->getDiscount());
        $this->assertEmpty($result->getDetails());
    }

    public function testGetCalculatorByTypeWithMultipleCalculators(): void
    {
        $calc1 = $this->createMock(PriceCalculatorInterface::class);
        $calc1->method('getPriority')->willReturn(10);
        $calc1->method('getType')->willReturn('base');

        $calc2 = $this->createMock(PriceCalculatorInterface::class);
        $calc2->method('getPriority')->willReturn(20);
        $calc2->method('getType')->willReturn('promotion');

        $calc3 = $this->createMock(PriceCalculatorInterface::class);
        $calc3->method('getPriority')->willReturn(15);
        $calc3->method('getType')->willReturn('base'); // 与 calc1 相同类型

        $this->priceCalculationService->addCalculator($calc1);
        $this->priceCalculationService->addCalculator($calc2);
        $this->priceCalculationService->addCalculator($calc3);

        // 应该返回第一个匹配的计算器
        $foundCalculator = $this->priceCalculationService->getCalculatorByType('base');
        $this->assertSame($calc3, $foundCalculator); // 因为 calc3 优先级更高，排在前面

        $promotionCalculator = $this->priceCalculationService->getCalculatorByType('promotion');
        $this->assertSame($calc2, $promotionCalculator);
    }

    public function testCalculateWithPriorityExecution(): void
    {
        // 测试计算器按优先级正确执行
        $executionOrder = [];

        $lowPriorityCalc = $this->createMock(PriceCalculatorInterface::class);
        $lowPriorityCalc->method('getPriority')->willReturn(5);
        $lowPriorityCalc->method('supports')->willReturn(true);
        $lowPriorityCalc->method('getType')->willReturn('low');
        $lowPriorityCalc->method('calculate')->willReturnCallback(function () use (&$executionOrder) {
            $executionOrder[] = 'low';

            return PriceResult::empty();
        });

        $highPriorityCalc = $this->createMock(PriceCalculatorInterface::class);
        $highPriorityCalc->method('getPriority')->willReturn(25);
        $highPriorityCalc->method('supports')->willReturn(true);
        $highPriorityCalc->method('getType')->willReturn('high');
        $highPriorityCalc->method('calculate')->willReturnCallback(function () use (&$executionOrder) {
            $executionOrder[] = 'high';

            return PriceResult::empty();
        });

        $mediumPriorityCalc = $this->createMock(PriceCalculatorInterface::class);
        $mediumPriorityCalc->method('getPriority')->willReturn(15);
        $mediumPriorityCalc->method('supports')->willReturn(true);
        $mediumPriorityCalc->method('getType')->willReturn('medium');
        $mediumPriorityCalc->method('calculate')->willReturnCallback(function () use (&$executionOrder) {
            $executionOrder[] = 'medium';

            return PriceResult::empty();
        });

        // 故意不按优先级顺序添加
        $this->priceCalculationService->addCalculator($lowPriorityCalc);
        $this->priceCalculationService->addCalculator($highPriorityCalc);
        $this->priceCalculationService->addCalculator($mediumPriorityCalc);

        $user = $this->createMock(UserInterface::class);
        $checkoutItems = [new CheckoutItem(1, 1)];
        $context = new CalculationContext($user, $checkoutItems, [], []);

        $this->priceCalculationService->calculate($context);

        // 验证执行顺序（高优先级先执行）
        $this->assertEquals(['high', 'medium', 'low'], $executionOrder);
    }

    public function testAddCalculatorMaintainsSortOrder(): void
    {
        $calc1 = $this->createMock(PriceCalculatorInterface::class);
        $calc1->method('getPriority')->willReturn(10);
        $calc1->method('getType')->willReturn('calc1');

        $calc2 = $this->createMock(PriceCalculatorInterface::class);
        $calc2->method('getPriority')->willReturn(30);
        $calc2->method('getType')->willReturn('calc2');

        $calc3 = $this->createMock(PriceCalculatorInterface::class);
        $calc3->method('getPriority')->willReturn(20);
        $calc3->method('getType')->willReturn('calc3');

        // 添加后应该自动按优先级排序
        $this->priceCalculationService->addCalculator($calc1);
        $calculators = $this->priceCalculationService->getCalculators();
        $this->assertEquals(['calc1'], array_map(fn ($c) => $c->getType(), $calculators));

        $this->priceCalculationService->addCalculator($calc2);
        $calculators = $this->priceCalculationService->getCalculators();
        $this->assertEquals(['calc2', 'calc1'], array_map(fn ($c) => $c->getType(), $calculators));

        $this->priceCalculationService->addCalculator($calc3);
        $calculators = $this->priceCalculationService->getCalculators();
        $this->assertEquals(['calc2', 'calc3', 'calc1'], array_map(fn ($c) => $c->getType(), $calculators));
    }
}
