<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponUsageLogger;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CouponUsageLogger::class)]
#[RunTestsInSeparateProcesses]
final class CouponUsageLoggerTest extends AbstractIntegrationTestCase
{
    private CouponUsageLogger $logger;

    protected function onSetUp(): void
    {
        $this->logger = self::getService(CouponUsageLogger::class);
    }

    public function testLoggerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CouponUsageLogger::class, $this->logger);
    }

    public function testLogUsageCreatesRecords(): void
    {
        $this->logger->logUsage(
            couponCode: 'TEST-CODE-123',
            couponType: 'full_reduction',
            userIdentifier: 'test-user',
            orderId: 12345,
            orderNumber: 'ORDER-12345',
            discountAmount: '50.00',
            allocations: []
        );

        // 验证记录已创建 - 通过查询数据库来验证
        $entityManager = self::getEntityManager();

        $usageLogs = $entityManager
            ->getRepository(\Tourze\OrderCheckoutBundle\Entity\CouponUsageLog::class)
            ->findBy(['couponCode' => 'TEST-CODE-123']);

        $this->assertCount(1, $usageLogs);

        $log = $usageLogs[0];
        $this->assertSame('TEST-CODE-123', $log->getCouponCode());
        $this->assertSame('full_reduction', $log->getCouponType());
        $this->assertSame('test-user', $log->getUserIdentifier());
        $this->assertSame(12345, $log->getOrderId());
        $this->assertSame('ORDER-12345', $log->getOrderNumber());
        $this->assertSame('50.00', $log->getDiscountAmount());
    }
}