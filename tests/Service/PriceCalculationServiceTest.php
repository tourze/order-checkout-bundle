<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\Service\PriceCalculationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(PriceCalculationService::class)]
#[RunTestsInSeparateProcesses]
final class PriceCalculationServiceTest extends AbstractIntegrationTestCase
{
    private PriceCalculationService $priceCalculationService;

    protected function onSetUp(): void
    {
        $this->priceCalculationService = self::getService(PriceCalculationService::class);
    }

    public function testPriceCalculationServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(PriceCalculationService::class, $this->priceCalculationService);
    }

    public function testGetCalculatorsReturnsArray(): void
    {
        $calculators = $this->priceCalculationService->getCalculators();

        $this->assertIsArray($calculators);
    }

    public function testCalculateWithEmptyContext(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $context = new CalculationContext($user, [], []);

        $result = $this->priceCalculationService->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        // 验证基本结构
        $this->assertSame('0.00', $result->getOriginalPrice());
        $this->assertSame('0.00', $result->getFinalPrice());
    }

    public function testCalculateWithValidContext(): void
    {
        // 跳过测试：此测试需要真实 SKU 数据，BasePriceCalculator 会查询数据库
        // 集成测试应在 ProcessCheckoutProcedureTest 中进行完整测试
        self::markTestSkipped('需要真实 SKU 数据，跳过集成测试');
    }

    public function testAddCalculator(): void
    {
        // 创建一个测试用的计算器
        $calculator = new class implements \Tourze\OrderCheckoutBundle\Contract\PriceCalculatorInterface {
            public function getType(): string
            {
                return 'test';
            }

            public function getPriority(): int
            {
                return 100;
            }

            public function supports(\Tourze\OrderCheckoutBundle\DTO\CalculationContext $context): bool
            {
                return true;
            }

            public function calculate(\Tourze\OrderCheckoutBundle\DTO\CalculationContext $context): \Tourze\OrderCheckoutBundle\DTO\PriceResult
            {
                return \Tourze\OrderCheckoutBundle\DTO\PriceResult::empty();
            }
        };

        $initialCount = count($this->priceCalculationService->getCalculators());
        $this->priceCalculationService->addCalculator($calculator);
        $newCount = count($this->priceCalculationService->getCalculators());

        $this->assertSame($initialCount + 1, $newCount);
    }

    public function testGetCalculatorByType(): void
    {
        // 创建一个测试用的计算器
        $calculator = new class implements \Tourze\OrderCheckoutBundle\Contract\PriceCalculatorInterface {
            public function getType(): string
            {
                return 'test_type';
            }

            public function getPriority(): int
            {
                return 100;
            }

            public function supports(\Tourze\OrderCheckoutBundle\DTO\CalculationContext $context): bool
            {
                return true;
            }

            public function calculate(\Tourze\OrderCheckoutBundle\DTO\CalculationContext $context): \Tourze\OrderCheckoutBundle\DTO\PriceResult
            {
                return \Tourze\OrderCheckoutBundle\DTO\PriceResult::empty();
            }
        };

        $this->priceCalculationService->addCalculator($calculator);
        $found = $this->priceCalculationService->getCalculatorByType('test_type');

        $this->assertNotNull($found);
        $this->assertSame('test_type', $found->getType());
    }

    public function testGetCalculatorByTypeReturnsNullForNonExistent(): void
    {
        $found = $this->priceCalculationService->getCalculatorByType('non_existent');

        $this->assertNull($found);
    }
}