<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\CouponCoreBundle\ValueObject\CouponVO;
use Tourze\OrderCheckoutBundle\Event\ExternalCouponRequestedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

#[CoversClass(ExternalCouponRequestedEvent::class)]
class ExternalCouponRequestedEventTest extends AbstractEventTestCase
{
    private UserInterface $user;

    protected function setUp(): void
    {
        $this->user = $this->createMock(UserInterface::class);
        $this->user->method('getUserIdentifier')->willReturn('test_user');
    }

    public function testConstructorInitializesCodeAndUser(): void
    {
        $event = new ExternalCouponRequestedEvent('TESTCODE', $this->user);

        $this->assertSame('TESTCODE', $event->getCode());
        $this->assertSame($this->user, $event->getUser());
    }

    public function testGetCouponVOReturnsNullByDefault(): void
    {
        $event = new ExternalCouponRequestedEvent('CODE123', $this->user);

        $this->assertNull($event->getCouponVO());
    }

    public function testSetCouponVOStoresValue(): void
    {
        $event = new ExternalCouponRequestedEvent('CODE123', $this->user);
        $couponVO = $this->createMock(CouponVO::class);

        $event->setCouponVO($couponVO);

        $this->assertSame($couponVO, $event->getCouponVO());
    }

    public function testIsResolvedReturnsFalseByDefault(): void
    {
        $event = new ExternalCouponRequestedEvent('CODE123', $this->user);

        $this->assertFalse($event->isResolved());
    }

    public function testIsResolvedReturnsTrueAfterSettingCouponVO(): void
    {
        $event = new ExternalCouponRequestedEvent('CODE123', $this->user);
        $couponVO = $this->createMock(CouponVO::class);

        $event->setCouponVO($couponVO);

        $this->assertTrue($event->isResolved());
    }

    public function testSetCouponVOCanSetNull(): void
    {
        $event = new ExternalCouponRequestedEvent('CODE123', $this->user);
        $couponVO = $this->createMock(CouponVO::class);

        $event->setCouponVO($couponVO);
        $this->assertTrue($event->isResolved());

        $event->setCouponVO(null);
        $this->assertFalse($event->isResolved());
        $this->assertNull($event->getCouponVO());
    }

    public function testMultipleEventsAreIndependent(): void
    {
        $user2 = $this->createMock(UserInterface::class);
        $event1 = new ExternalCouponRequestedEvent('CODE1', $this->user);
        $event2 = new ExternalCouponRequestedEvent('CODE2', $user2);

        $couponVO = $this->createMock(CouponVO::class);
        $event1->setCouponVO($couponVO);

        $this->assertTrue($event1->isResolved());
        $this->assertFalse($event2->isResolved());
        $this->assertSame($couponVO, $event1->getCouponVO());
        $this->assertNull($event2->getCouponVO());
    }

    public function testGetUserReturnsCorrectInstance(): void
    {
        $event = new ExternalCouponRequestedEvent('CODE123', $this->user);

        $retrievedUser = $event->getUser();

        $this->assertSame($this->user, $retrievedUser);
        $this->assertSame('test_user', $retrievedUser->getUserIdentifier());
    }

    public function testEventCanBeUsedInEventDispatcherPattern(): void
    {
        $event = new ExternalCouponRequestedEvent('EXTERNAL_CODE', $this->user);

        // 模拟事件监听器设置 CouponVO
        $this->assertFalse($event->isResolved());

        $couponVO = $this->createMock(CouponVO::class);
        $event->setCouponVO($couponVO);

        $this->assertTrue($event->isResolved());
        $this->assertInstanceOf(CouponVO::class, $event->getCouponVO());
    }
}
