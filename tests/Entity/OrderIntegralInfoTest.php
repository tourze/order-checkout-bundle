<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Entity\OrderIntegralInfo;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(OrderIntegralInfo::class)]
final class OrderIntegralInfoTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new OrderIntegralInfo();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'orderId' => ['orderId', 1001];
        yield 'integralRequired' => ['integralRequired', 100];
        yield 'integralOperationId' => ['integralOperationId', 'OP20251120123456'];
        // isRefunded 使用 isRefunded() 而非 getIsRefunded()，单独测试
        // yield 'isRefunded' => ['isRefunded', true];
        yield 'refundedTime' => ['refundedTime', new \DateTimeImmutable('2025-11-20 12:00:00')];
        yield 'refundOperationId' => ['refundOperationId', 'OP20251120234567'];
        yield 'remark' => ['remark', '人工补偿'];
        yield 'deductedTime' => ['deductedTime', new \DateTimeImmutable('2025-11-20 10:00:00')];
        yield 'userId' => ['userId', 2001];
        yield 'balanceBefore' => ['balanceBefore', 500];
        yield 'balanceAfter' => ['balanceAfter', 400];
        yield 'createTime' => ['createTime', new \DateTimeImmutable('2025-11-20 10:00:00')];
        yield 'updateTime' => ['updateTime', new \DateTimeImmutable('2025-11-20 11:00:00')];
    }

    public function testDefaultIsRefundedIsFalse(): void
    {
        $info = new OrderIntegralInfo();
        $this->assertFalse($info->isRefunded());
    }

    public function testSetAndGetIsRefunded(): void
    {
        $info = new OrderIntegralInfo();
        $info->setIsRefunded(true);
        $this->assertTrue($info->isRefunded());

        $info->setIsRefunded(false);
        $this->assertFalse($info->isRefunded());
    }

    public function testRefundedTimeNullableByDefault(): void
    {
        $info = new OrderIntegralInfo();
        $this->assertNull($info->getRefundedTime());
    }

    public function testRefundOperationIdNullableByDefault(): void
    {
        $info = new OrderIntegralInfo();
        $this->assertNull($info->getRefundOperationId());
    }

    public function testRemarkNullableByDefault(): void
    {
        $info = new OrderIntegralInfo();
        $this->assertNull($info->getRemark());
    }

    public function testFluentInterface(): void
    {
        $info = new OrderIntegralInfo();
        $deductedTime = new \DateTimeImmutable();

        $result = $info
            ->setOrderId(1001)
            ->setIntegralRequired(100)
            ->setIntegralOperationId('OP123')
            ->setIsRefunded(false)
            ->setDeductedTime($deductedTime)
            ->setUserId(2001)
            ->setBalanceBefore(500)
            ->setBalanceAfter(400)
        ;

        $this->assertSame($info, $result);
        $this->assertSame(1001, $info->getOrderId());
        $this->assertSame(100, $info->getIntegralRequired());
    }

    public function testCompleteIntegralInfoScenario(): void
    {
        $info = new OrderIntegralInfo();
        $now = new \DateTimeImmutable('2025-11-20 10:00:00');

        $info
            ->setOrderId(1001)
            ->setIntegralRequired(100)
            ->setIntegralOperationId('OP20251120123456')
            ->setIsRefunded(false)
            ->setDeductedTime($now)
            ->setUserId(2001)
            ->setBalanceBefore(500)
            ->setBalanceAfter(400)
        ;

        $info->setCreateTime($now);
        $info->setUpdateTime($now);

        $this->assertSame(1001, $info->getOrderId());
        $this->assertSame(100, $info->getIntegralRequired());
        $this->assertSame('OP20251120123456', $info->getIntegralOperationId());
        $this->assertFalse($info->isRefunded());
        $this->assertEquals($now, $info->getCreateTime());
        $this->assertEquals($now, $info->getDeductedTime());
        $this->assertSame(2001, $info->getUserId());
        $this->assertSame(500, $info->getBalanceBefore());
        $this->assertSame(400, $info->getBalanceAfter());
        $this->assertNull($info->getRefundedTime());
        $this->assertNull($info->getRefundOperationId());
    }

    public function testRefundScenario(): void
    {
        $info = new OrderIntegralInfo();
        $createdTime = new \DateTimeImmutable('2025-11-20 10:00:00');
        $refundedTime = new \DateTimeImmutable('2025-11-20 11:00:00');

        $info
            ->setOrderId(1001)
            ->setIntegralRequired(100)
            ->setIntegralOperationId('OP20251120123456')
            ->setIsRefunded(false)
            ->setDeductedTime($createdTime)
            ->setUserId(2001)
            ->setBalanceBefore(500)
            ->setBalanceAfter(400)
        ;

        $info->setCreateTime($createdTime);
        $info->setUpdateTime($createdTime);

        $info
            ->setIsRefunded(true)
            ->setRefundedTime($refundedTime)
            ->setRefundOperationId('OP20251120234567')
        ;

        $info->setUpdateTime($refundedTime);

        $this->assertTrue($info->isRefunded());
        $this->assertEquals($refundedTime, $info->getRefundedTime());
        $this->assertSame('OP20251120234567', $info->getRefundOperationId());
        $this->assertEquals($refundedTime, $info->getUpdateTime());
    }
}
