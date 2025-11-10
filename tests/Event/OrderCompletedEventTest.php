<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\OrderCheckoutBundle\Event\OrderCompletedEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

#[CoversClass(OrderCompletedEvent::class)]
class OrderCompletedEventTest extends AbstractEventTestCase
{
    private UserInterface $user;

    protected function setUp(): void
    {
        $this->user = $this->createMock(UserInterface::class);
        $this->user->method('getUserIdentifier')->willReturn('test_user');
    }

    public function testConstructorInitializesRequiredProperties(): void
    {
        $event = new OrderCompletedEvent(
            orderId: 123,
            orderNumber: 'ORD123456',
            user: $this->user,
            totalAmount: 199.99,
            paymentRequired: true,
            orderState: 'INIT'
        );

        $this->assertSame(123, $event->getOrderId());
        $this->assertSame('ORD123456', $event->getOrderNumber());
        $this->assertSame($this->user, $event->getUser());
        $this->assertSame(199.99, $event->getTotalAmount());
        $this->assertTrue($event->isPaymentRequired());
        $this->assertSame('INIT', $event->getOrderState());
    }

    public function testConstructorWithMetadata(): void
    {
        $metadata = [
            'orderType' => 'normal',
            'appliedCoupons' => ['COUPON1', 'COUPON2'],
            'addressId' => 456,
            'pointsToUse' => 100,
            'orderRemark' => 'Please deliver ASAP',
            'stockWarnings' => ['item1' => 'low stock'],
        ];

        $event = new OrderCompletedEvent(
            orderId: 123,
            orderNumber: 'ORD123456',
            user: $this->user,
            totalAmount: 199.99,
            paymentRequired: true,
            orderState: 'INIT',
            metadata: $metadata
        );

        $this->assertSame($metadata, $event->getMetadata());
    }

    public function testGetMetadataValueReturnsCorrectValue(): void
    {
        $metadata = ['key1' => 'value1', 'key2' => 123];
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', $metadata);

        $this->assertSame('value1', $event->getMetadataValue('key1'));
        $this->assertSame(123, $event->getMetadataValue('key2'));
    }

    public function testGetMetadataValueReturnsDefaultForMissingKey(): void
    {
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', []);

        $this->assertNull($event->getMetadataValue('nonexistent'));
        $this->assertSame('default', $event->getMetadataValue('nonexistent', 'default'));
    }

    public function testIsNormalOrderReturnsTrue(): void
    {
        $metadata = ['orderType' => 'normal'];
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', $metadata);

        $this->assertTrue($event->isNormalOrder());
        $this->assertFalse($event->isRedeemOrder());
    }

    public function testIsRedeemOrderReturnsTrue(): void
    {
        $metadata = ['orderType' => 'redeem'];
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', $metadata);

        $this->assertTrue($event->isRedeemOrder());
        $this->assertFalse($event->isNormalOrder());
    }

    public function testGetCouponCodesReturnsEmptyByDefault(): void
    {
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', []);

        $this->assertSame([], $event->getCouponCodes());
    }

    public function testGetCouponCodesReturnsAppliedCoupons(): void
    {
        $coupons = ['SUMMER2024', 'FIRST10'];
        $metadata = ['appliedCoupons' => $coupons];
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', $metadata);

        $this->assertSame($coupons, $event->getCouponCodes());
    }

    public function testHasCouponsReturnsTrueWhenCouponsApplied(): void
    {
        $metadata = ['appliedCoupons' => ['COUPON1']];
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', $metadata);

        $this->assertTrue($event->hasCoupons());
    }

    public function testHasCouponsReturnsFalseWhenNoCoupons(): void
    {
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', []);

        $this->assertFalse($event->hasCoupons());
    }

    public function testGetAddressIdReturnsNullByDefault(): void
    {
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', []);

        $this->assertNull($event->getAddressId());
    }

    public function testGetAddressIdReturnsValueFromMetadata(): void
    {
        $metadata = ['addressId' => 789];
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', $metadata);

        $this->assertSame(789, $event->getAddressId());
    }

    public function testGetPointsUsedReturnsZeroByDefault(): void
    {
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', []);

        $this->assertSame(0, $event->getPointsUsed());
    }

    public function testGetPointsUsedReturnsValueFromMetadata(): void
    {
        $metadata = ['pointsToUse' => 250];
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', $metadata);

        $this->assertSame(250, $event->getPointsUsed());
    }

    public function testHasPointsUsedReturnsTrueWhenPointsUsed(): void
    {
        $metadata = ['pointsToUse' => 100];
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', $metadata);

        $this->assertTrue($event->hasPointsUsed());
    }

    public function testHasPointsUsedReturnsFalseWhenNoPoints(): void
    {
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', []);

        $this->assertFalse($event->hasPointsUsed());
    }

    public function testGetOrderRemarkReturnsNullByDefault(): void
    {
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', []);

        $this->assertNull($event->getOrderRemark());
    }

    public function testGetOrderRemarkReturnsValueFromMetadata(): void
    {
        $metadata = ['orderRemark' => 'Urgent delivery'];
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', $metadata);

        $this->assertSame('Urgent delivery', $event->getOrderRemark());
    }

    public function testGetStockWarningsReturnsEmptyByDefault(): void
    {
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', []);

        $this->assertSame([], $event->getStockWarnings());
    }

    public function testGetStockWarningsReturnsValueFromMetadata(): void
    {
        $warnings = ['sku123' => 'Out of stock', 'sku456' => 'Low inventory'];
        $metadata = ['stockWarnings' => $warnings];
        $event = new OrderCompletedEvent(123, 'ORD', $this->user, 0.0, false, 'INIT', $metadata);

        $this->assertSame($warnings, $event->getStockWarnings());
    }

    public function testPaymentRequiredScenarios(): void
    {
        $event1 = new OrderCompletedEvent(1, 'ORD1', $this->user, 100.0, true, 'INIT', []);
        $this->assertTrue($event1->isPaymentRequired());

        $event2 = new OrderCompletedEvent(2, 'ORD2', $this->user, 0.0, false, 'PAID', []);
        $this->assertFalse($event2->isPaymentRequired());
    }

    public function testCompleteOrderScenario(): void
    {
        $metadata = [
            'orderType' => 'normal',
            'appliedCoupons' => ['WELCOME10'],
            'addressId' => 999,
            'pointsToUse' => 50,
            'orderRemark' => 'Handle with care',
        ];

        $event = new OrderCompletedEvent(
            orderId: 12345,
            orderNumber: 'ORD20240101001',
            user: $this->user,
            totalAmount: 299.99,
            paymentRequired: true,
            orderState: 'INIT',
            metadata: $metadata
        );

        $this->assertSame(12345, $event->getOrderId());
        $this->assertSame('ORD20240101001', $event->getOrderNumber());
        $this->assertSame(299.99, $event->getTotalAmount());
        $this->assertTrue($event->isPaymentRequired());
        $this->assertSame('INIT', $event->getOrderState());
        $this->assertTrue($event->isNormalOrder());
        $this->assertTrue($event->hasCoupons());
        $this->assertSame(['WELCOME10'], $event->getCouponCodes());
        $this->assertSame(999, $event->getAddressId());
        $this->assertTrue($event->hasPointsUsed());
        $this->assertSame(50, $event->getPointsUsed());
        $this->assertSame('Handle with care', $event->getOrderRemark());
    }
}
