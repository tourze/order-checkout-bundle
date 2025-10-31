<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Exception\ContentFilterException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ContentFilterException::class)]
final class ContentFilterExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeInstantiated(): void
    {
        // Act: 创建异常对象
        $exception = new ContentFilterException();

        // Assert: 验证异常对象
        $this->assertInstanceOf(ContentFilterException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionWithMessage(): void
    {
        // Arrange: 准备异常消息
        $message = '内容包含敏感词汇，已被过滤';

        // Act: 创建带消息的异常
        $exception = new ContentFilterException($message);

        // Assert: 验证异常消息
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionWithMessageAndCode(): void
    {
        // Arrange: 准备异常消息和代码
        $message = '用户评论包含不当内容';
        $code = 1001;

        // Act: 创建带消息和代码的异常
        $exception = new ContentFilterException($message, $code);

        // Assert: 验证异常消息和代码
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testExceptionWithPreviousException(): void
    {
        // Arrange: 准备前置异常
        $previousException = new \RuntimeException('原始错误');
        $message = '内容过滤失败';
        $code = 2001;

        // Act: 创建带前置异常的异常
        $exception = new ContentFilterException($message, $code, $previousException);

        // Assert: 验证异常链
        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previousException, $exception->getPrevious());
        $this->assertEquals('原始错误', $exception->getPrevious()->getMessage());
    }

    public function testExceptionInheritanceChain(): void
    {
        // Act: 创建异常对象
        $exception = new ContentFilterException('测试异常');

        // Assert: 验证继承关系
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testExceptionCanBeThrown(): void
    {
        // Arrange: 准备异常消息
        $message = '订单备注包含禁用词汇';

        // Act & Assert: 验证异常可以被抛出和捕获
        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage($message);

        throw new ContentFilterException($message);
    }

    public function testExceptionCanBeCaught(): void
    {
        // Arrange: 准备异常消息
        $message = '内容包含敏感信息';
        $caught = false;
        $caughtMessage = '';

        try {
            // Act: 抛出异常
            throw new ContentFilterException($message);
        } catch (ContentFilterException $e) {
            // 捕获异常
            $caught = true;
            $caughtMessage = $e->getMessage();
        }

        // Assert: 验证异常被正确捕获
        $this->assertTrue($caught);
        $this->assertEquals($message, $caughtMessage);
    }

    public function testExceptionWithEmptyMessage(): void
    {
        // Act: 创建空消息的异常
        $exception = new ContentFilterException('');

        // Assert: 验证空消息处理
        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
    }

    public function testExceptionWithSpecialCharactersInMessage(): void
    {
        // Arrange: 准备包含特殊字符的消息
        $message = '内容包含非法字符：@#$%^&*()[]{}|\:;"<>?,./';

        // Act: 创建异常
        $exception = new ContentFilterException($message);

        // Assert: 验证特殊字符处理
        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionStackTrace(): void
    {
        // Act: 创建异常并检查堆栈跟踪
        $exception = new ContentFilterException('测试堆栈跟踪');
        $trace = $exception->getTrace();
        $traceAsString = $exception->getTraceAsString();

        // Assert: 验证堆栈跟踪信息
        $this->assertIsArray($trace);
        $this->assertIsString($traceAsString);
        $this->assertNotEmpty($traceAsString);
    }
}
