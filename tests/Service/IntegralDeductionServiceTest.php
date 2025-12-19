<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use OrderCoreBundle\Entity\Contract;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\IntegralServiceContracts\DTO\IntegralAccountDTO;
use Tourze\IntegralServiceContracts\DTO\Request\DecreaseIntegralRequest;
use Tourze\IntegralServiceContracts\Exception\GenericIntegralException;
use Tourze\IntegralServiceContracts\Exception\InsufficientBalanceException;
use Tourze\IntegralServiceContracts\IntegralServiceInterface;
use Tourze\OrderCheckoutBundle\Service\IntegralDeductionService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * IntegralDeductionService 集成测试
 *
 * 采用混合测试策略：
 * - 从容器获取真实的 LoggerInterface
 * - Mock IntegralServiceInterface 以测试不同场景
 *
 * @internal
 */
#[CoversClass(IntegralDeductionService::class)]
#[RunTestsInSeparateProcesses]
final class IntegralDeductionServiceTest extends AbstractIntegrationTestCase
{
    private IntegralDeductionService $service;
    private LoggerInterface $logger;

    protected function onSetUp(): void
    {
        // 注意：IntegralServiceInterface 可能为 null，这是正常情况
        $integralService = self::getContainer()->has(IntegralServiceInterface::class)
            ? self::getService(IntegralServiceInterface::class)
            : null;

        $this->logger = self::getService(LoggerInterface::class);
        // 为了测试不同场景，使用 Mock 实例而非容器服务
        $this->service = $this->createIntegralDeductionService($this->logger, $integralService);
    }

    /**
     * 创建 IntegralDeductionService 实例
     * 直接构造新实例，而非从容器获取，以确保使用指定的 Mock 外部服务依赖
     */
    private function createIntegralDeductionService(LoggerInterface $logger, ?IntegralServiceInterface $integralService = null): IntegralDeductionService
    {
        return new IntegralDeductionService($logger, $integralService); // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass
    }

