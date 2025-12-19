<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\ShippingContext;

/**
 * @internal
 */
#[CoversClass(ShippingContext::class)]
final class ShippingContextTest extends TestCase
{
    private UserInterface $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createMock(UserInterface::class);
    }

    /**
     * 创建 CheckoutItem 数组的辅助方法
     *
     * @param array<array{skuId?: int|string, quantity?: int, selected?: bool}> $itemsData
     *
     * @return CheckoutItem[]
     */
    private function createCheckoutItems(array $itemsData): array
    {
        $items = [];
        foreach ($itemsData as $index => $itemData) {
            $items[] = new CheckoutItem(
                skuId: $itemData['skuId'] ?? $index + 1,
                quantity: $itemData['quantity'] ?? 1,
                selected: $itemData['selected'] ?? true
            );
        }

        return $items;
    }

    public function testConstructorInitializesAllRequiredProperties(): void
    {
        // Arrange: 准备基本数据
        $items = $this->createCheckoutItems([['quantity' => 2]]);
        $region = 'beijing';

        // Act: 创建运费上下文
        $context = new ShippingContext($this->user, $items, $region);

        // Assert: 验证必需属性
        $this->assertSame($this->user, $context->getUser());
        $this->assertEquals($items, $context->getItems());
        $this->assertEquals($region, $context->getRegion());
        $this->assertEmpty($context->getMetadata());
    }

    public function testConstructorWithMetadata(): void
    {
        // Arrange: 准备包含元数据的数据
        $items = $this->createCheckoutItems([['quantity' => 1]]);
        $region = 'shanghai';
        $metadata = ['source' => 'mobile', 'version' => '1.2.3'];

        // Act: 创建运费上下文
        $context = new ShippingContext($this->user, $items, $region, $metadata);

        // Assert: 验证包含元数据
        $this->assertEquals($metadata, $context->getMetadata());
        $this->assertEquals('mobile', $context->getMetadataValue('source'));
        $this->assertEquals('1.2.3', $context->getMetadataValue('version'));
    }

    public function testGetMetadataValueWithDefaultValue(): void
    {
        // Arrange: 创建空元数据的上下文
        $context = new ShippingContext($this->user, [], 'beijing');

        // Act: 获取不存在的元数据值
        $value = $context->getMetadataValue('non_existent_key', 'default_value');
        $nullValue = $context->getMetadataValue('another_key');

        // Assert: 验证默认值处理
        $this->assertEquals('default_value', $value);
        $this->assertNull($nullValue);
    }

    public function testGetTotalWeightWithSelectedItems(): void
    {
        // Arrange: 准备包含选中商品的数据
        $items = $this->createCheckoutItems([
            ['selected' => true, 'quantity' => 2],   // 2 * 0.5 = 1.0kg
            ['selected' => true, 'quantity' => 3],   // 3 * 0.5 = 1.5kg
            ['selected' => false, 'quantity' => 10], // 未选中，不计入
        ]);
        $context = new ShippingContext($this->user, $items, 'beijing');

        // Act: 计算总重量
        $totalWeight = $context->getTotalWeight();

        // Assert: 验证总重量（只计算选中的商品）
        $this->assertEquals(2.5, $totalWeight); // (2 + 3) * 0.5
    }

    public function testGetTotalWeightWithDefaultSelectedItems(): void
    {
        // Arrange: 准备没有selected标记的商品（默认为true）
        $items = $this->createCheckoutItems([
            ['quantity' => 4], // 默认selected=true，4 * 0.5 = 2.0kg
            ['quantity' => 1], // 默认selected=true，1 * 0.5 = 0.5kg
        ]);
        $context = new ShippingContext($this->user, $items, 'beijing');

        // Act: 计算总重量
        $totalWeight = $context->getTotalWeight();

        // Assert: 验证总重量
        $this->assertEquals(2.5, $totalWeight); // (4 + 1) * 0.5
    }

    public function testGetTotalWeightWithZeroQuantityItems(): void
    {
        // Arrange: 准备数量为0的商品
        $items = [
            new CheckoutItem(1, 0, true), // 数量为0
            new CheckoutItem(2, 0, true), // 数量为0
        ];
        $context = new ShippingContext($this->user, $items, 'beijing');

        // Act: 计算总重量
        $totalWeight = $context->getTotalWeight();

        // Assert: 验证零重量
        $this->assertEquals(0.0, $totalWeight);
    }

    public function testGetTotalValueWithSelectedItems(): void
    {
        // Arrange: 准备包含价格和数量的商品
        $items = [
            new CheckoutItem(1, 2, true),
            new CheckoutItem(2, 3, true),
            new CheckoutItem(3, 1, false),
        ];
        $context = new ShippingContext($this->user, $items, 'beijing');

        // Act: 计算总价值
        $totalValue = $context->getTotalValue();

        // Assert: 验证总价值 (默认价格100.0)
        $this->assertEquals(500.0, $totalValue); // (2*100) + (3*100) = 500.0
    }

    public function testGetTotalValueWithDefaultValues(): void
    {
        // Arrange: 准备缺少价格或数量的商品
        $items = [
            new CheckoutItem(1, 2, true),
            new CheckoutItem(2, 0, true),
            new CheckoutItem(3, 3, true),
        ];
        $context = new ShippingContext($this->user, $items, 'beijing');

        // Act: 计算总价值
        $totalValue = $context->getTotalValue();

        // Assert: 验证默认值处理 (默认价格100.0)
        $this->assertEquals(500.0, $totalValue); // (2*100) + (0*100) + (3*100) = 500.0
    }

    public function testGetTotalValueWithFloatPrecision(): void
    {
        // Arrange: 准备浮点数精度测试数据
        $items = $this->createCheckoutItems([
            ['selected' => true, 'quantity' => 3], // CheckoutItem 使用默认价格
            ['selected' => true, 'quantity' => 1],
        ]);
        $context = new ShippingContext($this->user, $items, 'beijing');

        // Act: 计算总价值
        $totalValue = $context->getTotalValue();

        // Assert: 验证浮点数精度 (4 items * 100.0 默认价格)
        $this->assertEqualsWithDelta(400.0, $totalValue, 0.001);
    }

    public function testGetTotalQuantityWithSelectedItems(): void
    {
        // Arrange: 准备包含选中商品的数据
        $items = $this->createCheckoutItems([
            ['selected' => true, 'quantity' => 5],
            ['selected' => true, 'quantity' => 3],
            ['selected' => false, 'quantity' => 10], // 未选中，不计入
            ['quantity' => 2], // 默认selected=true
        ]);
        $context = new ShippingContext($this->user, $items, 'beijing');

        // Act: 计算总数量
        $totalQuantity = $context->getTotalQuantity();

        // Assert: 验证总数量
        $this->assertEquals(10, $totalQuantity); // 5 + 3 + 2
    }

    public function testGetTotalQuantityWithZeroQuantity(): void
    {
        // Arrange: 准备数量为0或缺失的商品
        $items = $this->createCheckoutItems([
            ['selected' => true, 'quantity' => 0],
            ['selected' => true, 'quantity' => 1], // createCheckoutItems 默认quantity为1，不能缺失
            ['selected' => false, 'quantity' => 5], // 未选中
        ]);
        $context = new ShippingContext($this->user, $items, 'beijing');

        // Act: 计算总数量
        $totalQuantity = $context->getTotalQuantity();

        // Assert: 验证数量 (0 + 1 = 1, 因为只有selected的会被计算)
        $this->assertEquals(1, $totalQuantity);
    }

    public function testGetTotalQuantityWithLargeNumbers(): void
    {
        // Arrange: 准备大数量的商品
        $items = $this->createCheckoutItems([
            ['selected' => true, 'quantity' => 999],
            ['selected' => true, 'quantity' => 1001],
        ]);
        $context = new ShippingContext($this->user, $items, 'beijing');

        // Act: 计算总数量
        $totalQuantity = $context->getTotalQuantity();

        // Assert: 验证大数量计算
        $this->assertEquals(2000, $totalQuantity);
    }

    public function testWithEmptyItemsArray(): void
    {
        // Arrange: 创建空商品数组的上下文
        $context = new ShippingContext($this->user, [], 'beijing');

        // Act: 计算各种总值
        $totalWeight = $context->getTotalWeight();
        $totalValue = $context->getTotalValue();
        $totalQuantity = $context->getTotalQuantity();

        // Assert: 验证空数组结果
        $this->assertEquals(0.0, $totalWeight);
        $this->assertEquals(0.0, $totalValue);
        $this->assertEquals(0, $totalQuantity);
        $this->assertEmpty($context->getItems());
    }

    public function testWithComplexItemsStructure(): void
    {
        // Arrange: 准备复杂的商品结构
        $items = $this->createCheckoutItems([
            ['selected' => true, 'quantity' => 2, 'skuId' => 'SKU001'],
            ['selected' => true, 'quantity' => 1, 'skuId' => 'SKU002'],
        ]);
        $region = 'shanghai';
        $metadata = ['checkout_source' => 'web', 'user_agent' => 'Chrome'];

        // Act: 创建上下文
        $context = new ShippingContext($this->user, $items, $region, $metadata);

        // Assert: 验证复杂结构处理 (使用默认价格100.0)
        $this->assertEquals(300.0, $context->getTotalValue()); // (2 * 100) + (1 * 100)
        $this->assertEquals(1.5, $context->getTotalWeight());   // (2 + 1) * 0.5
        $this->assertEquals(3, $context->getTotalQuantity());   // 2 + 1
        $this->assertEquals('shanghai', $context->getRegion());
        $this->assertEquals('web', $context->getMetadataValue('checkout_source'));
        $this->assertCount(2, $context->getItems());
    }

    public function testReadOnlyPropertiesAreAccessible(): void
    {
        // Arrange: 创建上下文对象
        $items = $this->createCheckoutItems([['quantity' => 1]]);
        $region = 'test_region';
        $metadata = ['test_key' => 'test_value'];
        $context = new ShippingContext($this->user, $items, $region, $metadata);

        // Act & Assert: 验证所有属性都可访问
        $this->assertInstanceOf(UserInterface::class, $context->getUser());
        $this->assertIsArray($context->getItems());
        $this->assertIsString($context->getRegion());
        $this->assertIsArray($context->getMetadata());

        // 验证计算方法
        $this->assertIsFloat($context->getTotalWeight());
        $this->assertIsFloat($context->getTotalValue());
        $this->assertIsInt($context->getTotalQuantity());
    }
}
