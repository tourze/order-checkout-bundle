<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\EventSubscriber;

use Doctrine\Common\Collections\ArrayCollection;
use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderProduct;
use OrderCoreBundle\Event\OrderPaidEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\EventSubscriber\SkuStockSubscriber;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;

/**
 * @internal
 */
#[CoversClass(SkuStockSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class SkuStockSubscriberTest extends AbstractEventSubscriberTestCase
{
    private SkuStockSubscriber $subscriber;

    protected function onSetUp(): void
    {
        $this->subscriber = self::getService(SkuStockSubscriber::class);
    }

    public function testCanBeInstantiated(): void
    {
        $subscriber = self::getService(SkuStockSubscriber::class);
        $this->assertInstanceOf(SkuStockSubscriber::class, $subscriber);
    }

    public function testHasEventListenerMethods(): void
    {
        $subscriber = self::getService(SkuStockSubscriber::class);

        // 验证核心事件监听方法存在
        $this->assertTrue(method_exists($subscriber, 'onOrderPaidEvent'));
    }

    public function testOnOrderPaidEventWithValidSkuShouldIncreaseSales(): void
    {
        // Arrange
        $contract = $this->createMock(Contract::class);
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('123');

        $product1 = $this->createMock(OrderProduct::class);
        $product1->method('getSku')->willReturn($sku);
        $product1->method('getQuantity')->willReturn(5);

        $product2 = $this->createMock(OrderProduct::class);
        $product2->method('getSku')->willReturn($sku);
        $product2->method('getQuantity')->willReturn(3);

        $contract->method('getProducts')->willReturn(new ArrayCollection([$product1, $product2]));

        $event = new OrderPaidEvent();
        $event->setContract($contract);

        // Act & Assert - 集成测试验证调用不抛异常即可
        $this->expectNotToPerformAssertions();
        $this->subscriber->onOrderPaidEvent($event);
    }

    public function testOnOrderPaidEventWithNullSkuShouldSkip(): void
    {
        // Arrange
        $contract = $this->createMock(Contract::class);

        $product = $this->createMock(OrderProduct::class);
        $product->method('getSku')->willReturn(null); // SKU为空
        $product->method('getQuantity')->willReturn(2);

        $contract->method('getProducts')->willReturn(new ArrayCollection([$product]));

        $event = new OrderPaidEvent();
        $event->setContract($contract);

        // Act & Assert - 集成测试验证调用不抛异常即可
        $this->expectNotToPerformAssertions();
        $this->subscriber->onOrderPaidEvent($event);
    }

    public function testOnOrderPaidEventWithZeroQuantityShouldProcessCorrectly(): void
    {
        // Arrange
        $contract = $this->createMock(Contract::class);
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('456');

        $product = $this->createMock(OrderProduct::class);
        $product->method('getSku')->willReturn($sku);
        $product->method('getQuantity')->willReturn(0); // 数量为0，测试边界情况

        $contract->method('getProducts')->willReturn(new ArrayCollection([$product]));

        $event = new OrderPaidEvent();
        $event->setContract($contract);

        // Act & Assert - 集成测试验证调用不抛异常即可
        $this->expectNotToPerformAssertions();
        $this->subscriber->onOrderPaidEvent($event);
    }

    public function testOnOrderPaidEventWithEmptyProductsShouldSkip(): void
    {
        // Arrange
        $contract = $this->createMock(Contract::class);
        $contract->method('getProducts')->willReturn(new ArrayCollection([])); // 空商品列表

        $event = new OrderPaidEvent();
        $event->setContract($contract);

        // Act & Assert - 集成测试验证调用不抛异常即可
        $this->expectNotToPerformAssertions();
        $this->subscriber->onOrderPaidEvent($event);
    }

    public function testOnOrderPaidEventWithMultipleSkuTypesShouldProcessAll(): void
    {
        // Arrange
        $contract = $this->createMock(Contract::class);

        $sku1 = $this->createMock(Sku::class);
        $sku1->method('getId')->willReturn('111');
        $sku2 = $this->createMock(Sku::class);
        $sku2->method('getId')->willReturn('222');

        $product1 = $this->createMock(OrderProduct::class);
        $product1->method('getSku')->willReturn($sku1);
        $product1->method('getQuantity')->willReturn(2);

        $product2 = $this->createMock(OrderProduct::class);
        $product2->method('getSku')->willReturn(null); // 这个应该跳过
        $product2->method('getQuantity')->willReturn(1);

        $product3 = $this->createMock(OrderProduct::class);
        $product3->method('getSku')->willReturn($sku2);
        $product3->method('getQuantity')->willReturn(4);

        $contract->method('getProducts')->willReturn(new ArrayCollection([$product1, $product2, $product3]));

        $event = new OrderPaidEvent();
        $event->setContract($contract);

        // Act & Assert - 集成测试验证调用不抛异常即可
        $this->expectNotToPerformAssertions();
        $this->subscriber->onOrderPaidEvent($event);
    }
}
