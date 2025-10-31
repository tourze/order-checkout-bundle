<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Exception\InvalidCartItemException;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidCartItemException::class)]
final class InvalidCartItemExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new InvalidCartItemException();

        $this->assertInstanceOf(PriceCalculationException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testDefaultConstructor(): void
    {
        $exception = new InvalidCartItemException();

        $this->assertEquals('无效的购物车项对象', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertIsString($exception->getFile());
        $this->assertIsInt($exception->getLine());
        $this->assertGreaterThan(0, $exception->getLine());
    }

    public function testConstructorWithCustomReason(): void
    {
        $reason = '购物车项缺少必要的SKU信息';
        $exception = new InvalidCartItemException($reason);

        $this->assertEquals($reason, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithReasonAndCode(): void
    {
        $reason = '购物车项格式不正确';
        $code = 400;
        $exception = new InvalidCartItemException($reason, $code);

        $this->assertEquals($reason, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $reason = '购物车项验证失败';
        $code = 422;
        $previous = new \InvalidArgumentException('SKU不存在');
        $exception = new InvalidCartItemException($reason, $code, $previous);

        $this->assertEquals($reason, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithEmptyReason(): void
    {
        $exception = new InvalidCartItemException('');

        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithZeroCode(): void
    {
        $reason = '购物车项数据异常';
        $exception = new InvalidCartItemException($reason, 0);

        $this->assertEquals($reason, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithNegativeCode(): void
    {
        $reason = '购物车项内部错误';
        $code = -500;
        $exception = new InvalidCartItemException($reason, $code);

        $this->assertEquals($reason, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \RuntimeException('数据库连接失败');
        $serviceException = new \InvalidArgumentException('SKU查询失败', 0, $rootCause);
        $cartException = new InvalidCartItemException('购物车项无效', 500, $serviceException);

        $this->assertEquals('购物车项无效', $cartException->getMessage());
        $this->assertEquals(500, $cartException->getCode());
        $this->assertSame($serviceException, $cartException->getPrevious());
        $this->assertSame($rootCause, $cartException->getPrevious()->getPrevious());
    }

    public function testStackTrace(): void
    {
        $exception = new InvalidCartItemException('测试异常');
        $trace = $exception->getTrace();

        $this->assertIsArray($trace);
        $this->assertArrayHasKey('file', $trace[0]);
        $this->assertArrayHasKey('line', $trace[0]);
        $this->assertArrayHasKey('function', $trace[0]);
    }

    public function testTraceAsString(): void
    {
        $exception = new InvalidCartItemException('测试异常');
        $traceString = $exception->getTraceAsString();

        $this->assertIsString($traceString);
        $this->assertStringContainsString(__CLASS__, $traceString);
        $this->assertStringContainsString(__FUNCTION__, $traceString);
    }

    public function testToString(): void
    {
        $message = '购物车项验证异常';
        $exception = new InvalidCartItemException($message);
        $string = (string) $exception;

        $this->assertIsString($string);
        $this->assertStringContainsString(InvalidCartItemException::class, $string);
        $this->assertStringContainsString($message, $string);
        $this->assertStringContainsString(__FILE__, $string);
    }

    public function testMultipleParameterCombinations(): void
    {
        // Test with null previous
        $exception1 = new InvalidCartItemException('测试消息', 100, null);
        $this->assertEquals('测试消息', $exception1->getMessage());
        $this->assertEquals(100, $exception1->getCode());
        $this->assertNull($exception1->getPrevious());

        // Test with large code
        $exception2 = new InvalidCartItemException('大代码测试', 999999);
        $this->assertEquals('大代码测试', $exception2->getMessage());
        $this->assertEquals(999999, $exception2->getCode());
    }

    public function testExceptionWithSpecialCharactersInMessage(): void
    {
        $message = '购物车项失败: "特殊字符" & 符号 <>&';
        $exception = new InvalidCartItemException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithUnicodeMessage(): void
    {
        $message = '购物车项验证失败：商品信息无效 🛒❌';
        $exception = new InvalidCartItemException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testCommonUseCases(): void
    {
        // Case 1: Missing SKU information
        $exception1 = new InvalidCartItemException('购物车项缺少SKU信息');
        $this->assertEquals('购物车项缺少SKU信息', $exception1->getMessage());

        // Case 2: Invalid quantity
        $exception2 = new InvalidCartItemException('购物车项数量无效', 400);
        $this->assertEquals('购物车项数量无效', $exception2->getMessage());
        $this->assertEquals(400, $exception2->getCode());

        // Case 3: Object format error
        $exception3 = new InvalidCartItemException('购物车项对象格式错误');
        $this->assertEquals('购物车项对象格式错误', $exception3->getMessage());
    }

    public function testDifferentReasonFormats(): void
    {
        // Test with detailed technical reason
        $exception1 = new InvalidCartItemException('CartItem对象缺少必要的getSku()方法');
        $this->assertEquals('CartItem对象缺少必要的getSku()方法', $exception1->getMessage());

        // Test with user-friendly reason
        $exception2 = new InvalidCartItemException('购物车中的商品信息不完整');
        $this->assertEquals('购物车中的商品信息不完整', $exception2->getMessage());

        // Test with validation reason
        $exception3 = new InvalidCartItemException('购物车项验证规则不通过');
        $this->assertEquals('购物车项验证规则不通过', $exception3->getMessage());
    }
}
