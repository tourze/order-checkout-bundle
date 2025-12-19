<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\EventSubscriber\IntegralSubscriber;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(IntegralSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class IntegralSubscriberTest extends AbstractEventSubscriberTestCase
{
    private IntegralSubscriber $subscriber;

    protected function onSetUp(): void
    {
        // 从容器获取服务实例（集成测试规范）
        $this->subscriber = self::getService(IntegralSubscriber::class);
    }

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(IntegralSubscriber::class, $this->subscriber);
    }

    public function testServiceCanBeRetrievedFromContainer(): void
    {
        $subscriber = self::getService(IntegralSubscriber::class);
        $this->assertInstanceOf(IntegralSubscriber::class, $subscriber);
    }

    public function testHasEventListenerMethods(): void
    {
        $this->assertTrue(method_exists($this->subscriber, 'checkAndDeductIntegral'));
        $this->assertTrue(method_exists($this->subscriber, 'createIntegralRecord'));
        $this->assertTrue(method_exists($this->subscriber, 'refundIntegralOnFailure'));
        $this->assertTrue(method_exists($this->subscriber, 'refundIntegralOnCancel'));
        $this->assertTrue(method_exists($this->subscriber, 'refundIntegralOnProductRefund'));
    }

    public function testCheckAndDeductIntegral(): void
    {
        $reflection = new \ReflectionMethod($this->subscriber, 'checkAndDeductIntegral');
        $this->assertTrue($reflection->isPublic());
    }

    public function testCreateIntegralRecord(): void
    {
        $reflection = new \ReflectionMethod($this->subscriber, 'createIntegralRecord');
        $this->assertTrue($reflection->isPublic());
    }

    public function testRefundIntegralOnFailure(): void
    {
        $reflection = new \ReflectionMethod($this->subscriber, 'refundIntegralOnFailure');
        $this->assertTrue($reflection->isPublic());
    }

    public function testRefundIntegralOnCancel(): void
    {
        $reflection = new \ReflectionMethod($this->subscriber, 'refundIntegralOnCancel');
        $this->assertTrue($reflection->isPublic());
    }

    public function testRefundIntegralOnProductRefund(): void
    {
        $reflection = new \ReflectionMethod($this->subscriber, 'refundIntegralOnProductRefund');
        $this->assertTrue($reflection->isPublic());
    }
}
