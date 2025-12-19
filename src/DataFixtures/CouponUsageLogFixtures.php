<?php

namespace Tourze\OrderCheckoutBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\OrderCheckoutBundle\Entity\CouponUsageLog;

#[When(env: 'dev')]
final class CouponUsageLogFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $log = new CouponUsageLog();
        $log->setCouponCode('FIXTURE-CODE');
        $log->setUserIdentifier('fixture-user');
        $log->setOrderId(1000);
        $log->setOrderNumber('ORD-FIXTURE');
        $log->setDiscountAmount('5.00');
        $log->setCouponType('fixture');
        $log->setMetadata(['source' => 'fixtures']);

        $manager->persist($log);
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['coupon', 'test'];
    }
}
