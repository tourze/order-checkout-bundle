<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderPrice;
use OrderCoreBundle\Entity\OrderProduct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\IntegralServiceContracts\DTO\IntegralAccountDTO;
use Tourze\IntegralServiceContracts\Exception\ServiceUnavailableException;
use Tourze\IntegralServiceContracts\IntegralServiceInterface;
use Tourze\OrderCheckoutBundle\Entity\OrderIntegralInfo;
use Tourze\OrderCheckoutBundle\Repository\OrderIntegralInfoRepository;
use Tourze\OrderCheckoutBundle\Service\IntegralRecordService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * IntegralRecordService 集成测试
 *
 * 采用混合测试策略：
 * - 从容器获取真实的数据库连接
 * - Mock IntegralServiceInterface 以避免外部依赖
 *
 * @internal
 */
#[CoversClass(IntegralRecordService::class)]
#[RunTestsInSeparateProcesses]
final class IntegralRecordServiceTest extends AbstractIntegrationTestCase
{
    private IntegralRecordService $service;

    private OrderIntegralInfoRepository $integralInfoRepository;

    private IntegralServiceInterface&MockObject $integralService;

    private LoggerInterface&MockObject $logger;

    protected function onSetUp(): void
    {
        // 先创建Mock服务
        $this->integralService = $this->createMock(IntegralServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // 从服务容器获取仓库
        $this->integralInfoRepository = self::getService(OrderIntegralInfoRepository::class);

        // 直接实例化服务以确保使用 Mock 外部服务依赖
        // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass
        $this->service = new IntegralRecordService(
            $this->logger,
            $this->integralService,
            self::getEntityManager(),
            $this->integralInfoRepository
        );

        // Clean up existing test data
        $this->clearIntegralInfoRecords();
    }

    protected function onTearDown(): void
    {
        parent::onTearDown();
    }

    
    private function clearIntegralInfoRecords(): void
    {
        $connection = self::getEntityManager()->getConnection();
        $connection->executeStatement('DELETE FROM order_integral_info');
    }

    public function testCreateIntegralInfoForProductWithPositiveIntegralPrice(): void
    {
        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(1001);
        $contract->method('getSn')->willReturn('ORDER-2024-001');

        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('12345');

        $product = $this->createMock(OrderProduct::class);
        $product->method('getId')->willReturn(501);
        $product->method('getIntegralPrice')->willReturn(100);
        $product->method('getIntegralDeductedTime')->willReturn(new \DateTimeImmutable('2024-01-15 10:00:00'));

        $integralAccount = new IntegralAccountDTO(
            id: 1,
            userIdentifier: '12345',
            totalIntegral: 400,
            availableIntegral: 400,
            frozenIntegral: 0,
            grandTotalIntegral: 500
        );

        $this->integralService
            ->expects($this->once())
            ->method('getIntegralAccount')
            ->with('12345')
            ->willReturn($integralAccount)
        ;

        $this->service->createIntegralInfoForProduct($contract, $user, $product);

        self::getEntityManager()->flush();

        // Verify the record was created
        $records = $this->integralInfoRepository->findBy(['orderId' => 1001]);
        $this->assertCount(1, $records);

        $record = $records[0];
        $this->assertSame(1001, $record->getOrderId());
        $this->assertSame(12345, $record->getUserId());
        $this->assertSame(501, $record->getProductId());
        $this->assertSame(100, $record->getIntegralRequired());
        $this->assertSame('ORDER-2024-001-product-501', $record->getIntegralOperationId());
        $this->assertSame(500, $record->getBalanceBefore());
        $this->assertSame(400, $record->getBalanceAfter());
        $this->assertFalse($record->isRefunded());
        $this->assertStringContainsString('订单 ORDER-2024-001 商品 501 扣除积分', $record->getRemark());
    }

    public function testCreateIntegralInfoForProductWithZeroIntegralPrice(): void
    {
        $contract = $this->createMock(Contract::class);
        $user = $this->createMock(UserInterface::class);

        $product = $this->createMock(OrderProduct::class);
        $product->method('getIntegralPrice')->willReturn(0);

        $this->integralService
            ->expects($this->never())
            ->method('getIntegralAccount')
        ;

        $this->service->createIntegralInfoForProduct($contract, $user, $product);

        self::getEntityManager()->flush();

        // Verify no record was created
        $records = $this->integralInfoRepository->findAll();
        $this->assertEmpty($records);
    }

    public function testCreateIntegralInfoForProductWithNullIntegralPrice(): void
    {
        $contract = $this->createMock(Contract::class);
        $user = $this->createMock(UserInterface::class);

        $product = $this->createMock(OrderProduct::class);
        $product->method('getIntegralPrice')->willReturn(null);

        $this->integralService
            ->expects($this->never())
            ->method('getIntegralAccount')
        ;

        $this->service->createIntegralInfoForProduct($contract, $user, $product);

        self::getEntityManager()->flush();

        // Verify no record was created
        $records = $this->integralInfoRepository->findAll();
        $this->assertEmpty($records);
    }

    public function testCreateIntegralInfoForProductWithNegativeIntegralPrice(): void
    {
        $contract = $this->createMock(Contract::class);
        $user = $this->createMock(UserInterface::class);

        $product = $this->createMock(OrderProduct::class);
        $product->method('getIntegralPrice')->willReturn(-50);

        $this->integralService
            ->expects($this->never())
            ->method('getIntegralAccount')
        ;

        $this->service->createIntegralInfoForProduct($contract, $user, $product);

        self::getEntityManager()->flush();

        // Verify no record was created
        $records = $this->integralInfoRepository->findAll();
        $this->assertEmpty($records);
    }

    /**
     * @deprecated Test for deprecated method - skipped due to final methods in OrderPrice
     */
    public function testCreateIntegralInfoForPriceWithPaidPrice(): void
    {
        self::markTestSkipped('Deprecated method with final methods in OrderPrice entity');
    }

    /**
     * @deprecated Test for deprecated method - skipped due to final methods in OrderPrice
     */
    public function testCreateIntegralInfoForPriceWithUnpaidPrice(): void
    {
        self::markTestSkipped('Deprecated method with final methods in OrderPrice entity');
    }

    public function testUpdateIntegralInfoRefundStatusByProduct(): void
    {
        // Create initial integral info record
        $integralInfo = new OrderIntegralInfo();
        $integralInfo->setOrderId(1003);
        $integralInfo->setUserId(11111);
        $integralInfo->setProductId(601);
        $integralInfo->setIntegralRequired(200);
        $integralInfo->setIntegralOperationId('ORDER-2024-003-product-601');
        $integralInfo->setDeductedTime(new \DateTimeImmutable('2024-01-15 10:00:00'));
        $integralInfo->setBalanceBefore(700);
        $integralInfo->setBalanceAfter(500);
        $integralInfo->setRemark('Test deduction');

        self::getEntityManager()->persist($integralInfo);
        self::getEntityManager()->flush();

        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(1003);
        $contract->method('getSn')->willReturn('ORDER-2024-003');

        $product = $this->createMock(OrderProduct::class);
        $product->method('getId')->willReturn(601);

        $this->service->updateIntegralInfoRefundStatusByProduct($contract, $product);

        // Verify the record was updated
        self::getEntityManager()->refresh($integralInfo);

        $this->assertTrue($integralInfo->isRefunded());
        $this->assertNotNull($integralInfo->getRefundedTime());
        $this->assertSame('ORDER-2024-003-refund-product-601', $integralInfo->getRefundOperationId());
    }

    public function testUpdateIntegralInfoRefundStatusByProductWithMultipleRecords(): void
    {
        // Create multiple integral info records for the same product
        $integralInfo1 = new OrderIntegralInfo();
        $integralInfo1->setOrderId(1004);
        $integralInfo1->setUserId(22222);
        $integralInfo1->setProductId(701);
        $integralInfo1->setIntegralRequired(100);
        $integralInfo1->setIntegralOperationId('ORDER-2024-004-product-701-1');
        $integralInfo1->setDeductedTime(new \DateTimeImmutable('2024-01-15 10:00:00'));
        $integralInfo1->setBalanceBefore(300);
        $integralInfo1->setBalanceAfter(200);

        $integralInfo2 = new OrderIntegralInfo();
        $integralInfo2->setOrderId(1004);
        $integralInfo2->setUserId(22222);
        $integralInfo2->setProductId(701);
        $integralInfo2->setIntegralRequired(50);
        $integralInfo2->setIntegralOperationId('ORDER-2024-004-product-701-2');
        $integralInfo2->setDeductedTime(new \DateTimeImmutable('2024-01-15 11:00:00'));
        $integralInfo2->setBalanceBefore(200);
        $integralInfo2->setBalanceAfter(150);

        self::getEntityManager()->persist($integralInfo1);
        self::getEntityManager()->persist($integralInfo2);
        self::getEntityManager()->flush();

        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(1004);
        $contract->method('getSn')->willReturn('ORDER-2024-004');

        $product = $this->createMock(OrderProduct::class);
        $product->method('getId')->willReturn(701);

        $this->service->updateIntegralInfoRefundStatusByProduct($contract, $product);

        // Verify both records were updated
        self::getEntityManager()->refresh($integralInfo1);
        self::getEntityManager()->refresh($integralInfo2);

        $this->assertTrue($integralInfo1->isRefunded());
        $this->assertTrue($integralInfo2->isRefunded());
        $this->assertSame('ORDER-2024-004-refund-product-701', $integralInfo1->getRefundOperationId());
        $this->assertSame('ORDER-2024-004-refund-product-701', $integralInfo2->getRefundOperationId());
    }

    public function testUpdateIntegralInfoRefundStatusByProductDoesNotUpdateAlreadyRefunded(): void
    {
        // Create an already refunded record
        $integralInfo = new OrderIntegralInfo();
        $integralInfo->setOrderId(1005);
        $integralInfo->setUserId(33333);
        $integralInfo->setProductId(801);
        $integralInfo->setIntegralRequired(75);
        $integralInfo->setIntegralOperationId('ORDER-2024-005-product-801');
        $integralInfo->setDeductedTime(new \DateTimeImmutable('2024-01-15 10:00:00'));
        $integralInfo->setBalanceBefore(200);
        $integralInfo->setBalanceAfter(125);
        $integralInfo->setIsRefunded(true);
        $integralInfo->setRefundedTime(new \DateTimeImmutable('2024-01-16 12:00:00'));
        $integralInfo->setRefundOperationId('ORIGINAL-REFUND-ID');

        self::getEntityManager()->persist($integralInfo);
        self::getEntityManager()->flush();

        $originalRefundTime = $integralInfo->getRefundedTime();

        $contract = $this->createMock(Contract::class);
        $contract->method('getId')->willReturn(1005);
        $contract->method('getSn')->willReturn('ORDER-2024-005');

        $product = $this->createMock(OrderProduct::class);
        $product->method('getId')->willReturn(801);

        $this->service->updateIntegralInfoRefundStatusByProduct($contract, $product);

        // Verify the record was not updated
        self::getEntityManager()->refresh($integralInfo);

        $this->assertTrue($integralInfo->isRefunded());
        $this->assertSame('ORIGINAL-REFUND-ID', $integralInfo->getRefundOperationId());
        $this->assertEquals($originalRefundTime, $integralInfo->getRefundedTime());
    }

    /**
     * @deprecated Test for deprecated method - skipped due to final methods in OrderPrice
     */
    public function testUpdateIntegralInfoRefundStatus(): void
    {
        self::markTestSkipped('Deprecated method with final methods in OrderPrice entity');
    }

    /**
     * @deprecated Test for deprecated method - skipped due to final methods in OrderPrice
     */
    public function testUpdateIntegralInfoRefundStatusWithNoMatch(): void
    {
        self::markTestSkipped('Deprecated method with final methods in OrderPrice entity');
    }

    public function testFindUnrefundedIntegralInfos(): void
    {
        // Create mixed refunded and unrefunded records
        $integralInfo1 = new OrderIntegralInfo();
        $integralInfo1->setOrderId(1008);
        $integralInfo1->setUserId(66666);
        $integralInfo1->setProductId(901);
        $integralInfo1->setIntegralRequired(100);
        $integralInfo1->setIntegralOperationId('ORDER-2024-008-product-901');
        $integralInfo1->setDeductedTime(new \DateTimeImmutable('2024-01-15 10:00:00'));
        $integralInfo1->setBalanceBefore(500);
        $integralInfo1->setBalanceAfter(400);

        $integralInfo2 = new OrderIntegralInfo();
        $integralInfo2->setOrderId(1008);
        $integralInfo2->setUserId(66666);
        $integralInfo2->setProductId(902);
        $integralInfo2->setIntegralRequired(50);
        $integralInfo2->setIntegralOperationId('ORDER-2024-008-product-902');
        $integralInfo2->setDeductedTime(new \DateTimeImmutable('2024-01-15 11:00:00'));
        $integralInfo2->setBalanceBefore(400);
        $integralInfo2->setBalanceAfter(350);
        $integralInfo2->setIsRefunded(true);
        $integralInfo2->setRefundedTime(new \DateTimeImmutable('2024-01-16 12:00:00'));

        self::getEntityManager()->persist($integralInfo1);
        self::getEntityManager()->persist($integralInfo2);
        self::getEntityManager()->flush();

        $unrefunded = $this->service->findUnrefundedIntegralInfos(1008);

        $this->assertCount(1, $unrefunded);
        $this->assertSame($integralInfo1->getId(), $unrefunded[0]->getId());
        $this->assertFalse($unrefunded[0]->isRefunded());
    }

    public function testFindUnrefundedIntegralInfosReturnsEmptyArrayWhenNoneFound(): void
    {
        $unrefunded = $this->service->findUnrefundedIntegralInfos(9999);

        $this->assertIsArray($unrefunded);
        $this->assertEmpty($unrefunded);
    }

    public function testGetIntegralBalanceWithAvailableService(): void
    {
        $integralAccount = new IntegralAccountDTO(
            id: 1,
            userIdentifier: '12345',
            totalIntegral: 800,
            availableIntegral: 800,
            frozenIntegral: 0,
            grandTotalIntegral: 1000
        );

        $this->integralService
            ->expects($this->once())
            ->method('getIntegralAccount')
            ->with('12345')
            ->willReturn($integralAccount)
        ;

        // 使用基类中已经设置的服务实例
        $balance = $this->service->getIntegralBalance('12345');

        $this->assertSame(800, $balance);
    }

    public function testGetIntegralBalanceReturnsZeroWhenAccountNotFound(): void
    {
        $this->integralService
            ->expects($this->once())
            ->method('getIntegralAccount')
            ->with('99999')
            ->willReturn(null)
        ;

        $balance = $this->service->getIntegralBalance('99999');

        $this->assertSame(0, $balance);
    }

    public function testGetIntegralBalanceReturnsZeroWhenServiceUnavailable(): void
    {
        // 直接实例化服务，将 IntegralService 设为 null 以模拟服务不可用
        // @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass
        $serviceWithoutIntegral = new IntegralRecordService(
            $this->logger,
            null, // IntegralService 不可用
            self::getEntityManager(),
            $this->integralInfoRepository
        );

        $balance = $serviceWithoutIntegral->getIntegralBalance('12345');

        $this->assertSame(0, $balance);
    }

    public function testGetIntegralBalanceReturnsZeroOnException(): void
    {
        $this->integralService
            ->expects($this->once())
            ->method('getIntegralAccount')
            ->with('12345')
            ->willThrowException(new ServiceUnavailableException('Service error'))
        ;

        $balance = $this->service->getIntegralBalance('12345');

        $this->assertSame(0, $balance);
    }
}
