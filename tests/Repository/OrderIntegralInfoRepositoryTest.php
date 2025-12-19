<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Entity\OrderIntegralInfo;
use Tourze\OrderCheckoutBundle\Repository\OrderIntegralInfoRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(OrderIntegralInfoRepository::class)]
#[RunTestsInSeparateProcesses]
final class OrderIntegralInfoRepositoryTest extends AbstractRepositoryTestCase
{
    protected function getRepository(): OrderIntegralInfoRepository
    {
        return self::getService(OrderIntegralInfoRepository::class);
    }

    protected function createNewEntity(): object
    {
        $entity = new OrderIntegralInfo();
        $entity->setOrderId(999999)
            ->setIntegralRequired(100)
            ->setIntegralOperationId('TEST-OP-' . uniqid())
            ->setUserId(888888)
            ->setDeductedTime(new \DateTimeImmutable())
            ->setBalanceBefore(200)
            ->setBalanceAfter(100);
        return $entity;
    }

    protected function onSetUp(): void
    {
        // Repository 测试的设置逻辑已由父类处理
    }

    public function testFindByOrderIdReturnsNull(): void
    {
        // 不存在的订单应返回 null
        $result = $this->getRepository()->findByOrderId(999999);
        $this->assertNull($result);
    }

    public function testSaveAndFindByOrderId(): void
    {
        $info = new OrderIntegralInfo();
        $info->setOrderId(9876543)
            ->setIntegralRequired(100)
            ->setIntegralOperationId('OP-UNIQUE-' . uniqid())
            ->setUserId(7654321)
            ->setDeductedTime(new \DateTimeImmutable())
            ->setBalanceBefore(500)
            ->setBalanceAfter(400);

        $this->getRepository()->save($info, flush: true);

        // 验证可以通过 orderId 查询到
        $found = $this->getRepository()->findByOrderId(9876543);
        $this->assertInstanceOf(OrderIntegralInfo::class, $found);
        $this->assertSame(9876543, $found->getOrderId());
        $this->assertSame(100, $found->getIntegralRequired());
    }

    public function testFindByUserId(): void
    {
        // 创建多个同用户的积分记录
        $info1 = new OrderIntegralInfo();
        $info1->setOrderId(2001)
            ->setUserId(3001)
            ->setIntegralRequired(50)
            ->setIntegralOperationId('OP001')
            ->setDeductedTime(new \DateTimeImmutable('2024-01-01 10:00:00'))
            ->setBalanceBefore(200)
            ->setBalanceAfter(150);
        $info1->setCreateTime(new \DateTimeImmutable('2024-01-01 10:00:00'));

        $info2 = new OrderIntegralInfo();
        $info2->setOrderId(2002)
            ->setUserId(3001)
            ->setIntegralRequired(30)
            ->setIntegralOperationId('OP002')
            ->setDeductedTime(new \DateTimeImmutable('2024-01-02 11:00:00'))
            ->setBalanceBefore(150)
            ->setBalanceAfter(120);
        $info2->setCreateTime(new \DateTimeImmutable('2024-01-02 11:00:00'));

        $this->getRepository()->save($info1, flush: false);
        $this->getRepository()->save($info2, flush: true);

        $results = $this->getRepository()->findByUserId(3001);
        $this->assertCount(2, $results);

        // 验证按创建时间倒序排列
        $this->assertSame(2002, $results[0]->getOrderId());
        $this->assertSame(2001, $results[1]->getOrderId());
    }

    public function testCountUnrefunded(): void
    {
        // 创建测试数据
        $unrefunded = new OrderIntegralInfo();
        $unrefunded->setOrderId(3001)
            ->setUserId(4001)
            ->setIntegralRequired(100)
            ->setIntegralOperationId('OP-UNREFUND')
            ->setDeductedTime(new \DateTimeImmutable())
            ->setBalanceBefore(200)
            ->setBalanceAfter(100)
            ->setIsRefunded(false);

        $refunded = new OrderIntegralInfo();
        $refunded->setOrderId(3002)
            ->setUserId(4002)
            ->setIntegralRequired(50)
            ->setIntegralOperationId('OP-REFUND')
            ->setDeductedTime(new \DateTimeImmutable())
            ->setBalanceBefore(150)
            ->setBalanceAfter(100)
            ->setIsRefunded(true);

        $this->getRepository()->save($unrefunded, flush: false);
        $this->getRepository()->save($refunded, flush: true);

        $count = $this->getRepository()->countUnrefunded();
        $this->assertGreaterThanOrEqual(1, $count);
    }
}
