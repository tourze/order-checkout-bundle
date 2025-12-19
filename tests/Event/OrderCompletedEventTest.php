<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Event\OrderCreateAfterEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

#[CoversClass(OrderCreateAfterEvent::class)]
class OrderCompletedEventTest extends AbstractEventTestCase
{
    public function testConstructorInitializesRequiredProperties(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('test_user');

        $event = new OrderCreateAfterEvent(
            orderId: 123,
            orderNumber: 'ORD123456',
            user: $user,
            totalAmount: 199.99,
            paymentRequired: true,
            orderState: 'pending',
            metadata: []
        );

        $this->assertSame(123, $event->getOrderId());
        $this->assertSame('ORD123456', $event->getOrderNumber());
        $this->assertSame($user, $event->getUser());
        $this->assertSame(199.99, $event->getTotalAmount());
        $this->assertTrue($event->isPaymentRequired());
        $this->assertSame('pending', $event->getOrderState());
        $this->assertSame([], $event->getMetadata());
    }

    public function testIsPropagationStoppedReturnsFalseByDefault(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $event = new OrderCreateAfterEvent(
            orderId: 123,
            orderNumber: 'ORD123456',
            user: $user,
            totalAmount: 0.0,
            paymentRequired: false,
            orderState: 'completed'
        );

        $this->assertFalse($event->isPropagationStopped());
    }

    public function testGetOrderNumber(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $event = new OrderCreateAfterEvent(
            orderId: 456,
            orderNumber: 'ORD789',
            user: $user,
            totalAmount: 0.0,
            paymentRequired: false,
            orderState: 'completed'
        );

        $this->assertSame('ORD789', $event->getOrderNumber());
    }
}