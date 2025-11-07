<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use OrderCoreBundle\OrderCoreBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\CouponCoreBundle\CouponCoreBundle;
use Tourze\DeliveryAddressBundle\DeliveryAddressBundle;
use Tourze\OrderCartBundle\OrderCartBundle;
use Tourze\OrderCheckoutBundle\Contract\PriceCalculatorInterface;
use Tourze\ProductCoreBundle\ProductCoreBundle;
use Tourze\StockManageBundle\StockManageBundle;

class OrderCheckoutBundle extends Bundle implements BundleDependencyInterface
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // 自动配置价格计算器接口
        $container->registerForAutoconfiguration(PriceCalculatorInterface::class)
            ->addTag('order_checkout.price_calculator')
        ;
    }

    public static function getBundleDependencies(): array
    {
        return [
            SecurityBundle::class => ['all' => true],
            DoctrineBundle::class => ['all' => true],
            DeliveryAddressBundle::class => ['all' => true],
            OrderCartBundle::class => ['all' => true],
            ProductCoreBundle::class => ['all' => true],
            OrderCoreBundle::class => ['all' => true],
            StockManageBundle::class => ['all' => true],
            CouponCoreBundle::class => ['all' => true],
        ];
    }
}
