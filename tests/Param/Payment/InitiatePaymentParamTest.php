<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Param\Payment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Param\Payment\InitiatePaymentParam;

/**
 * @internal
 */
#[CoversClass(InitiatePaymentParam::class)]
final class InitiatePaymentParamTest extends TestCase
{
    public function testCanBeInstantiatedWithDefaults(): void
    {
        $param = new InitiatePaymentParam(orderId: 12345);

        self::assertInstanceOf(InitiatePaymentParam::class, $param);
        self::assertSame(12345, $param->orderId);
        self::assertSame('', $param->paymentMethod);
    }

    public function testCanBeInstantiatedWithPaymentMethod(): void
    {
        $param = new InitiatePaymentParam(
            orderId: 12345,
            paymentMethod: 'wechat_pay'
        );

        self::assertSame(12345, $param->orderId);
        self::assertSame('wechat_pay', $param->paymentMethod);
    }

    public function testOrderIdProperty(): void
    {
        $param = new InitiatePaymentParam(orderId: 99999);

        self::assertSame(99999, $param->orderId);
    }

    public function testPaymentMethodProperty(): void
    {
        $param = new InitiatePaymentParam(
            orderId: 1,
            paymentMethod: 'alipay'
        );

        self::assertSame('alipay', $param->paymentMethod);
    }

    public function testCanBeInstantiatedWithDifferentPaymentMethods(): void
    {
        $param1 = new InitiatePaymentParam(
            orderId: 1,
            paymentMethod: 'wechat_pay'
        );
        $param2 = new InitiatePaymentParam(
            orderId: 2,
            paymentMethod: 'alipay'
        );
        $param3 = new InitiatePaymentParam(
            orderId: 3,
            paymentMethod: 'unionpay'
        );

        self::assertSame('wechat_pay', $param1->paymentMethod);
        self::assertSame('alipay', $param2->paymentMethod);
        self::assertSame('unionpay', $param3->paymentMethod);
    }

    public function testEmptyPaymentMethodByDefault(): void
    {
        $param = new InitiatePaymentParam(orderId: 1);

        self::assertSame('', $param->paymentMethod);
    }
}
