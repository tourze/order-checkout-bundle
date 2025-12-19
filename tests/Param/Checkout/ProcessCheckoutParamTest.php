<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Param\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Param\Checkout\ProcessCheckoutParam;

/**
 * @internal
 */
#[CoversClass(ProcessCheckoutParam::class)]
final class ProcessCheckoutParamTest extends TestCase
{
    public function testCanBeInstantiatedWithDefaults(): void
    {
        $param = new ProcessCheckoutParam();

        self::assertInstanceOf(ProcessCheckoutParam::class, $param);
        self::assertSame([], $param->skuItems);
        self::assertFalse($param->fromCart);
        self::assertSame(0, $param->addressId);
        self::assertNull($param->couponCode);
        self::assertSame(0, $param->pointsToUse);
        self::assertNull($param->orderRemark);
        self::assertNull($param->referralDistributorId);
        self::assertNull($param->referralSource);
        self::assertNull($param->referralTrackCode);
        self::assertSame('CASH_ONLY', $param->paymentMode);
        self::assertSame(0, $param->useIntegralAmount);
    }

    public function testCanBeInstantiatedWithSkuItems(): void
    {
        $skuItems = [
            ['id' => 1, 'skuId' => '100', 'quantity' => 2, 'price' => 99.99],
            ['skuId' => 101, 'quantity' => 1],
        ];

        $param = new ProcessCheckoutParam(skuItems: $skuItems);

        self::assertSame($skuItems, $param->skuItems);
    }

    public function testCanBeInstantiatedWithFromCart(): void
    {
        $param = new ProcessCheckoutParam(fromCart: true);

        self::assertTrue($param->fromCart);
    }

    public function testCanBeInstantiatedWithAddressId(): void
    {
        $param = new ProcessCheckoutParam(addressId: 789);

        self::assertSame(789, $param->addressId);
    }

    public function testCanBeInstantiatedWithCouponCode(): void
    {
        $param = new ProcessCheckoutParam(couponCode: 'WELCOME20');

        self::assertSame('WELCOME20', $param->couponCode);
    }

    public function testCanBeInstantiatedWithPointsToUse(): void
    {
        $param = new ProcessCheckoutParam(pointsToUse: 300);

        self::assertSame(300, $param->pointsToUse);
    }

    public function testCanBeInstantiatedWithOrderRemark(): void
    {
        $param = new ProcessCheckoutParam(orderRemark: '请尽快发货');

        self::assertSame('请尽快发货', $param->orderRemark);
    }

    public function testCanBeInstantiatedWithReferralDistributorId(): void
    {
        $param = new ProcessCheckoutParam(referralDistributorId: 123);

        self::assertSame(123, $param->referralDistributorId);
    }

    public function testCanBeInstantiatedWithReferralSource(): void
    {
        $param = new ProcessCheckoutParam(referralSource: 'scan_qrcode');

        self::assertSame('scan_qrcode', $param->referralSource);
    }

    public function testCanBeInstantiatedWithReferralTrackCode(): void
    {
        $param = new ProcessCheckoutParam(referralTrackCode: 'TRACK123ABC');

        self::assertSame('TRACK123ABC', $param->referralTrackCode);
    }

    public function testCanBeInstantiatedWithPaymentModeIntegralOnly(): void
    {
        $param = new ProcessCheckoutParam(paymentMode: 'INTEGRAL_ONLY');

        self::assertSame('INTEGRAL_ONLY', $param->paymentMode);
    }

    public function testCanBeInstantiatedWithPaymentModeMixed(): void
    {
        $param = new ProcessCheckoutParam(paymentMode: 'MIXED');

        self::assertSame('MIXED', $param->paymentMode);
    }

    public function testCanBeInstantiatedWithUseIntegralAmount(): void
    {
        $param = new ProcessCheckoutParam(
            paymentMode: 'MIXED',
            useIntegralAmount: 800
        );

        self::assertSame(800, $param->useIntegralAmount);
    }

    public function testCanBeInstantiatedWithAllParameters(): void
    {
        $skuItems = [
            ['id' => 1, 'skuId' => '100', 'quantity' => 2],
        ];

        $param = new ProcessCheckoutParam(
            skuItems: $skuItems,
            fromCart: true,
            addressId: 999,
            couponCode: 'VIP50',
            pointsToUse: 500,
            orderRemark: '请包装好',
            referralDistributorId: 456,
            referralSource: 'wechat_share',
            referralTrackCode: 'WX_TRACK_789',
            paymentMode: 'MIXED',
            useIntegralAmount: 1200
        );

        self::assertSame($skuItems, $param->skuItems);
        self::assertTrue($param->fromCart);
        self::assertSame(999, $param->addressId);
        self::assertSame('VIP50', $param->couponCode);
        self::assertSame(500, $param->pointsToUse);
        self::assertSame('请包装好', $param->orderRemark);
        self::assertSame(456, $param->referralDistributorId);
        self::assertSame('wechat_share', $param->referralSource);
        self::assertSame('WX_TRACK_789', $param->referralTrackCode);
        self::assertSame('MIXED', $param->paymentMode);
        self::assertSame(1200, $param->useIntegralAmount);
    }

    public function testSkuItemsWithStringSkuId(): void
    {
        $skuItems = [
            ['skuId' => 'SKU_ABC123', 'quantity' => 1],
        ];

        $param = new ProcessCheckoutParam(skuItems: $skuItems);

        self::assertSame('SKU_ABC123', $param->skuItems[0]['skuId']);
    }

    public function testSkuItemsWithIntegerSkuId(): void
    {
        $skuItems = [
            ['skuId' => 999, 'quantity' => 1],
        ];

        $param = new ProcessCheckoutParam(skuItems: $skuItems);

        self::assertSame(999, $param->skuItems[0]['skuId']);
    }
}
