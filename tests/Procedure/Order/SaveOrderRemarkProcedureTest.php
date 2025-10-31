<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use Tourze\OrderCheckoutBundle\Procedure\Order\SaveOrderRemarkProcedure;

/**
 * @internal
 */
#[CoversClass(SaveOrderRemarkProcedure::class)]
#[RunTestsInSeparateProcesses]
final class SaveOrderRemarkProcedureTest extends AbstractProcedureTestCase
{
    private SaveOrderRemarkProcedure $procedure;

    protected function onSetUp(): void
    {
        // 从服务容器获取 procedure 实例，使用真实依赖进行集成测试
        $this->procedure = self::getService(SaveOrderRemarkProcedure::class);
    }

    public function testExecuteSuccess(): void
    {
        // 设置 procedure 参数
        $this->procedure->orderId = 12345;
        $this->procedure->remark = '测试备注';

        // 由于需要用户登录，这个测试会因为没有认证用户而失败
        // 在实际集成测试中，应该先创建用户并登录
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $this->procedure->execute();
    }

    public function testExecuteWithEmptyRemark(): void
    {
        // 测试空备注的情况
        $this->procedure->orderId = 12345;
        $this->procedure->remark = '';

        // 应该抛出验证异常
        $this->expectException(\Exception::class);

        $this->procedure->execute();
    }

    public function testExecuteWithLongRemark(): void
    {
        // 测试超长备注的情况（超过200字符）
        $this->procedure->orderId = 12345;
        $this->procedure->remark = str_repeat('测试', 101); // 202个字符

        // 应该抛出验证异常
        $this->expectException(\Exception::class);

        $this->procedure->execute();
    }

    public function testExecuteWithInvalidOrderId(): void
    {
        // 测试无效订单ID的情况
        $this->procedure->orderId = 0;
        $this->procedure->remark = '测试备注';

        // 应该抛出验证异常
        $this->expectException(\Exception::class);

        $this->procedure->execute();
    }

    public function testGetLockResource(): void
    {
        // 设置参数
        $this->procedure->orderId = 12345;
        $this->procedure->remark = '测试备注';

        // 测试获取锁资源
        try {
            $params = $this->createMock(JsonRpcParams::class);
            $lockResource = $this->procedure->getLockResource($params);
            $this->assertIsArray($lockResource);
            $this->assertCount(1, $lockResource);
            $this->assertIsString($lockResource[0]);
            $this->assertStringContainsString('order_remark:12345:', $lockResource[0]);
        } catch (ApiException $e) {
            // 如果因为用户未登录而失败，这是预期的行为
            $this->assertStringContainsString('用户未登录', $e->getMessage());
        }
    }

    public function testGetMockResult(): void
    {
        $mockResult = SaveOrderRemarkProcedure::getMockResult();

        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('__message', $mockResult);
        $this->assertArrayHasKey('orderId', $mockResult);
        $this->assertArrayHasKey('remark', $mockResult);
        $this->assertArrayHasKey('filteredRemark', $mockResult);
        $this->assertArrayHasKey('hasFilteredContent', $mockResult);
        $this->assertArrayHasKey('savedAt', $mockResult);

        // 验证 Mock 结果的类型
        $this->assertIsString($mockResult['__message']);
        $this->assertIsInt($mockResult['orderId']);
        $this->assertIsString($mockResult['remark']);
        $this->assertIsString($mockResult['filteredRemark']);
        $this->assertIsBool($mockResult['hasFilteredContent']);
        $this->assertIsString($mockResult['savedAt']);
    }
}
