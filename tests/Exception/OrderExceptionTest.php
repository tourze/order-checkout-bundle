<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Exception\OrderException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(OrderException::class)]
final class OrderExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeInstantiated(): void
    {
        // Act: 创建异常对象
        $exception = new OrderException();

        // Assert: 验证异常对象
        $this->assertInstanceOf(OrderException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionWithMessage(): void
    {
        // Arrange: 准备异常消息
        $message = '订单处理失败：库存不足';

        // Act: 创建带消息的异常
        $exception = new OrderException($message);

        // Assert: 验证异常消息
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionWithMessageAndCode(): void
    {
        // Arrange: 准备异常消息和代码
        $message = '订单状态异常：无法取消已付款订单';
        $code = 4001;

        // Act: 创建带消息和代码的异常
        $exception = new OrderException($message, $code);

        // Assert: 验证异常消息和代码
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testExceptionWithPreviousException(): void
    {
        // Arrange: 准备前置异常
        $previousException = new \InvalidArgumentException('无效的订单ID');
        $message = '订单查询失败';
        $code = 4002;

        // Act: 创建带前置异常的异常
        $exception = new OrderException($message, $code, $previousException);

        // Assert: 验证异常链
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertEquals('无效的订单ID', $exception->getPrevious()->getMessage());
    }

    public function testExceptionInheritanceChain(): void
    {
        // Act: 创建异常对象
        $exception = new OrderException('测试订单异常');

        // Assert: 验证继承关系
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testExceptionCanBeThrown(): void
    {
        // Arrange: 准备异常消息
        $message = '订单结算失败：账户余额不足';

        // Act & Assert: 验证异常可以被抛出和捕获
        $this->expectException(OrderException::class);
        $this->expectExceptionMessage($message);

        throw new OrderException($message);
    }

    public function testExceptionCanBeCaught(): void
    {
        // Arrange: 准备异常消息
        $message = '订单创建失败：用户信息验证不通过';
        $caught = false;
        $caughtMessage = '';

        try {
            // Act: 抛出异常
            throw new OrderException($message);
        } catch (OrderException $e) {
            // 捕获异常
            $caught = true;
            $caughtMessage = $e->getMessage();
        }

        // Assert: 验证异常被正确捕获
        $this->assertTrue($caught);
        $this->assertEquals($message, $caughtMessage);
    }

    public function testExceptionWithOrderSpecificScenarios(): void
    {
        // Arrange: 测试订单特定场景
        $scenarios = [
            ['订单状态异常', 1001],
            ['支付失败', 2001],
            ['订单超时', 3001],
            ['商品下架', 4001],
            ['配送地址错误', 5001],
        ];

        foreach ($scenarios as [$message, $code]) {
            // Act: 创建异常
            $exception = new OrderException($message, $code);

            // Assert: 验证各种订单异常场景
            $this->assertEquals($message, $exception->getMessage());
            $this->assertEquals($code, $exception->getCode());
            $this->assertInstanceOf(OrderException::class, $exception);
        }
    }

    public function testExceptionWithComplexErrorChain(): void
    {
        // Arrange: 创建复杂的错误链
        $rootCause = new \PDOException('数据库连接失败');
        $intermediateCause = new \RuntimeException('数据保存失败', 0, $rootCause);
        $orderException = new OrderException('订单处理失败', 4999, $intermediateCause);

        // Act & Assert: 验证错误链
        $this->assertEquals('订单处理失败', $orderException->getMessage());
        $this->assertEquals(4999, $orderException->getCode());

        $previous = $orderException->getPrevious();
        $this->assertInstanceOf(\RuntimeException::class, $previous);
        $this->assertEquals('数据保存失败', $previous->getMessage());

        $rootPrevious = $previous->getPrevious();
        $this->assertInstanceOf(\PDOException::class, $rootPrevious);
        $this->assertEquals('数据库连接失败', $rootPrevious->getMessage());
    }

    public function testExceptionWithEmptyMessage(): void
    {
        // Act: 创建空消息的异常
        $exception = new OrderException('');

        // Assert: 验证空消息处理
        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
    }

    public function testExceptionWithLongMessage(): void
    {
        // Arrange: 准备长消息
        $longMessage = str_repeat('订单处理过程中出现了一个复杂的错误情况，', 50);

        // Act: 创建带长消息的异常
        $exception = new OrderException($longMessage);

        // Assert: 验证长消息处理
        $this->assertEquals($longMessage, $exception->getMessage());
        $this->assertGreaterThan(1000, strlen($exception->getMessage()));
    }

    public function testExceptionWithNegativeCode(): void
    {
        // Arrange: 准备负数代码
        $message = '订单系统内部错误';
        $code = -1;

        // Act: 创建带负数代码的异常
        $exception = new OrderException($message, $code);

        // Assert: 验证负数代码处理
        $this->assertEquals($code, $exception->getCode());
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionStackTrace(): void
    {
        // Act: 创建异常并检查堆栈跟踪
        $exception = new OrderException('测试订单异常堆栈跟踪');
        $trace = $exception->getTrace();
        $traceAsString = $exception->getTraceAsString();
        $file = $exception->getFile();
        $line = $exception->getLine();

        // Assert: 验证堆栈跟踪信息
        $this->assertIsArray($trace);
        $this->assertIsString($traceAsString);
        $this->assertNotEmpty($traceAsString);
        $this->assertIsString($file);
        $this->assertIsInt($line);
        $this->assertGreaterThan(0, $line);
    }
}