    public function testIntegralDeductionServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(IntegralDeductionService::class, $this->service);
    }

    public function testDeductIntegralWithNullIntegralService(): void
    {
        // 当 IntegralService 为 null 时，应该记录警告并提前返回
        $service = $this->createIntegralDeductionService($this->logger, null);
        $contract = $this->createMock(Contract::class);

        // 不应抛出异常
        $service->deductIntegral($contract, 'user123', 100);

        // 验证方法正常完成（无异常）
        $this->assertTrue(true);
    }

    public function testDeductIntegralWithAccountNotExists(): void
    {
        $integralService = $this->createMock(IntegralServiceInterface::class);
        $integralService->expects($this->once())
            ->method('getIntegralAccount')
            ->with('user123')
            ->willReturn(null);
        $service = $this->createIntegralDeductionService($this->logger, $integralService);
        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(1);
        $contract->method('getSn')->willReturn('ORDER001');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('用户 user123 的积分账户不存在');

        $service->deductIntegral($contract, 'user123', 100);
    }

    public function testDeductIntegralWithInsufficientBalance(): void
    {
        $integralAccount = new IntegralAccountDTO(
            id: 1,
            userIdentifier: 'user123',
            totalIntegral: 50,
            availableIntegral: 50,
            frozenIntegral: 0,
            grandTotalIntegral: 50
        );

        $integralService = $this->createMock(IntegralServiceInterface::class);
        $integralService->expects($this->once())
            ->method('getIntegralAccount')
            ->with('user123')
            ->willReturn($integralAccount);
        $service = $this->createIntegralDeductionService($this->logger, $integralService);
        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(1);
        $contract->method('getSn')->willReturn('ORDER001');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('积分不足：需要 100，当前可用 50');

        $service->deductIntegral($contract, 'user123', 100);
    }

    public function testDeductIntegralWithInsufficientBalanceException(): void
    {
        // 账户有足够余额，但 IntegralService 抛出异常（例如并发扣除导致余额不足）
        $integralAccount = new IntegralAccountDTO(
            id: 1,
            userIdentifier: 'user123',
            totalIntegral: 200,
            availableIntegral: 200,
            frozenIntegral: 0,
            grandTotalIntegral: 200
        );

        $integralService = $this->createMock(IntegralServiceInterface::class);
        $integralService->expects($this->once())
            ->method('getIntegralAccount')
            ->with('user123')
            ->willReturn($integralAccount);

        $exception = new InsufficientBalanceException(
            userIdentifier: 'user123',
            required: 100,
            available: 50
        );

        $integralService->expects($this->once())
            ->method('decreaseIntegral')
            ->willThrowException($exception);
        $service = $this->createIntegralDeductionService($this->logger, $integralService);
        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(1);
        $contract->method('getSn')->willReturn('ORDER001');

        try {
            $service->deductIntegral($contract, 'user123', 100);
            self::fail('应该抛出 RuntimeException');
        } catch (\RuntimeException $e) {
            // 验证异常消息来自 InsufficientBalanceException
            $this->assertStringContainsString('100', $e->getMessage());
            $this->assertStringContainsString('50', $e->getMessage());
            $this->assertInstanceOf(InsufficientBalanceException::class, $e->getPrevious());
        }
    }

    public function testDeductIntegralWithServiceException(): void
    {
        $integralAccount = new IntegralAccountDTO(
            id: 1,
            userIdentifier: 'user123',
            totalIntegral: 200,
            availableIntegral: 200,
            frozenIntegral: 0,
            grandTotalIntegral: 200
        );

        $integralService = $this->createMock(IntegralServiceInterface::class);
        $integralService->expects($this->once())
            ->method('getIntegralAccount')
            ->with('user123')
            ->willReturn($integralAccount);

        $exception = new GenericIntegralException('积分服务不可用', 'SERVICE_UNAVAILABLE');

        $integralService->expects($this->once())
            ->method('decreaseIntegral')
            ->willThrowException($exception);
        $service = $this->createIntegralDeductionService($this->logger, $integralService);
        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(1);
        $contract->method('getSn')->willReturn('ORDER001');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('积分服务异常，请稍后重试');

        $service->deductIntegral($contract, 'user123', 100);
    }

    public function testDeductIntegralSuccessfully(): void
    {
        $integralAccount = new IntegralAccountDTO(
            id: 1,
            userIdentifier: 'user123',
            totalIntegral: 500,
            availableIntegral: 500,
            frozenIntegral: 0,
            grandTotalIntegral: 500
        );

        $integralService = $this->createMock(IntegralServiceInterface::class);
        $integralService->expects($this->once())
            ->method('getIntegralAccount')
            ->with('user123')
            ->willReturn($integralAccount);

        $integralService->expects($this->once())
            ->method('decreaseIntegral')
            ->with(self::callback(function (DecreaseIntegralRequest $request) {
                return 'user123' === $request->userIdentifier
                    && 100 === $request->changeValue
                    && str_contains($request->changeReason, '订单支付')
                    && 'order' === $request->sourceType;
            }));
        $service = $this->createIntegralDeductionService($this->logger, $integralService);
        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(1);
        $contract->method('getSn')->willReturn('ORDER001');

        // 不应抛出异常
        $service->deductIntegral($contract, 'user123', 100);

        // 验证方法正常完成
        $this->assertTrue(true);
    }

    public function testDeductIntegralVerifiesSourceId(): void
    {
        $integralAccount = new IntegralAccountDTO(
            id: 1,
            userIdentifier: 'user123',
            totalIntegral: 500,
            availableIntegral: 500,
            frozenIntegral: 0,
            grandTotalIntegral: 500
        );

        $integralService = $this->createMock(IntegralServiceInterface::class);
        $integralService->expects($this->once())
            ->method('getIntegralAccount')
            ->willReturn($integralAccount);

        $integralService->expects($this->once())
            ->method('decreaseIntegral')
            ->with(self::callback(function (DecreaseIntegralRequest $request) {
                // 验证 sourceId 格式为 {orderSn}-deduct
                return str_ends_with($request->sourceId, '-deduct')
                    && str_contains($request->sourceId, 'ORDER002');
            }));
        $service = $this->createIntegralDeductionService($this->logger, $integralService);
        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(2);
        $contract->method('getSn')->willReturn('ORDER002');

        $service->deductIntegral($contract, 'user123', 150);

        $this->assertTrue(true);
    }

    public function testDeductIntegralWithExactBalance(): void
    {
        // 测试余额正好等于扣除金额的边界情况
        $integralAccount = new IntegralAccountDTO(
            id: 1,
            userIdentifier: 'user123',
            totalIntegral: 100,
            availableIntegral: 100,
            frozenIntegral: 0,
            grandTotalIntegral: 100
        );

        $integralService = $this->createMock(IntegralServiceInterface::class);
        $integralService->expects($this->once())
            ->method('getIntegralAccount')
            ->willReturn($integralAccount);

        $integralService->expects($this->once())
            ->method('decreaseIntegral');
        $service = $this->createIntegralDeductionService($this->logger, $integralService);
        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(3);
        $contract->method('getSn')->willReturn('ORDER003');

        // 不应抛出异常
        $service->deductIntegral($contract, 'user123', 100);

        $this->assertTrue(true);
    }

}
