<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Tourze\OrderCheckoutBundle\Entity\OrderExtendedInfo;

/**
 * 订单扩展信息测试数据
 */
final class OrderExtendedInfoFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 创建一些测试数据以满足 AbstractRepositoryTestCase 的要求
        $orderExtendedInfo1 = new OrderExtendedInfo();
        $orderExtendedInfo1->setOrderId(1001);
        $orderExtendedInfo1->setInfoType('remark');
        $orderExtendedInfo1->setInfoKey('customer_remark');
        $orderExtendedInfo1->setInfoValue('测试备注1');
        $orderExtendedInfo1->setCreatedBy('100');
        $orderExtendedInfo1->setCreateTime(new \DateTimeImmutable('2024-01-01 10:00:00'));

        $orderExtendedInfo2 = new OrderExtendedInfo();
        $orderExtendedInfo2->setOrderId(1002);
        $orderExtendedInfo2->setInfoType('remark');
        $orderExtendedInfo2->setInfoKey('customer_remark');
        $orderExtendedInfo2->setInfoValue('测试备注2');
        $orderExtendedInfo2->setCreatedBy('101');
        $orderExtendedInfo2->setCreateTime(new \DateTimeImmutable('2024-01-02 10:00:00'));

        // 添加 seller_remark 记录以满足测试要求
        $orderExtendedInfo3 = new OrderExtendedInfo();
        $orderExtendedInfo3->setOrderId(1001);
        $orderExtendedInfo3->setInfoType('remark');
        $orderExtendedInfo3->setInfoKey('seller_remark');
        $orderExtendedInfo3->setInfoValue('商家备注');
        $orderExtendedInfo3->setCreatedBy('102');
        $orderExtendedInfo3->setCreateTime(new \DateTimeImmutable('2024-01-03 10:00:00'));

        $manager->persist($orderExtendedInfo1);
        $manager->persist($orderExtendedInfo2);
        $manager->persist($orderExtendedInfo3);
        $manager->flush();
    }
}
