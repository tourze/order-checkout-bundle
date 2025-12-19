<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;
use Tourze\OrderCheckoutBundle\Event\ExternalCouponRequestedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

#[CoversClass(ExternalCouponRequestedEvent::class)]
class ExternalCouponRequestedEventTest extends AbstractEventTestCase
{
    public function testConstructorInitializesCodeAndUser(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('test_user');

        $event = new ExternalCouponRequestedEvent('TESTCODE', $user);

        $this->assertSame('TESTCODE', $event->getCode());
        $this->assertSame($user, $event->getUser());
    }

    public function testGetUserIdentifier(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('test_user');

        $event = new ExternalCouponRequestedEvent('TESTCODE', $user);

        $this->assertSame('test_user', $event->getUser()->getUserIdentifier());
    }

    public function testSetAndGetCoupon(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $event = new ExternalCouponRequestedEvent('TESTCODE', $user);

        $coupon = $this->createMock(CouponVO::class);

        $event->setCouponVO($coupon);

        $this->assertSame($coupon, $event->getCouponVO());
    }

    public function testIsPropagationStoppedReturnsFalseByDefault(): void
    {
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $event = new ExternalCouponRequestedEvent('TESTCODE', $user);

        $this->assertFalse($event->isPropagationStopped());
    }
}