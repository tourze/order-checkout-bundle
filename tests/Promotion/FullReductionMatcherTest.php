<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Promotion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\OrderCheckoutBundle\Contract\PromotionMatcherInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PromotionResult;
use Tourze\OrderCheckoutBundle\Promotion\FullReductionMatcher;
use Tourze\ProductCoreBundle\Entity\Sku;

/**
 * @internal
 */
#[CoversClass(FullReductionMatcher::class)]
final class FullReductionMatcherTest extends TestCase
{
    private UserInterface $user;

    protected function setUp(): void
    {
        $this->user = $this->createMock(UserInterface::class);
    }

    public function testMatcherImplementsInterface(): void
    {
        // Act: 创建匹配器
        $matcher = new FullReductionMatcher();

        // Assert: 验证实现接口
        $this->assertInstanceOf(PromotionMatcherInterface::class, $matcher);
    }

    public function testMatcherWithDefaultConstructor(): void
    {
        // Act: 创建默认匹配器
        $matcher = new FullReductionMatcher();

        // Assert: 验证默认值
        $this->assertEquals('full_reduction', $matcher->getType());
        $this->assertEquals(100, $matcher->getPriority());
    }

    public function testMatcherWithCustomParameters(): void
    {
        // Arrange: 创建自定义匹配器
        $threshold = 200.0;
        $reduction = 30.0;
        $description = '满200减30';

        // Act: 创建匹配器
        $matcher = new FullReductionMatcher($threshold, $reduction, $description);

        // Assert: 验证自定义参数
        $this->assertEquals('full_reduction', $matcher->getType());
        $this->assertEquals(100, $matcher->getPriority());
    }

    public function testMatchWithAmountAboveThreshold(): void
    {
        // Arrange: 准备超过门槛的数据
        $items = $this->createMockItems([
            ['price' => 60.0, 'quantity' => 2, 'selected' => true],  // 120.0
        ]);
        $context = new CalculationContext($this->user, $items, []);
        $matcher = new FullReductionMatcher(100.0, 10.0, '满100减10');

        // Act: 执行匹配
        $result = $matcher->match($context);

        // Assert: 验证匹配结果
        $this->assertInstanceOf(PromotionResult::class, $result);
        $this->assertEquals(10.0, $result->getDiscount());
        $this->assertNotEmpty($result->getPromotions());
        $this->assertArrayHasKey('full_reduction', $result->getPromotions());

        $promotions = $result->getPromotions();
        $this->assertIsArray($promotions);
        $promotion = $promotions['full_reduction'];
        $this->assertIsArray($promotion);
        $this->assertArrayHasKey('type', $promotion);
        $this->assertEquals('full_reduction', $promotion['type']);
        $this->assertArrayHasKey('description', $promotion);
        $this->assertEquals('满100减10', $promotion['description']);
        $this->assertArrayHasKey('threshold', $promotion);
        $this->assertEquals(100.0, $promotion['threshold']);
        $this->assertArrayHasKey('reduction', $promotion);
        $this->assertEquals(10.0, $promotion['reduction']);
        $this->assertArrayHasKey('applied_amount', $promotion);
        $this->assertEquals(120.0, $promotion['applied_amount']);
    }

    public function testMatchWithAmountBelowThreshold(): void
    {
        // Arrange: 准备低于门槛的数据
        $items = $this->createMockItems([
            ['price' => 30.0, 'quantity' => 2, 'selected' => true],  // 60.0 < 100.0
        ]);
        $context = new CalculationContext($this->user, $items, []);
        $matcher = new FullReductionMatcher(100.0, 10.0, '满100减10');

        // Act: 执行匹配
        $result = $matcher->match($context);

        // Assert: 验证空结果
        $this->assertEquals(0.0, $result->getDiscount());
        $this->assertEmpty($result->getPromotions());
        $this->assertEmpty($result->getDetails());
    }

    public function testMatchWithExactThresholdAmount(): void
    {
        // Arrange: 准备刚好达到门槛的数据
        $items = $this->createMockItems([
            ['price' => 50.0, 'quantity' => 2, 'selected' => true],  // 刚好100.0
        ]);
        $context = new CalculationContext($this->user, $items, []);
        $matcher = new FullReductionMatcher(100.0, 15.0, '满100减15');

        // Act: 执行匹配
        $result = $matcher->match($context);

        // Assert: 验证匹配成功
        $this->assertEquals(15.0, $result->getDiscount());
        $promotions = $result->getPromotions();
        $this->assertIsArray($promotions);
        $this->assertArrayHasKey('full_reduction', $promotions);
        $promotion = $promotions['full_reduction'];
        $this->assertIsArray($promotion);
        $this->assertArrayHasKey('applied_amount', $promotion);
        $this->assertEquals(100.0, $promotion['applied_amount']);
    }

    public function testMatchWithMixedSelectedItems(): void
    {
        // Arrange: 准备混合选中状态的商品
        $items = $this->createMockItems([
            ['price' => 60.0, 'quantity' => 1, 'selected' => true],   // 60.0
            ['price' => 50.0, 'quantity' => 1, 'selected' => true],   // 50.0
            ['price' => 100.0, 'quantity' => 1, 'selected' => false], // 不计入
        ]);
        $context = new CalculationContext($this->user, $items, []);
        $matcher = new FullReductionMatcher(100.0, 20.0, '满100减20');

        // Act: 执行匹配
        $result = $matcher->match($context);

        // Assert: 验证只计算选中商品（110.0 >= 100.0）
        $this->assertEquals(20.0, $result->getDiscount());
        $promotions = $result->getPromotions();
        $this->assertIsArray($promotions);
        $this->assertArrayHasKey('full_reduction', $promotions);
        $promotion = $promotions['full_reduction'];
        $this->assertIsArray($promotion);
        $this->assertArrayHasKey('applied_amount', $promotion);
        $this->assertEquals(110.0, $promotion['applied_amount']);
    }

