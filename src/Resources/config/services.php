<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tourze\OrderCartBundle\Repository\CartItemRepository;
use Tourze\OrderCartBundle\Service\CartManager;
use Tourze\OrderCheckoutBundle\Calculator\BasePriceCalculator;
use Tourze\OrderCheckoutBundle\Calculator\BasicShippingCalculator;
use Tourze\OrderCheckoutBundle\Calculator\PromotionCalculator;
use Tourze\OrderCheckoutBundle\Contract\PriceCalculatorInterface;
use Tourze\OrderCheckoutBundle\Contract\PromotionMatcherInterface;
use Tourze\OrderCheckoutBundle\Contract\ShippingCalculatorInterface;
use Tourze\OrderCheckoutBundle\Contract\StockValidatorInterface;
use Tourze\OrderCheckoutBundle\Promotion\FullReductionMatcher;
use Tourze\OrderCheckoutBundle\Service\BasicStockValidator;
use Tourze\OrderCheckoutBundle\Service\CheckoutService;
use Tourze\OrderCheckoutBundle\Service\PriceCalculationService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // 仓储服务
    $services->set(CartItemRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service')
    ;

    // 核心服务
    $services->set(CartManager::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service(CartItemRepository::class),
        ])
    ;

    $services->set(PriceCalculationService::class);

    $services->set(BasicStockValidator::class);

    $services->set(BasicShippingCalculator::class);

    $services->set(CheckoutService::class)
        ->args([
            service(CartManager::class),
            service(PriceCalculationService::class),
            service(StockValidatorInterface::class),
            service(ShippingCalculatorInterface::class),
        ])
    ;

    // 价格计算器
    $services->set(BasePriceCalculator::class)
        ->tag('order_checkout.price_calculator')
    ;

    $services->set(PromotionCalculator::class)
        ->tag('order_checkout.price_calculator')
    ;

    // 促销匹配器
    $services->set(FullReductionMatcher::class)
        ->tag('order_checkout.promotion_matcher')
    ;

    // 接口别名
    $services->alias(StockValidatorInterface::class, BasicStockValidator::class);
    $services->alias(ShippingCalculatorInterface::class, BasicShippingCalculator::class);

    // 自动配置价格计算器
    $services->get(PriceCalculationService::class)
        ->call('addCalculator', [service(BasePriceCalculator::class)])
        ->call('addCalculator', [service(PromotionCalculator::class)])
    ;

    // 自动配置促销匹配器
    $services->get(PromotionCalculator::class)
        ->call('addMatcher', [service(FullReductionMatcher::class)])
    ;

    // 自动标记
    $services->instanceof(PriceCalculatorInterface::class)
        ->tag('order_checkout.price_calculator')
    ;

    $services->instanceof(PromotionMatcherInterface::class)
        ->tag('order_checkout.promotion_matcher')
    ;

    // 自动注册所有 Procedure 类
    $services->load('Tourze\OrderCheckoutBundle\Procedure\\', '../../Procedure/')
        ->autowire()
        ->autoconfigure()
        ->public()
        ->tag('json_rpc.procedure')
    ;

    // 公共服务别名
    $services->alias('order_checkout.cart_service', CartManager::class)
        ->public()
    ;

    $services->alias('order_checkout.checkout_service', CheckoutService::class)
        ->public()
    ;

    $services->alias('order_checkout.price_calculation_service', PriceCalculationService::class)
        ->public()
    ;
};
