<?php

namespace Tourze\OrderCheckoutBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Entity\CouponAllocationDetail;
use Tourze\OrderCheckoutBundle\Repository\CouponAllocationDetailRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(CouponAllocationDetailRepository::class)]
#[RunTestsInSeparateProcesses]
final class CouponAllocationDetailRepositoryTest extends AbstractRepositoryTestCase
{
    protected function getRepository(): CouponAllocationDetailRepository
    {
        return self::getService(CouponAllocationDetailRepository::class);
    }

    protected function createNewEntity(): object
    {
        return new CouponAllocationDetail();
    }

    protected function onSetUp(): void
    {
        // Repository 测试的设置逻辑已由父类 AbstractRepositoryTestCase 处理
        // 这里不需要额外的设置，因为所有必需的依赖都已通过继承获得
    }

    public function testExtendsBaseRepository(): void
    {
        $stub = $this->getMockBuilder(CouponAllocationDetailRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        self::assertInstanceOf(ServiceEntityRepository::class, $stub);
    }
}
