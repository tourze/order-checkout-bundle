<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\Service\CouponEvaluator;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\RecommendedCoupon;
use Tourze\OrderCheckoutBundle\Provider\CouponProviderChain;
use Tourze\OrderCheckoutBundle\Service\CouponRecommendationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\ProductServiceContracts\SkuLoaderInterface;

/**
 * @internal
 */
#[CoversClass(CouponRecommendationService::class)]
#[RunTestsInSeparateProcesses]
final class CouponRecommendationServiceTest extends AbstractIntegrationTestCase
{
    private CouponRecommendationService $service;

    private UserInterface $user;

    protected function onSetUp(): void
    {
        $this->service = self::getService(CouponRecommendationService::class);
        $this->user = $this->createNormalUser('testuser', 'password');
    }

    public function testGetRecommendedCouponsWithNoAvailableCoupons(): void
    {
        // 准备测试数据：空购物车上下文
        $context = $this->createCalculationContext([]);

        // 执行
        $recommendations = $this->service->getRecommendedCoupons($context);

        // 验证
        $this->assertIsArray($recommendations);
        $this->assertEmpty($recommendations, '没有可用优惠券时应返回空数组');
    }

    public function testGetRecommendedCouponsWithEmptyCart(): void
    {
        // 准备测试数据：空购物车上下文
        $context = $this->createCalculationContext([]);

        // 执行
        $recommendations = $this->service->getRecommendedCoupons($context);

        // 验证
        $this->assertIsArray($recommendations);
        $this->assertEmpty($recommendations, '空购物车时应返回空数组');
    }

    public function testGetRecommendedCouponsWithValidItems(): void
    {
        // 准备测试数据：创建 SKU 和购物车项
        $sku = $this->createSku(1, '99.99', '1234567890123');
        $checkoutItem = new CheckoutItem(
            skuId: 1,
            quantity: 2,
            selected: true,
            sku: $sku
        );

        $context = $this->createCalculationContext([$checkoutItem]);

        // 执行
        $recommendations = $this->service->getRecommendedCoupons($context);

        // 验证
        $this->assertIsArray($recommendations);
        // 注意：实际推荐结果取决于 CouponProviderChain 的实现
    }

    public function testGetRecommendedCouponsWithUnselectedItems(): void
    {
        // 准备测试数据：创建未选中的购物车项
        $sku = $this->createSku(1, '99.99', '1234567890123');
        $checkoutItem = new CheckoutItem(
            skuId: 1,
            quantity: 2,
            selected: false,
            sku: $sku
        );

        $context = $this->createCalculationContext([$checkoutItem]);

        // 执行
        $recommendations = $this->service->getRecommendedCoupons($context);

        // 验证
        $this->assertIsArray($recommendations);
        $this->assertEmpty($recommendations, '未选中的商品不应参与优惠券推荐');
    }

    public function testGetRecommendedCouponsWithNullSku(): void
    {
        // 准备测试数据：SKU 为 null 的购物车项
        $checkoutItem = new CheckoutItem(
            skuId: 999,
            quantity: 1,
            selected: true,
            sku: null
        );

        $context = $this->createCalculationContext([$checkoutItem]);

        // 执行
        $recommendations = $this->service->getRecommendedCoupons($context);

        // 验证
        $this->assertIsArray($recommendations);
        // SKU 不存在时，应该跳过该商品
    }

    public function testGetRecommendedCouponsWithMultipleItems(): void
    {
        // 准备测试数据：多个购物车项
        $sku1 = $this->createSku(1, '50.00', '1234567890001');
        $sku2 = $this->createSku(2, '80.00', '1234567890002');

        $items = [
            new CheckoutItem(skuId: 1, quantity: 2, selected: true, sku: $sku1),
            new CheckoutItem(skuId: 2, quantity: 1, selected: true, sku: $sku2),
        ];

        $context = $this->createCalculationContext($items);

        // 执行
        $recommendations = $this->service->getRecommendedCoupons($context);

        // 验证
        $this->assertIsArray($recommendations);
    }

    public function testGetRecommendedCouponsWithMetadata(): void
    {
        // 准备测试数据：带元数据的上下文
        $sku = $this->createSku(1, '99.99', '1234567890123');
        $checkoutItem = new CheckoutItem(
            skuId: 1,
            quantity: 1,
            selected: true,
            sku: $sku
        );

        $metadata = [
            'region' => 'CN',
            'calculate_time' => new \DateTimeImmutable('2025-01-15 12:00:00'),
        ];

        $context = $this->createCalculationContext([$checkoutItem], [], $metadata);

        // 执行
        $recommendations = $this->service->getRecommendedCoupons($context);

        // 验证
        $this->assertIsArray($recommendations);
    }

