<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Param\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Param\Checkout\CalculatePriceParam;

/**
 * @internal
 */
#[CoversClass(CalculatePriceParam::class)]
final class CalculatePriceParamTest extends TestCase
{
    public function testCanBeInstantiatedWithDefaults(): void
    {
        $param = new CalculatePriceParam();

        self::assertInstanceOf(CalculatePriceParam::class, $param);
        self::assertSame([], $param->cartItems);
        self::assertNull($param->addressId);
        self::assertNull($param->couponCode);
        self::assertSame(0, $param->pointsToUse);
        self::assertFalse($param->useCoupon);
        self::assertFalse($param->getAvailableCoupons);
        self::assertSame('CASH_ONLY', $param->paymentMode);
        self::assertSame(0, $param->useIntegralAmount);
    }

    public function testCanBeInstantiatedWithCartItems(): void
    {
        $cartItems = [
            ['id' => 1, 'skuId' => 100, 'quantity' => 2, 'price' => 99.99],
            ['id' => 2, 'skuId' => 101, 'quantity' => 1],
        ];

        $param = new CalculatePriceParam(cartItems: $cartItems);

        self::assertSame($cartItems, $param->cartItems);
    }

    public function testCanBeInstantiatedWithAddressId(): void
    {
        $param = new CalculatePriceParam(addressId: 123);

        self::assertSame(123, $param->addressId);
    }

    public function testCanBeInstantiatedWithCouponCode(): void
    {
        $param = new CalculatePriceParam(couponCode: 'SUMMER2024');

        self::assertSame('SUMMER2024', $param->couponCode);
    }

    public function testCanBeInstantiatedWithPointsToUse(): void
    {
        $param = new CalculatePriceParam(pointsToUse: 500);

        self::assertSame(500, $param->pointsToUse);
    }

    public function testCanBeInstantiatedWithUseCoupon(): void
    {
        $param = new CalculatePriceParam(useCoupon: true);

        self::assertTrue($param->useCoupon);
    }

    public function testCanBeInstantiatedWithGetAvailableCoupons(): void
    {
        $param = new CalculatePriceParam(getAvailableCoupons: true);

        self::assertTrue($param->getAvailableCoupons);
    }

    public function testCanBeInstantiatedWithPaymentModeIntegralOnly(): void
    {
        $param = new CalculatePriceParam(paymentMode: 'INTEGRAL_ONLY');

        self::assertSame('INTEGRAL_ONLY', $param->paymentMode);
    }

    public function testCanBeInstantiatedWithPaymentModeMixed(): void
    {
        $param = new CalculatePriceParam(paymentMode: 'MIXED');

        self::assertSame('MIXED', $param->paymentMode);
    }

    public function testCanBeInstantiatedWithUseIntegralAmount(): void
    {
        $param = new CalculatePriceParam(
            paymentMode: 'MIXED',
            useIntegralAmount: 1000
        );

        self::assertSame(1000, $param->useIntegralAmount);
    }

    public function testCanBeInstantiatedWithAllParameters(): void
    {
        $cartItems = [
            ['id' => 1, 'skuId' => 100, 'quantity' => 2, 'price' => 99.99],
        ];

        $param = new CalculatePriceParam(
            cartItems: $cartItems,
            addressId: 456,
            couponCode: 'DISCOUNT10',
            pointsToUse: 200,
            useCoupon: true,
            getAvailableCoupons: true,
            paymentMode: 'MIXED',
            useIntegralAmount: 500
        );

        self::assertSame($cartItems, $param->cartItems);
        self::assertSame(456, $param->addressId);
        self::assertSame('DISCOUNT10', $param->couponCode);
        self::assertSame(200, $param->pointsToUse);
        self::assertTrue($param->useCoupon);
        self::assertTrue($param->getAvailableCoupons);
        self::assertSame('MIXED', $param->paymentMode);
        self::assertSame(500, $param->useIntegralAmount);
    }
}
