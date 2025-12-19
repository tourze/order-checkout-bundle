<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\EventSubscriber\PaymentSubscriber;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(PaymentSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class PaymentSubscriberTest extends AbstractEventSubscriberTestCase
{
    protected function onSetUp(): void
    {
        // 测试初始化
    }

    public function testCanBeInstantiated(): void
    {
        $subscriber = self::getService(PaymentSubscriber::class);
        $this->assertInstanceOf(PaymentSubscriber::class, $subscriber);
    }

    public function testServiceCanBeRetrievedFromContainer(): void
    {
        $subscriber = self::getService(PaymentSubscriber::class);
        $this->assertInstanceOf(PaymentSubscriber::class, $subscriber);
    }

    public function testHasEventListenerMethods(): void
    {
        $subscriber = self::getService(PaymentSubscriber::class);

        $this->assertTrue(method_exists($subscriber, 'onPaymentSuccess'));
        $this->assertTrue(method_exists($subscriber, 'onPaymentFailed'));
    }

    public function testOnPaymentSuccessMethodExists(): void
    {
        $subscriber = self::getService(PaymentSubscriber::class);
        $this->assertTrue(method_exists($subscriber, 'onPaymentSuccess'));

        // 验证方法可见性
        $reflection = new \ReflectionMethod(PaymentSubscriber::class, 'onPaymentSuccess');
        $this->assertTrue($reflection->isPublic());
    }

    public function testOnPaymentFailedMethodExists(): void
    {
        $subscriber = self::getService(PaymentSubscriber::class);
        $this->assertTrue(method_exists($subscriber, 'onPaymentFailed'));

        // 验证方法可见性
        $reflection = new \ReflectionMethod(PaymentSubscriber::class, 'onPaymentFailed');
        $this->assertTrue($reflection->isPublic());
    }
}
