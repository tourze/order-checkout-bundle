<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\OrderCheckoutBundle\Param\Order\GetOrderRemarkHistoryParam;
use Tourze\OrderCheckoutBundle\Procedure\Order\GetOrderRemarkHistoryProcedure;
use Tourze\PHPUnitJsonRPC\AbstractProcedureTestCase;

/**
 * @internal
 */
#[CoversClass(GetOrderRemarkHistoryProcedure::class)]
#[RunTestsInSeparateProcesses]
final class GetOrderRemarkHistoryProcedureTest extends AbstractProcedureTestCase
{
    private GetOrderRemarkHistoryProcedure $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(GetOrderRemarkHistoryProcedure::class);
    }

    public function testExecuteThrowsExceptionWhenUserNotLoggedIn(): void
    {
        // Arrange: 设置订单ID但未登录
        $param = new GetOrderRemarkHistoryParam(orderId: 12345);

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $this->procedure->execute($param);
    }

    public function testExecuteWithValidOrderIdShouldReturnHistoryList(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $param = new GetOrderRemarkHistoryParam(orderId: 12345);

        // Act: 执行获取订单备注历史
        $result = $this->procedure->execute($param);

        // Assert: 验证结果结构
        $this->assertInstanceOf(ArrayResult::class, $result);
        $this->assertArrayHasKey('__message', $result);
        $this->assertArrayHasKey('orderId', $result);
        $this->assertArrayHasKey('history', $result);
        $this->assertArrayHasKey('total', $result);

        // 验证基本信息
        $this->assertEquals('获取订单备注历史成功', $result['__message']);
        $this->assertEquals(12345, $result['orderId']);
        $this->assertIsArray($result['history']);
        $this->assertIsInt($result['total']);
    }

    public function testExecuteWithDifferentOrderIdsShouldWork(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        // Test with various order IDs
        $testOrderIds = [1, 100, 12345, 2147483647];

        foreach ($testOrderIds as $orderId) {
            $param = new GetOrderRemarkHistoryParam(orderId: $orderId);

            // Act: 执行获取订单备注历史
            $result = $this->procedure->execute($param);

            // Assert: 验证返回结果
            $this->assertInstanceOf(ArrayResult::class, $result);
            $this->assertEquals($orderId, $result['orderId']);
        }
    }
}