    public function testMatchWithItemsWithoutSku(): void
    {
        // Arrange: 准备没有SKU的商品
        $items = $this->createMockItems([
            ['price' => 60.0, 'quantity' => 2, 'selected' => true, 'has_sku' => false],
        ]);
        $context = new CalculationContext($this->user, $items, []);
        $matcher = new FullReductionMatcher(100.0, 10.0, '满100减10');

        // Act: 执行匹配
        $result = $matcher->match($context);

        // Assert: 验证空结果（没有有效SKU）
        $this->assertEquals(0.0, $result->getDiscount());
    }

    public function testMatchWithZeroPriceItems(): void
    {
        // Arrange: 准备零价商品
        $items = $this->createMockItems([
            ['price' => 0.0, 'quantity' => 10, 'selected' => true],
        ]);
        $context = new CalculationContext($this->user, $items, []);
        $matcher = new FullReductionMatcher(100.0, 10.0, '满100减10');

        // Act: 执行匹配
        $result = $matcher->match($context);

        // Assert: 验证空结果（总金额为0）
        $this->assertEquals(0.0, $result->getDiscount());
    }

    public function testSupportsWithEmptyItems(): void
    {
        // Arrange: 创建空商品列表的上下文
        $context = new CalculationContext($this->user, [], []);
        $matcher = new FullReductionMatcher();

        // Act: 检查是否支持
        $supports = $matcher->supports($context);

        // Assert: 验证不支持空列表
        $this->assertFalse($supports);
    }

    public function testSupportsWithValidItems(): void
    {
        // Arrange: 创建有效商品列表的上下文
        $items = $this->createMockItems([['price' => 50.0, 'quantity' => 1, 'selected' => true]]);
        $context = new CalculationContext($this->user, $items, []);
        $matcher = new FullReductionMatcher();

        // Act: 检查是否支持
        $supports = $matcher->supports($context);

        // Assert: 验证支持有效列表
        $this->assertTrue($supports);
    }

    public function testGetType(): void
    {
        // Arrange: 创建匹配器
        $matcher = new FullReductionMatcher();

        // Act: 获取类型
        $type = $matcher->getType();

        // Assert: 验证类型
        $this->assertEquals('full_reduction', $type);
    }

    public function testGetPriority(): void
    {
        // Arrange: 创建匹配器
        $matcher = new FullReductionMatcher();

        // Act: 获取优先级
        $priority = $matcher->getPriority();

        // Assert: 验证优先级
        $this->assertEquals(100, $priority);
    }

    public function testPromotionResultDetails(): void
    {
        // Arrange: 准备测试数据
        $items = $this->createMockItems([
            ['price' => 150.0, 'quantity' => 1, 'selected' => true],
        ]);
        $context = new CalculationContext($this->user, $items, []);
        $matcher = new FullReductionMatcher(100.0, 25.0, '满100减25');

        // Act: 执行匹配
        $result = $matcher->match($context);
        $details = $result->getDetails();

        // Assert: 验证详情结构
        $this->assertIsArray($details);
        $this->assertArrayHasKey('full_reduction', $details);
        $fullReductionDetail = $details['full_reduction'];
        $this->assertIsArray($fullReductionDetail);
        $this->assertArrayHasKey('total_amount', $fullReductionDetail);
        $this->assertEquals(150.0, $fullReductionDetail['total_amount']);
        $this->assertArrayHasKey('threshold', $fullReductionDetail);
        $this->assertEquals(100.0, $fullReductionDetail['threshold']);
        $this->assertArrayHasKey('reduction', $fullReductionDetail);
        $this->assertEquals(25.0, $fullReductionDetail['reduction']);
        $this->assertArrayHasKey('saved', $fullReductionDetail);
        $this->assertEquals(25.0, $fullReductionDetail['saved']);
    }

    /**
     * 创建Mock商品项
     *
     * @param array<int, array<string, mixed>> $itemsData
     * @return CheckoutItem[]
     */
    private function createMockItems(array $itemsData): array
    {
        $items = [];
        foreach ($itemsData as $index => $data) {
            $sku = null;
            if (($data['has_sku'] ?? true) === true) {
                $sku = new class extends Sku {
                    private ?string $marketPrice = null;

                    public function setMarketPrice(?string $price): void
                    {
                        $this->marketPrice = $price;
                    }

                    public function getMarketPrice(): ?string
                    {
                        return $this->marketPrice;
                    }

                    public function getDisplayPrice(): float
                    {
                        return null !== $this->marketPrice ? (float) $this->marketPrice : 0.0;
                    }
                };
                $price = $data['price'] ?? 0.0;
                $this->assertIsFloat($price);
                $sku->setMarketPrice(number_format($price, 2, '.', ''));
            }

            $quantity = $data['quantity'] ?? 1;
            $this->assertIsInt($quantity);
            $selected = $data['selected'] ?? true;
            $this->assertIsBool($selected);
            $item = new CheckoutItem(
                skuId: $index + 1,
                quantity: $quantity,
                selected: $selected,
                sku: $sku,
                id: $index + 1
            );

            $items[] = $item;
        }

        return $items;
    }
}
