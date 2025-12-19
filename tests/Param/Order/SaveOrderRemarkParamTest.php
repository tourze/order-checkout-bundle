<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Param\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Param\Order\SaveOrderRemarkParam;

/**
 * @internal
 */
#[CoversClass(SaveOrderRemarkParam::class)]
final class SaveOrderRemarkParamTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $param = new SaveOrderRemarkParam(
            orderId: 12345,
            remark: '请尽快发货'
        );

        self::assertInstanceOf(SaveOrderRemarkParam::class, $param);
        self::assertSame(12345, $param->orderId);
        self::assertSame('请尽快发货', $param->remark);
    }

    public function testOrderIdProperty(): void
    {
        $param = new SaveOrderRemarkParam(
            orderId: 99999,
            remark: 'test'
        );

        self::assertSame(99999, $param->orderId);
    }

    public function testRemarkProperty(): void
    {
        $param = new SaveOrderRemarkParam(
            orderId: 1,
            remark: '包装好一点，谢谢'
        );

        self::assertSame('包装好一点，谢谢', $param->remark);
    }

    public function testRemarkWithEmoji(): void
    {
        $param = new SaveOrderRemarkParam(
            orderId: 1,
            remark: '快点发货哦 😊👍'
        );

        self::assertSame('快点发货哦 😊👍', $param->remark);
    }

    public function testRemarkWithMaxLength(): void
    {
        $longRemark = str_repeat('备注', 100); // 200个字符

        $param = new SaveOrderRemarkParam(
            orderId: 1,
            remark: $longRemark
        );

        self::assertSame($longRemark, $param->remark);
        self::assertSame(200, mb_strlen($param->remark));
    }

    public function testCanBeInstantiatedWithDifferentValues(): void
    {
        $param1 = new SaveOrderRemarkParam(
            orderId: 100,
            remark: '备注1'
        );
        $param2 = new SaveOrderRemarkParam(
            orderId: 200,
            remark: '备注2'
        );

        self::assertSame(100, $param1->orderId);
        self::assertSame('备注1', $param1->remark);
        self::assertSame(200, $param2->orderId);
        self::assertSame('备注2', $param2->remark);
    }
}
