<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Exception\InvalidSkuTypeException;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidSkuTypeException::class)]
final class InvalidSkuTypeExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new InvalidSkuTypeException('test_type');

        $this->assertInstanceOf(PriceCalculationException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testConstructorWithActualType(): void
    {
        $actualType = 'invalid_sku_type';
        $exception = new InvalidSkuTypeException($actualType);

        $expectedMessage = sprintf('无效的 SKU 类型: %s', $actualType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertIsString($exception->getFile());
        $this->assertIsInt($exception->getLine());
        $this->assertGreaterThan(0, $exception->getLine());
    }

    public function testConstructorWithActualTypeAndCode(): void
    {
        $actualType = 'unsupported_type';
        $code = 400;
        $exception = new InvalidSkuTypeException($actualType, $code);

        $expectedMessage = sprintf('无效的 SKU 类型: %s', $actualType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $actualType = 'corrupted_type';
        $code = 422;
        $previous = new \InvalidArgumentException('类型验证失败');
        $exception = new InvalidSkuTypeException($actualType, $code, $previous);

        $expectedMessage = sprintf('无效的 SKU 类型: %s', $actualType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithEmptyType(): void
    {
        $exception = new InvalidSkuTypeException('');

        $this->assertEquals('无效的 SKU 类型: ', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithZeroCode(): void
    {
        $actualType = 'zero_code_type';
        $exception = new InvalidSkuTypeException($actualType, 0);

        $expectedMessage = sprintf('无效的 SKU 类型: %s', $actualType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithNegativeCode(): void
    {
        $actualType = 'negative_code_type';
        $code = -500;
        $exception = new InvalidSkuTypeException($actualType, $code);

        $expectedMessage = sprintf('无效的 SKU 类型: %s', $actualType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \RuntimeException('数据库类型校验失败');
        $validationException = new \InvalidArgumentException('SKU类型格式错误', 0, $rootCause);
        $skuException = new InvalidSkuTypeException('unknown_format', 500, $validationException);

        $this->assertEquals('无效的 SKU 类型: unknown_format', $skuException->getMessage());
        $this->assertEquals(500, $skuException->getCode());
        $this->assertSame($validationException, $skuException->getPrevious());
        $this->assertSame($rootCause, $skuException->getPrevious()->getPrevious());
    }

    public function testStackTrace(): void
    {
        $exception = new InvalidSkuTypeException('trace_test_type');
        $trace = $exception->getTrace();

        $this->assertIsArray($trace);
        $this->assertArrayHasKey('file', $trace[0]);
        $this->assertArrayHasKey('line', $trace[0]);
        $this->assertArrayHasKey('function', $trace[0]);
    }

    public function testTraceAsString(): void
    {
        $exception = new InvalidSkuTypeException('string_test_type');
        $traceString = $exception->getTraceAsString();

        $this->assertIsString($traceString);
        $this->assertStringContainsString(__CLASS__, $traceString);
        $this->assertStringContainsString(__FUNCTION__, $traceString);
    }

    public function testToString(): void
    {
        $actualType = 'to_string_test_type';
        $exception = new InvalidSkuTypeException($actualType);
        $string = (string) $exception;

        $this->assertIsString($string);
        $this->assertStringContainsString(InvalidSkuTypeException::class, $string);
        $this->assertStringContainsString('无效的 SKU 类型: ' . $actualType, $string);
        $this->assertStringContainsString(__FILE__, $string);
    }

    public function testMultipleParameterCombinations(): void
    {
        // Test with null previous
        $exception1 = new InvalidSkuTypeException('test_type', 100, null);
        $this->assertEquals('无效的 SKU 类型: test_type', $exception1->getMessage());
        $this->assertEquals(100, $exception1->getCode());
        $this->assertNull($exception1->getPrevious());

        // Test with large code
        $exception2 = new InvalidSkuTypeException('large_code_type', 999999);
        $this->assertEquals('无效的 SKU 类型: large_code_type', $exception2->getMessage());
        $this->assertEquals(999999, $exception2->getCode());
    }

    public function testExceptionWithSpecialCharactersInType(): void
    {
        $actualType = 'special_type_"with"_<chars>&symbols';
        $exception = new InvalidSkuTypeException($actualType);

        $expectedMessage = sprintf('无效的 SKU 类型: %s', $actualType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testExceptionWithUnicodeType(): void
    {
        $actualType = '无效类型_🚫_错误';
        $exception = new InvalidSkuTypeException($actualType);

        $expectedMessage = sprintf('无效的 SKU 类型: %s', $actualType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testCommonSkuTypes(): void
    {
        // Case 1: Digital product type
        $exception1 = new InvalidSkuTypeException('digital_invalid');
        $this->assertEquals('无效的 SKU 类型: digital_invalid', $exception1->getMessage());

        // Case 2: Physical product type
        $exception2 = new InvalidSkuTypeException('physical_unknown', 400);
        $this->assertEquals('无效的 SKU 类型: physical_unknown', $exception2->getMessage());
        $this->assertEquals(400, $exception2->getCode());

        // Case 3: Service type
        $exception3 = new InvalidSkuTypeException('service_deprecated');
        $this->assertEquals('无效的 SKU 类型: service_deprecated', $exception3->getMessage());
    }

    public function testNumericTypes(): void
    {
        // Test with numeric type as string
        $exception1 = new InvalidSkuTypeException('123');
        $this->assertEquals('无效的 SKU 类型: 123', $exception1->getMessage());

        // Test with decimal type
        $exception2 = new InvalidSkuTypeException('45.67');
        $this->assertEquals('无效的 SKU 类型: 45.67', $exception2->getMessage());

        // Test with negative type
        $exception3 = new InvalidSkuTypeException('-999');
        $this->assertEquals('无效的 SKU 类型: -999', $exception3->getMessage());
    }

    public function testLongTypeNames(): void
    {
        $longType = str_repeat('very_long_type_name_', 10);
        $exception = new InvalidSkuTypeException($longType);

        $expectedMessage = sprintf('无效的 SKU 类型: %s', $longType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testCommonErrorCodes(): void
    {
        // HTTP 400 Bad Request
        $exception1 = new InvalidSkuTypeException('bad_type', 400);
        $this->assertEquals(400, $exception1->getCode());

        // HTTP 422 Unprocessable Entity
        $exception2 = new InvalidSkuTypeException('unprocessable_type', 422);
        $this->assertEquals(422, $exception2->getCode());

        // HTTP 500 Internal Server Error
        $exception3 = new InvalidSkuTypeException('server_error_type', 500);
        $this->assertEquals(500, $exception3->getCode());
    }
}
