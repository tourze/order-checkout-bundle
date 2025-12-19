<?php

namespace Tourze\OrderCheckoutBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Entity\CouponUsageLog;
use Tourze\OrderCheckoutBundle\Repository\CouponUsageLogRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(CouponUsageLogRepository::class)]
#[RunTestsInSeparateProcesses]
final class CouponUsageLogRepositoryTest extends AbstractRepositoryTestCase
{
    protected function getRepository(): CouponUsageLogRepository
    {
        return self::getService(CouponUsageLogRepository::class);
    }

    protected function createNewEntity(): object
    {
        return new CouponUsageLog();
    }

    protected function onSetUp(): void
    {
        // Repository 测试的设置逻辑已由父类 AbstractRepositoryTestCase 处理
        // 这里不需要额外的设置，因为所有必需的依赖都已通过继承获得
    }

    public function testExtendsBaseRepository(): void
    {
        // Repository 类是 final，无法 mock，直接使用真实实例验证继承关系
        $repository = $this->getRepository();
        self::assertInstanceOf(ServiceEntityRepository::class, $repository);
    }
}
