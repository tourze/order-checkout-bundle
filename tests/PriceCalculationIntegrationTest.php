<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\OrderCheckoutBundle\Calculator\BasePriceCalculator;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\ProductCoreBundle\Entity\Price;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Enum\PriceType;
use Tourze\ProductServiceContracts\SkuLoaderInterface;

/**
 * 价格计算集成测试
 *
 * @internal
 */
#[CoversClass(BasePriceCalculator::class)]
final class PriceCalculationIntegrationTest extends TestCase
{
    public function testArrayItemsFromSkuPrices(): void
    {
        // 创建模拟的 SKU 仓储
        $skuLoader = $this->createMock(SkuLoaderInterface::class);

        // 创建 SKU 和价格对象
        $sku1 = $this->createMockSku('100', 99.99);
        $sku2 = $this->createMockSku('200', 49.99);

        // 设置仓储返回 SKU
        $skuLoader->method('loadSkuByIdentifier')
            ->willReturnMap([
                ['100', $sku1],
                ['200', $sku2],
            ])
        ;

        // 创建价格计算器
        $calculator = new BasePriceCalculator($skuLoader);

        // 创建模拟用户
        $user = $this->createMock(UserInterface::class);

        // 创建数组格式的购物车项目（不包含价格，从 SKU 获取）
        $cartItemsData = [
            ['skuId' => '100', 'quantity' => 2, 'selected' => true],
            ['skuId' => '200', 'quantity' => 1, 'selected' => true],
        ];

        // 转换为 CheckoutItem 对象
        $cartItems = array_map(fn (array $data) => CheckoutItem::fromArray($data), $cartItemsData);

        // 创建计算上下文
        $context = new CalculationContext($user, $cartItems);

        // 验证计算器支持这个上下文
        $this->assertTrue($calculator->supports($context));

        // 执行价格计算
        $result = $calculator->calculate($context);

        // 验证计算结果
        $expectedTotal = (99.99 * 2) + (49.99 * 1); // 只计算选中的商品
        $this->assertEquals($expectedTotal, $result->getOriginalPrice());
        $this->assertEquals($expectedTotal, $result->getFinalPrice());
        $this->assertEquals(0.0, $result->getDiscount());

        // 验证详细信息
        $details = $result->getDetails();
        $this->assertArrayHasKey('base_price', $details);
        $this->assertIsArray($details['base_price']);
        $this->assertCount(2, $details['base_price']); // 只有选中的商品
        $this->assertEquals($expectedTotal, $details['base_total']);
    }

    public function testEmptyCartItemsReturnsZero(): void
    {
        $skuLoader = $this->createMock(SkuLoaderInterface::class);
        $calculator = new BasePriceCalculator($skuLoader);
        $user = $this->createMock(UserInterface::class);

        // 空购物车
        $cartItems = [];
        $context = new CalculationContext($user, $cartItems);

        // 空购物车不应该被支持
        $this->assertFalse($calculator->supports($context));
    }

    public function testAllUnselectedItemsReturnsZero(): void
    {
        $skuLoader = $this->createMock(SkuLoaderInterface::class);
        $calculator = new BasePriceCalculator($skuLoader);
        $user = $this->createMock(UserInterface::class);

        // 所有商品都未选中
        $cartItemsData = [
            ['skuId' => '100', 'quantity' => 2, 'selected' => false],
            ['skuId' => '200', 'quantity' => 1, 'selected' => false],
        ];

        $cartItems = array_map(fn (array $data) => CheckoutItem::fromArray($data), $cartItemsData);
        $context = new CalculationContext($user, $cartItems);

        $this->assertTrue($calculator->supports($context));

        $result = $calculator->calculate($context);

        // 应该返回0，因为没有选中的商品
        $this->assertEquals(0.0, $result->getOriginalPrice());
        $this->assertEquals(0.0, $result->getFinalPrice());

        // 详细信息应该为空
        $details = $result->getDetails();
        $this->assertArrayHasKey('base_price', $details);
        $this->assertIsArray($details['base_price']);
        $this->assertCount(0, $details['base_price']);
        $this->assertEquals(0.0, $details['base_total']);
    }

    public function testCalculate(): void
    {
        $skuLoader = $this->createMock(SkuLoaderInterface::class);
        $sku = $this->createMockSku('test-sku', 100.0);

        $skuLoader->method('loadSkuByIdentifier')
            ->with('test-sku')
            ->willReturn($sku)
        ;

        $calculator = new BasePriceCalculator($skuLoader);
        $user = $this->createMock(UserInterface::class);

        $cartItemsData = [
            ['skuId' => 'test-sku', 'quantity' => 2, 'selected' => true],
        ];

        $cartItems = array_map(fn (array $data) => CheckoutItem::fromArray($data), $cartItemsData);
        $context = new CalculationContext($user, $cartItems);
        $result = $calculator->calculate($context);

        $this->assertEquals(200.0, $result->getFinalPrice()); // 100 * 2
        $this->assertEquals(200.0, $result->getOriginalPrice());
        $this->assertEquals(0.0, $result->getDiscount());
    }

    public function testSupports(): void
    {
        $skuLoader = $this->createMock(SkuLoaderInterface::class);
        $calculator = new BasePriceCalculator($skuLoader);
        $user = $this->createMock(UserInterface::class);

        // 测试支持有选中商品的购物车
        $cartItemsWithSelectedData = [
            ['skuId' => 'sku-1', 'quantity' => 1, 'selected' => true],
        ];
        $cartItemsWithSelected = array_map(fn (array $data) => CheckoutItem::fromArray($data), $cartItemsWithSelectedData);
        $contextWithSelected = new CalculationContext($user, $cartItemsWithSelected);
        $this->assertTrue($calculator->supports($contextWithSelected));

        // 测试不支持空购物车
        $emptyContext = new CalculationContext($user, []);
        $this->assertFalse($calculator->supports($emptyContext));

        // 测试支持全部未选中的购物车（虽然会返回0金额，但仍然支持处理）
        $cartItemsAllUnselectedData = [
            ['skuId' => 'sku-1', 'quantity' => 1, 'selected' => false],
        ];
        $cartItemsAllUnselected = array_map(fn (array $data) => CheckoutItem::fromArray($data), $cartItemsAllUnselectedData);
        $contextAllUnselected = new CalculationContext($user, $cartItemsAllUnselected);
        $this->assertTrue($calculator->supports($contextAllUnselected));
    }

    /**
     * 创建模拟的 SKU 对象及其价格
     */
    private function createMockSku(string $id, float $price): Sku
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn($id);

        // 创建模拟的价格对象
        $priceObj = $this->createMock(Price::class);
        $priceObj->method('getPrice')->willReturn((string) $price);
        $priceObj->method('getType')->willReturn(PriceType::SALE);

        // SKU 直接返回价格信息
        $sku->method('getMarketPrice')->willReturn((string) $price);

        return $sku;
    }
}
