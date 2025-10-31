<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\OrderCheckoutBundle\DependencyInjection\OrderCheckoutExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(OrderCheckoutExtension::class)]
final class OrderCheckoutExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testGetAlias(): void
    {
        $extension = new OrderCheckoutExtension();
        $this->assertEquals('order_checkout', $extension->getAlias());
    }

    public function testLoadInTestEnvironment(): void
    {
        $extension = new OrderCheckoutExtension();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        $extension->load([], $container);

        // 验证核心服务是否注册
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Service\CheckoutService'), 'CheckoutService 应该被注册');
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Service\PriceCalculationService'), 'PriceCalculationService 应该被注册');
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Service\BasicStockValidator'), 'BasicStockValidator 应该被注册');

        // 验证计算器服务是否注册
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Calculator\BasePriceCalculator'), 'BasePriceCalculator 应该被注册');
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Calculator\PromotionCalculator'), 'PromotionCalculator 应该被注册');
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Calculator\BasicShippingCalculator'), 'BasicShippingCalculator 应该被注册');

        // 验证仓储服务是否注册（购物车相关仓储已迁移到 order-cart-bundle）

        // 验证促销匹配器是否注册
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Promotion\FullReductionMatcher'), 'FullReductionMatcher 应该被注册');

        // 验证服务别名是否注册
        $this->assertTrue($container->has('order_checkout.checkout_service'), '公共服务别名 order_checkout.checkout_service 应该被注册');
        $this->assertTrue($container->has('order_checkout.price_calculation_service'), '公共服务别名 order_checkout.price_calculation_service 应该被注册');

        // 验证接口别名是否注册
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Contract\StockValidatorInterface'), 'StockValidatorInterface 别名应该被注册');
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Contract\ShippingCalculatorInterface'), 'ShippingCalculatorInterface 别名应该被注册');

        // 购物车相关 DataFixtures 已迁移到 order-cart-bundle
    }

    public function testLoadInDevelopmentEnvironment(): void
    {
        $extension = new OrderCheckoutExtension();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'dev');

        $extension->load([], $container);

        // 购物车相关 DataFixtures 已迁移到 order-cart-bundle

        // 验证核心服务在开发环境也正常注册
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Service\CheckoutService'), 'CheckoutService 在开发环境应该被注册');
    }

    public function testLoadInProductionEnvironment(): void
    {
        $extension = new OrderCheckoutExtension();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'prod');

        $extension->load([], $container);

        // 验证核心服务在生产环境正常注册
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Service\CheckoutService'), 'CheckoutService 在生产环境应该被注册');

        // 购物车相关 DataFixtures 已迁移到 order-cart-bundle
    }

    public function testServiceDefinitionConfiguration(): void
    {
        $extension = new OrderCheckoutExtension();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        $extension->load([], $container);

        // 购物车相关 Repository 已迁移到 order-cart-bundle

        // 验证价格计算器的标签
        $basePriceCalculatorDefinition = $container->getDefinition('Tourze\OrderCheckoutBundle\Calculator\BasePriceCalculator');
        $this->assertTrue($basePriceCalculatorDefinition->hasTag('order_checkout.price_calculator'), 'BasePriceCalculator 应该有 order_checkout.price_calculator 标签');

        $promotionCalculatorDefinition = $container->getDefinition('Tourze\OrderCheckoutBundle\Calculator\PromotionCalculator');
        $this->assertTrue($promotionCalculatorDefinition->hasTag('order_checkout.price_calculator'), 'PromotionCalculator 应该有 order_checkout.price_calculator 标签');

        // 验证促销匹配器的标签
        $fullReductionMatcherDefinition = $container->getDefinition('Tourze\OrderCheckoutBundle\Promotion\FullReductionMatcher');
        $this->assertTrue($fullReductionMatcherDefinition->hasTag('order_checkout.promotion_matcher'), 'FullReductionMatcher 应该有 order_checkout.promotion_matcher 标签');

        // 验证公共服务别名是否是公共的
        $checkoutServiceAlias = $container->getAlias('order_checkout.checkout_service');
        $this->assertTrue($checkoutServiceAlias->isPublic(), 'order_checkout.checkout_service 别名应该是公共的');

        $priceCalculationServiceAlias = $container->getAlias('order_checkout.price_calculation_service');
        $this->assertTrue($priceCalculationServiceAlias->isPublic(), 'order_checkout.price_calculation_service 别名应该是公共的');
    }

    public function testProcedureAutoConfiguration(): void
    {
        $extension = new OrderCheckoutExtension();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        $extension->load([], $container);

        // 验证一些具体的 Procedure 类是否被注册（购物车相关 Procedure 已迁移到 order-cart-bundle）
        $this->assertTrue($container->has('Tourze\OrderCheckoutBundle\Procedure\Checkout\ProcessCheckoutProcedure'), 'ProcessCheckoutProcedure 应该被自动注册');

        // 验证 Procedure 类的标签和配置
        $processCheckoutDefinition = $container->getDefinition('Tourze\OrderCheckoutBundle\Procedure\Checkout\ProcessCheckoutProcedure');
        $this->assertTrue($processCheckoutDefinition->hasTag('json_rpc.procedure'), 'ProcessCheckoutProcedure 应该有 json_rpc.procedure 标签');
        $this->assertTrue($processCheckoutDefinition->isPublic(), 'ProcessCheckoutProcedure 应该是公共的');
        $this->assertTrue($processCheckoutDefinition->isAutowired(), 'ProcessCheckoutProcedure 应该启用自动装配');
        $this->assertTrue($processCheckoutDefinition->isAutoconfigured(), 'ProcessCheckoutProcedure 应该启用自动配置');
    }
}
