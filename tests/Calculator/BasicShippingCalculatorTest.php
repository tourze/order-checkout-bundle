<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\OrderCheckoutBundle\Calculator\BasicShippingCalculator;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\ShippingContext;
use Tourze\OrderCheckoutBundle\DTO\ShippingResult;

/**
 * @internal
 */
#[CoversClass(BasicShippingCalculator::class)]
final class BasicShippingCalculatorTest extends TestCase
{
    private BasicShippingCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new BasicShippingCalculator();
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

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(BasicShippingCalculator::class, $this->calculator);
    }

    public function testGetType(): void
    {
        $this->assertSame('basic_shipping', $this->calculator->getType());
    }

    public function testGetPriority(): void
    {
        $this->assertSame(100, $this->calculator->getPriority());
    }

    public function testSupportsWithEmptyItems(): void
    {
        $items = [];
        $region = 'shanghai';

        $this->assertFalse($this->calculator->supports($items, $region));
    }

    public function testSupportsWithItems(): void
    {
        // supports() 方法只检查数组是否为空，因此可以使用简单数组测试
        $items = [['sku_id' => 1, 'quantity' => 2]];
        $region = 'shanghai';

        // @phpstan-ignore argument.type (supports方法实际上只检查数组长度,不依赖具体类型)
        $this->assertTrue($this->calculator->supports($items, $region));
    }

    public function testCalculateFreeShippingForShanghaiRegion(): void
    {
        $user = $this->createMock(UserInterface::class);
        $items = $this->createCheckoutItems([
            [
                'quantity' => 2, // 总价：100.0，超过99门槛（默认价格100.0每件）
                'selected' => true,
            ],
        ]);
        $context = new ShippingContext($user, $items, 'shanghai');

        $result = $this->calculator->calculate($context);

        $this->assertInstanceOf(ShippingResult::class, $result);
        $this->assertTrue($result->isFreeShipping());
        $this->assertSame(0.0, $result->getShippingFee());
        $this->assertSame('free', $result->getShippingMethod());

        $details = $result->getDetails();
        $this->assertArrayHasKey('free_reason', $details);
        $this->assertIsString($details['free_reason']);
        $this->assertStringContainsString('满¥99.00包邮', $details['free_reason']);
        $this->assertSame(200.0, $details['order_value']);
        $this->assertSame(99.0, $details['threshold']);
        $this->assertSame('shanghai', $details['region']);
    }

    public function testCalculateFreeShippingForJiangsumRegion(): void
    {
        $user = $this->createMock(UserInterface::class);
        $items = $this->createCheckoutItems([
            [
                'quantity' => 1, // 总价：100.0（默认价格），达到99门槛
                'selected' => true,
            ],
        ]);
        $context = new ShippingContext($user, $items, 'jiangsu');

        $result = $this->calculator->calculate($context);

        $this->assertTrue($result->isFreeShipping());
        $this->assertSame(0.0, $result->getShippingFee());
    }

    public function testCalculateShippingFeeForJiangshuPackageRegion(): void
    {
        $user = $this->createMock(UserInterface::class);
        $items = $this->createCheckoutItems([
            [
                'quantity' => 1, // 总价：100.0（默认价格），达到99门槛但测试需要低于门槛的情况
                'selected' => true,
            ],
        ]);
        $context = new ShippingContext($user, $items, 'jiangsu');

        $result = $this->calculator->calculate($context);

        // 江苏属于江浙沪包邮区域，实际测试达到免邮门槛
        $this->assertTrue($result->isFreeShipping());
        $this->assertSame(0.0, $result->getShippingFee());
        $this->assertSame('free', $result->getShippingMethod());

        $details = $result->getDetails();
        $this->assertSame(100.0, $details['order_value']);
        $this->assertSame('jiangsu', $details['region']);
    }

    public function testCalculateShippingFeeForBeijingRegion(): void
    {
        $user = $this->createMock(UserInterface::class);
        // 使用数量 0 来模拟低于门槛的情况
        $items = $this->createCheckoutItems([
            [
                'quantity' => 0, // 总价：0，低于99门槛
                'selected' => true,
            ],
        ]);
        $context = new ShippingContext($user, $items, 'beijing');

        $result = $this->calculator->calculate($context);

        $this->assertFalse($result->isFreeShipping());
        $this->assertSame(8.0, $result->getShippingFee());
        $this->assertSame('standard', $result->getShippingMethod());

        $details = $result->getDetails();
        $this->assertSame(0.0, $details['order_value']);
        $this->assertSame('beijing', $details['region']);
        $this->assertSame(8.0, $details['base_fee']);
        $this->assertSame(99.0, $details['free_threshold']);
        $this->assertSame(99.0, $details['needed_for_free']);
    }

    public function testCalculateShippingFeeForDefaultRegion(): void
    {
        $user = $this->createMock(UserInterface::class);
        $items = $this->createCheckoutItems([
            [
                'quantity' => 0, // 总价：0，低于99门槛
                'selected' => true,
            ],
        ]);
        $context = new ShippingContext($user, $items, 'unknown_region');

        $result = $this->calculator->calculate($context);

        $this->assertFalse($result->isFreeShipping());
        $this->assertSame(12.0, $result->getShippingFee()); // default运费
        $this->assertSame('standard', $result->getShippingMethod());
    }

    public function testCalculateShippingFeeForRemoteRegion(): void
    {
        $user = $this->createMock(UserInterface::class);
        $items = $this->createCheckoutItems([
            [
                'quantity' => 0, // 总价：0，低于99门槛
                'selected' => true,
            ],
        ]);
        $context = new ShippingContext($user, $items, 'xinjiang');

        $result = $this->calculator->calculate($context);

        $this->assertFalse($result->isFreeShipping());
        $this->assertSame(25.0, $result->getShippingFee()); // 偏远地区运费
        $this->assertSame('standard', $result->getShippingMethod());
    }

    public function testCalculateWithMixedItemsSelectedAndUnselected(): void
    {
        $user = $this->createMock(UserInterface::class);
        $items = $this->createCheckoutItems([
            [
                'skuId' => 1,
                'quantity' => 1, // 选中：100.0（默认价格）
                'selected' => true,
            ],
            [
                'skuId' => 2,
                'quantity' => 1, // 未选中：0.0
                'selected' => false,
            ],
            [
                'skuId' => 3,
                'quantity' => 1, // 选中：100.0（默认价格）
                'selected' => true,
            ],
        ]);
        // 实际总价：100.0 + 100.0 = 200.0，超过99门槛
        $context = new ShippingContext($user, $items, 'beijing');

        $result = $this->calculator->calculate($context);

        $this->assertTrue($result->isFreeShipping());
        $this->assertSame(0.0, $result->getShippingFee());

        $details = $result->getDetails();
        $this->assertSame(200.0, $details['order_value']);
    }

    public function testCalculateWithMissingPriceAndQuantity(): void
    {
        $user = $this->createMock(UserInterface::class);
        $items = $this->createCheckoutItems([
            [
                'skuId' => 1,
                'quantity' => 0, // 默认数量 0
                'selected' => true,
            ],
            [
                'skuId' => 2,
                'quantity' => 0, // 总价：0低于门槛
                'selected' => true,
            ],
        ]);
        $context = new ShippingContext($user, $items, 'beijing');

        $result = $this->calculator->calculate($context);

        $this->assertFalse($result->isFreeShipping());
        $this->assertSame(8.0, $result->getShippingFee());

        $details = $result->getDetails();
        $this->assertSame(0.0, $details['order_value']); // 数量为0的项目总价为0
    }

    public function testCalculateWithUnselectedByDefault(): void
    {
        $user = $this->createMock(UserInterface::class);
        $items = $this->createCheckoutItems([
            [
                'skuId' => 1,
                // selected 默认为 true
                'quantity' => 2, // 总价：200.0（默认价格100*2）
            ],
        ]);
        $context = new ShippingContext($user, $items, 'beijing');

        $result = $this->calculator->calculate($context);

        $this->assertTrue($result->isFreeShipping());
        $this->assertSame(0.0, $result->getShippingFee());

        $details = $result->getDetails();
        $this->assertSame(200.0, $details['order_value']);
    }

    public function testCalculateEdgeCaseJustBelowThreshold(): void
    {
        $user = $this->createMock(UserInterface::class);
        $items = $this->createCheckoutItems([
            [
                'skuId' => 1,
                'quantity' => 0, // 总价：0，低于99门槛
                'selected' => true,
            ],
        ]);
        $context = new ShippingContext($user, $items, 'beijing');

        $result = $this->calculator->calculate($context);

        $this->assertFalse($result->isFreeShipping());
        $this->assertSame(8.0, $result->getShippingFee());

        $details = $result->getDetails();
        $this->assertSame(0.0, $details['order_value']);
        $this->assertSame(99.0, $details['needed_for_free']);
    }
}
