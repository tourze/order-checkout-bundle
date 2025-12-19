<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use OrderCoreBundle\OrderCoreBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\CouponCoreBundle\CouponCoreBundle;
use Tourze\DeliveryAddressBundle\DeliveryAddressBundle;
use Tourze\DoctrineIpBundle\DoctrineIpBundle;
use Tourze\DoctrinePrecisionBundle\DoctrinePrecisionBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\DoctrineTrackBundle\DoctrineTrackBundle;
use Tourze\DoctrineUserBundle\DoctrineUserBundle;
use Tourze\OrderCartBundle\OrderCartBundle;
use Tourze\ProductCoreBundle\ProductCoreBundle;
use Tourze\StockManageBundle\StockManageBundle;

class OrderCheckoutBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            SecurityBundle::class => ['all' => true],
            DoctrineBundle::class => ['all' => true],
            DoctrineIpBundle::class => ['all' => true],
            DoctrinePrecisionBundle::class => ['all' => true],
            DoctrineSnowflakeBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            DoctrineTrackBundle::class => ['all' => true],
            DoctrineUserBundle::class => ['all' => true],
            DeliveryAddressBundle::class => ['all' => true],
            OrderCartBundle::class => ['all' => true],
            ProductCoreBundle::class => ['all' => true],
            OrderCoreBundle::class => ['all' => true],
            StockManageBundle::class => ['all' => true],
            CouponCoreBundle::class => ['all' => true],
        ];
    }
}
