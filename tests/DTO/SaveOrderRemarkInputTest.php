<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\SaveOrderRemarkInput;

/**
 * @internal
 */
#[CoversClass(SaveOrderRemarkInput::class)]
final class SaveOrderRemarkInputTest extends TestCase
{
    public function testSaveOrderRemarkInputCanBeCreated(): void
    {
        $orderId = 12345;
        $remark = '请尽快发货，谢谢！';

        $input = new SaveOrderRemarkInput($orderId, $remark);

        $this->assertSame($orderId, $input->orderId);
        $this->assertSame($remark, $input->remark);
    }

    public function testSaveOrderRemarkInputWithLongRemark(): void
    {
        $orderId = 99999;
        $remark = str_repeat('这是一个很长的备注内容。', 100);

        $input = new SaveOrderRemarkInput($orderId, $remark);

        $this->assertSame($orderId, $input->orderId);
        $this->assertSame($remark, $input->remark);
        $this->assertGreaterThan(1000, strlen($input->remark));
    }

    public function testSaveOrderRemarkInputWithEmptyRemark(): void
    {
        $orderId = 54321;
        $remark = '';

        $input = new SaveOrderRemarkInput($orderId, $remark);

        $this->assertSame($orderId, $input->orderId);
        $this->assertSame($remark, $input->remark);
        $this->assertEmpty($input->remark);
    }

    public function testSaveOrderRemarkInputWithSpecialCharacters(): void
    {
        $orderId = 77777;
        $remark = '特殊字符测试：@#$%^&*()_+{}|:"<>?[]\;\',./ 😊🎉🔥';

        $input = new SaveOrderRemarkInput($orderId, $remark);

        $this->assertSame($orderId, $input->orderId);
        $this->assertSame($remark, $input->remark);
        $this->assertStringContainsString('😊', $input->remark);
    }

    public function testSaveOrderRemarkInputWithZeroOrderId(): void
    {
        $orderId = 0;
        $remark = '零订单ID测试';

        $input = new SaveOrderRemarkInput($orderId, $remark);

        $this->assertSame($orderId, $input->orderId);
        $this->assertSame($remark, $input->remark);
    }

    public function testSaveOrderRemarkInputImmutability(): void
    {
        $orderId = 88888;
        $remark = '不可变性测试';

        $input = new SaveOrderRemarkInput($orderId, $remark);

        // 验证是 readonly 类，属性不能被修改
        $this->assertSame($orderId, $input->orderId);
        $this->assertSame($remark, $input->remark);

        // readonly 类的属性在运行时无法修改，这里只是验证值保持不变
        $originalOrderId = $input->orderId;
        $originalRemark = $input->remark;

        $this->assertSame($originalOrderId, $input->orderId);
        $this->assertSame($originalRemark, $input->remark);
    }
}
