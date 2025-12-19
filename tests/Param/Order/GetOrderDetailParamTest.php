<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Param\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Param\Order\GetOrderDetailParam;

/**
 * @internal
 */
#[CoversClass(GetOrderDetailParam::class)]
final class GetOrderDetailParamTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $param = new GetOrderDetailParam(orderId: 12345);

        self::assertInstanceOf(GetOrderDetailParam::class, $param);
        self::assertSame(12345, $param->orderId);
    }

    public function testOrderIdProperty(): void
    {
        $param = new GetOrderDetailParam(orderId: 99999);

        self::assertSame(99999, $param->orderId);
    }

    public function testCanBeInstantiatedWithDifferentOrderIds(): void
    {
        $param1 = new GetOrderDetailParam(orderId: 1);
        $param2 = new GetOrderDetailParam(orderId: 1000000);

        self::assertSame(1, $param1->orderId);
        self::assertSame(1000000, $param2->orderId);
    }
}
