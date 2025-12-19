<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Event\OrderCreateAfterEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

#[CoversClass(OrderCreateAfterEvent::class)]
final class OrderCreateAfterEventTest extends AbstractEventTestCase
{
    public function testGetOrderId(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $event = new OrderCreateAfterEvent(
            orderId: 12345,
            orderNumber: 'ORD-2024-001',
            user: $user,
            totalAmount: 99.99,
            paymentRequired: true,
            orderState: 'pending'
        );

        $this->assertSame(12345, $event->getOrderId());
    }

    public function testGetOrderNumber(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $event = new OrderCreateAfterEvent(
            orderId: 12345,
            orderNumber: 'ORD-2024-001',
            user: $user,
            totalAmount: 99.99,
            paymentRequired: true,
            orderState: 'pending'
        );

        $this->assertSame('ORD-2024-001', $event->getOrderNumber());
    }

    public function testGetUser(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $event = new OrderCreateAfterEvent(
            orderId: 12345,
            orderNumber: 'ORD-2024-001',
            user: $user,
            totalAmount: 99.99,
            paymentRequired: true,
            orderState: 'pending'
        );

        $this->assertSame($user, $event->getUser());
    }

    public function testGetTotalAmount(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $event = new OrderCreateAfterEvent(
            orderId: 12345,
            orderNumber: 'ORD-2024-001',
            user: $user,
            totalAmount: 99.99,
            paymentRequired: true,
            orderState: 'pending'
        );

        $this->assertSame(99.99, $event->getTotalAmount());
    }

    public function testIsPaymentRequired(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $event = new OrderCreateAfterEvent(
            orderId: 12345,
            orderNumber: 'ORD-2024-001',
            user: $user,
            totalAmount: 99.99,
            paymentRequired: true,
            orderState: 'pending'
        );

        $this->assertTrue($event->isPaymentRequired());
    }

    public function testGetOrderState(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);

        $event = new OrderCreateAfterEvent(
            orderId: 12345,
            orderNumber: 'ORD-2024-001',
            user: $user,
            totalAmount: 99.99,
            paymentRequired: true,
            orderState: 'pending'
        );

        $this->assertSame('pending', $event->getOrderState());
    }

}