    public function testGetRecommendedCouponsWithDateTimeInterfaceInMetadata(): void
    {
        // 准备测试数据：使用 DateTime（非 Immutable）
        $sku = $this->createSku(1, '99.99', '1234567890123');
        $checkoutItem = new CheckoutItem(
            skuId: 1,
            quantity: 1,
            selected: true,
            sku: $sku
        );

        $metadata = [
            'calculate_time' => new \DateTime('2025-01-15 12:00:00'),
        ];

        $context = $this->createCalculationContext([$checkoutItem], [], $metadata);

        // 执行
        $recommendations = $this->service->getRecommendedCoupons($context);

        // 验证
        $this->assertIsArray($recommendations);
    }

    public function testServiceCanBeInstantiated(): void
    {
        // 验证服务可以被正确实例化
        $this->assertInstanceOf(CouponRecommendationService::class, $this->service);
    }

    public function testServiceHasRequiredDependencies(): void
    {
        // 验证服务依赖可以从容器中获取
        $providerChain = self::getService(CouponProviderChain::class);
        $this->assertInstanceOf(CouponProviderChain::class, $providerChain);

        $couponEvaluator = self::getService(CouponEvaluator::class);
        $this->assertInstanceOf(CouponEvaluator::class, $couponEvaluator);

        $skuLoader = self::getService(SkuLoaderInterface::class);
        $this->assertInstanceOf(SkuLoaderInterface::class, $skuLoader);
    }

    public function testGetRecommendedCouponsWithLargeBatchOfItems(): void
    {
        // 准备测试数据：大量购物车项（模拟批量场景）
        $items = [];
        for ($i = 1; $i <= 20; ++$i) {
            $sku = $this->createSku($i, '10.00', "123456789000{$i}");
            $items[] = new CheckoutItem(
                skuId: $i,
                quantity: 1,
                selected: true,
                sku: $sku
            );
        }

        $context = $this->createCalculationContext($items);

        // 执行
        $recommendations = $this->service->getRecommendedCoupons($context);

        // 验证
        $this->assertIsArray($recommendations);
        // 验证推荐数量不超过最大限制（代码中设置为 10）
        $this->assertLessThanOrEqual(10, count($recommendations));
    }

    public function testGetRecommendedCouponsReturnsCorrectStructure(): void
    {
        // 准备测试数据
        $sku = $this->createSku(1, '99.99', '1234567890123');
        $checkoutItem = new CheckoutItem(
            skuId: 1,
            quantity: 1,
            selected: true,
            sku: $sku
        );

        $context = $this->createCalculationContext([$checkoutItem]);

        // 执行
        $recommendations = $this->service->getRecommendedCoupons($context);

        // 验证
        $this->assertIsArray($recommendations);

        // 验证每个推荐优惠券的结构
        foreach ($recommendations as $recommendation) {
            $this->assertInstanceOf(RecommendedCoupon::class, $recommendation);
            $this->assertIsString($recommendation->getCode());
            $this->assertIsString($recommendation->getName());
            $this->assertIsString($recommendation->getType());
            $this->assertIsString($recommendation->getExpectedDiscount());
            $this->assertIsString($recommendation->getDescription());
        }
    }

    /**
     * 创建测试用的 CalculationContext
     *
     * @param array<int, CheckoutItem> $items
     * @param array<string> $appliedCoupons
     * @param array<string, mixed> $metadata
     */
    private function createCalculationContext(
        array $items,
        array $appliedCoupons = [],
        array $metadata = [],
    ): CalculationContext {
        return new CalculationContext(
            user: $this->user,
            items: $items,
            appliedCoupons: $appliedCoupons,
            metadata: $metadata
        );
    }

    /**
     * 创建测试用的 SKU
     */
    private function createSku(int $id, string $price, string $gtin): Sku
    {
        $spu = new Spu();
        $reflection = new \ReflectionClass($spu);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($spu, $id);

        $sku = new Sku();
        $reflection = new \ReflectionClass($sku);

        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($sku, $id);

        $marketPriceProperty = $reflection->getProperty('marketPrice');
        $marketPriceProperty->setValue($sku, $price);

        $gtinProperty = $reflection->getProperty('gtin');
        $gtinProperty->setValue($sku, $gtin);

        $spuProperty = $reflection->getProperty('spu');
        $spuProperty->setValue($sku, $spu);

        return $sku;
    }
}
