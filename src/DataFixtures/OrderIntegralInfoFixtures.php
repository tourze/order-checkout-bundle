<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\OrderCheckoutBundle\Entity\OrderIntegralInfo;

/**
 * 订单积分信息测试数据
 */
final class OrderIntegralInfoFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 创建测试数据 - 已扣减积分的订单
        $integralInfo1 = new OrderIntegralInfo();
        $integralInfo1->setOrderId(1001);
        $integralInfo1->setUserId(100);
        $integralInfo1->setIntegralRequired(50);
        $integralInfo1->setIntegralOperationId('INT-OP-001');
        $integralInfo1->setDeductedTime(new \DateTimeImmutable('2024-01-15 10:00:00'));
        $integralInfo1->setBalanceBefore(100);
        $integralInfo1->setBalanceAfter(50);
        $integralInfo1->setCreateTime(new \DateTimeImmutable('2024-01-15 10:00:00'));

        $manager->persist($integralInfo1);

        // 创建测试数据 - 已退还积分的订单
        $integralInfo2 = new OrderIntegralInfo();
        $integralInfo2->setOrderId(1002);
        $integralInfo2->setUserId(101);
        $integralInfo2->setIntegralRequired(30);
        $integralInfo2->setIntegralOperationId('INT-OP-002');
        $integralInfo2->setDeductedTime(new \DateTimeImmutable('2024-01-16 11:00:00'));
        $integralInfo2->setBalanceBefore(80);
        $integralInfo2->setBalanceAfter(50);
        $integralInfo2->setIsRefunded(true);
        $integralInfo2->setRefundedTime(new \DateTimeImmutable('2024-01-17 12:00:00'));
        $integralInfo2->setRefundOperationId('INT-REFUND-002');
        $integralInfo2->setRemark('订单取消退还积分');
        $integralInfo2->setCreateTime(new \DateTimeImmutable('2024-01-16 11:00:00'));

        $manager->persist($integralInfo2);

        // 创建测试数据 - 使用积分较多的订单
        $integralInfo3 = new OrderIntegralInfo();
        $integralInfo3->setOrderId(1003);
        $integralInfo3->setUserId(102);
        $integralInfo3->setIntegralRequired(200);
        $integralInfo3->setIntegralOperationId('INT-OP-003');
        $integralInfo3->setDeductedTime(new \DateTimeImmutable('2024-01-18 14:00:00'));
        $integralInfo3->setBalanceBefore(500);
        $integralInfo3->setBalanceAfter(300);
        $integralInfo3->setRemark('大额积分订单');
        $integralInfo3->setCreateTime(new \DateTimeImmutable('2024-01-18 14:00:00'));

        $manager->persist($integralInfo3);

        $manager->flush();
    }
}
