<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Param\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Param\Order\GetOrderRemarkHistoryParam;

/**
 * @internal
 */
#[CoversClass(GetOrderRemarkHistoryParam::class)]
final class GetOrderRemarkHistoryParamTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $param = new GetOrderRemarkHistoryParam(orderId: 12345);

        self::assertInstanceOf(GetOrderRemarkHistoryParam::class, $param);
        self::assertSame(12345, $param->orderId);
    }

    public function testOrderIdProperty(): void
    {
        $param = new GetOrderRemarkHistoryParam(orderId: 88888);

        self::assertSame(88888, $param->orderId);
    }

    public function testCanBeInstantiatedWithDifferentOrderIds(): void
    {
        $param1 = new GetOrderRemarkHistoryParam(orderId: 1);
        $param2 = new GetOrderRemarkHistoryParam(orderId: 2000000);

        self::assertSame(1, $param1->orderId);
        self::assertSame(2000000, $param2->orderId);
    }
}
