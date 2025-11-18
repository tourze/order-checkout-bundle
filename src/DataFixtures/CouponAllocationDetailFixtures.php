<?php

namespace Tourze\OrderCheckoutBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\OrderCheckoutBundle\Entity\CouponAllocationDetail;

#[When(env: 'dev')]
class CouponAllocationDetailFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $detail = new CouponAllocationDetail();
        $detail->setCouponCode('FIXTURE-CODE');
        $detail->setOrderId(1000);
        $detail->setSkuId('SKU-FIXTURE');
        $detail->setAllocatedAmount('2.50');
        $detail->setAllocationRule('fixture');

        $manager->persist($detail);
        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['coupon', 'test'];
    }
}
