<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\OrderCheckoutBundle\Param\Order\GetOrderDetailParam;
use Tourze\OrderCheckoutBundle\Procedure\Order\GetOrderDetailProcedure;
use Tourze\PHPUnitJsonRPC\AbstractProcedureTestCase;

/**
 * @internal
 */
#[CoversClass(GetOrderDetailProcedure::class)]
#[RunTestsInSeparateProcesses]
final class GetOrderDetailProcedureTest extends AbstractProcedureTestCase
{
    private GetOrderDetailProcedure $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(GetOrderDetailProcedure::class);
    }

    public function testExecuteThrowsExceptionWhenUserNotLoggedIn(): void
    {
        // Arrange: 设置订单ID但未登录
        $param = new GetOrderDetailParam(orderId: 12345);

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $this->procedure->execute($param);
    }

    public function testExecuteWithValidOrderIdReturnsArrayResult(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $param = new GetOrderDetailParam(orderId: 12345);

        // Act: 执行操作
        $result = $this->procedure->execute($param);

        // Assert: 验证返回结果
        $this->assertInstanceOf(ArrayResult::class, $result);
        $this->assertArrayHasKey('orderId', $result);
        $this->assertEquals(12345, $result['orderId']);
    }
}
