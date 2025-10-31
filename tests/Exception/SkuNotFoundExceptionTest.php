<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationException;
use Tourze\OrderCheckoutBundle\Exception\SkuNotFoundException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(SkuNotFoundException::class)]
final class SkuNotFoundExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new SkuNotFoundException('test_sku_id');

        $this->assertInstanceOf(PriceCalculationException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testConstructorWithSkuId(): void
    {
        $skuId = 'SKU123456';
        $exception = new SkuNotFoundException($skuId);

        $expectedMessage = sprintf('SKU 未找到: %s', $skuId);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertIsString($exception->getFile());
        $this->assertIsInt($exception->getLine());
        $this->assertGreaterThan(0, $exception->getLine());
    }

    public function testConstructorWithSkuIdAndCode(): void
    {
        $skuId = 'MISSING_SKU';
        $code = 404;
        $exception = new SkuNotFoundException($skuId, $code);

        $expectedMessage = sprintf('SKU 未找到: %s', $skuId);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $skuId = 'INVALID_SKU';
        $code = 422;
        $previous = new \InvalidArgumentException('SKU 查询失败');
        $exception = new SkuNotFoundException($skuId, $code, $previous);

        $expectedMessage = sprintf('SKU 未找到: %s', $skuId);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithEmptySkuId(): void
    {
        $exception = new SkuNotFoundException('');

        $this->assertEquals('SKU 未找到: ', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithZeroCode(): void
    {
        $skuId = 'ZERO_CODE_SKU';
        $exception = new SkuNotFoundException($skuId, 0);

        $expectedMessage = sprintf('SKU 未找到: %s', $skuId);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithNegativeCode(): void
    {
        $skuId = 'NEGATIVE_CODE_SKU';
        $code = -500;
        $exception = new SkuNotFoundException($skuId, $code);

        $expectedMessage = sprintf('SKU 未找到: %s', $skuId);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \RuntimeException('数据库连接失败');
        $queryException = new \InvalidArgumentException('SKU 查询异常', 0, $rootCause);
        $skuException = new SkuNotFoundException('DB_ERROR_SKU', 500, $queryException);

        $this->assertEquals('SKU 未找到: DB_ERROR_SKU', $skuException->getMessage());
        $this->assertEquals(500, $skuException->getCode());
        $this->assertSame($queryException, $skuException->getPrevious());
        $this->assertSame($rootCause, $skuException->getPrevious()->getPrevious());
    }

    public function testStackTrace(): void
    {
        $exception = new SkuNotFoundException('TRACE_TEST_SKU');
        $trace = $exception->getTrace();

        $this->assertIsArray($trace);
        $this->assertArrayHasKey('file', $trace[0]);
        $this->assertArrayHasKey('line', $trace[0]);
        $this->assertArrayHasKey('function', $trace[0]);
    }

    public function testTraceAsString(): void
    {
        $exception = new SkuNotFoundException('STRING_TEST_SKU');
        $traceString = $exception->getTraceAsString();

        $this->assertIsString($traceString);
        $this->assertStringContainsString(__CLASS__, $traceString);
        $this->assertStringContainsString(__FUNCTION__, $traceString);
    }

    public function testToString(): void
    {
        $skuId = 'TO_STRING_TEST_SKU';
        $exception = new SkuNotFoundException($skuId);
        $string = (string) $exception;

        $this->assertIsString($string);
        $this->assertStringContainsString(SkuNotFoundException::class, $string);
        $this->assertStringContainsString('SKU 未找到: ' . $skuId, $string);
        $this->assertStringContainsString(__FILE__, $string);
    }

    public function testMultipleParameterCombinations(): void
    {
        // Test with null previous
        $exception1 = new SkuNotFoundException('TEST_SKU', 100, null);
        $this->assertEquals('SKU 未找到: TEST_SKU', $exception1->getMessage());
        $this->assertEquals(100, $exception1->getCode());
        $this->assertNull($exception1->getPrevious());

        // Test with large code
        $exception2 = new SkuNotFoundException('LARGE_CODE_SKU', 999999);
        $this->assertEquals('SKU 未找到: LARGE_CODE_SKU', $exception2->getMessage());
        $this->assertEquals(999999, $exception2->getCode());
    }

    public function testExceptionWithSpecialCharactersInSkuId(): void
    {
        $skuId = 'special_sku_"with"_<chars>&symbols';
        $exception = new SkuNotFoundException($skuId);

        $expectedMessage = sprintf('SKU 未找到: %s', $skuId);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testExceptionWithUnicodeSkuId(): void
    {
        $skuId = '商品编码_🔍_不存在';
        $exception = new SkuNotFoundException($skuId);

        $expectedMessage = sprintf('SKU 未找到: %s', $skuId);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testCommonSkuIdFormats(): void
    {
        // Case 1: Alphanumeric SKU
        $exception1 = new SkuNotFoundException('SKU123ABC');
        $this->assertEquals('SKU 未找到: SKU123ABC', $exception1->getMessage());

        // Case 2: UUID format SKU
        $exception2 = new SkuNotFoundException('550e8400-e29b-41d4-a716-446655440000', 404);
        $this->assertEquals('SKU 未找到: 550e8400-e29b-41d4-a716-446655440000', $exception2->getMessage());
        $this->assertEquals(404, $exception2->getCode());

        // Case 3: Barcode format SKU
        $exception3 = new SkuNotFoundException('1234567890123');
        $this->assertEquals('SKU 未找到: 1234567890123', $exception3->getMessage());

        // Case 4: Internal SKU format
        $exception4 = new SkuNotFoundException('PROD-CAT-001-SIZE-M');
        $this->assertEquals('SKU 未找到: PROD-CAT-001-SIZE-M', $exception4->getMessage());
    }

    public function testNumericSkuIds(): void
    {
        // Test with pure numeric SKU
        $exception1 = new SkuNotFoundException('123456789');
        $this->assertEquals('SKU 未找到: 123456789', $exception1->getMessage());

        // Test with decimal SKU
        $exception2 = new SkuNotFoundException('123.456');
        $this->assertEquals('SKU 未找到: 123.456', $exception2->getMessage());

        // Test with negative numeric SKU (edge case)
        $exception3 = new SkuNotFoundException('-999');
        $this->assertEquals('SKU 未找到: -999', $exception3->getMessage());
    }

    public function testSkuIdWithDifferentCases(): void
    {
        // Uppercase SKU
        $exception1 = new SkuNotFoundException('SKU-UPPERCASE-123');
        $this->assertEquals('SKU 未找到: SKU-UPPERCASE-123', $exception1->getMessage());

        // Lowercase SKU
        $exception2 = new SkuNotFoundException('sku-lowercase-456');
        $this->assertEquals('SKU 未找到: sku-lowercase-456', $exception2->getMessage());

        // Mixed case SKU
        $exception3 = new SkuNotFoundException('Sku-MixedCase-789');
        $this->assertEquals('SKU 未找到: Sku-MixedCase-789', $exception3->getMessage());
    }

    public function testSkuIdWithSpecialSeparators(): void
    {
        // Hyphen separated
        $exception1 = new SkuNotFoundException('SKU-123-ABC');
        $this->assertEquals('SKU 未找到: SKU-123-ABC', $exception1->getMessage());

        // Underscore separated
        $exception2 = new SkuNotFoundException('SKU_123_ABC');
        $this->assertEquals('SKU 未找到: SKU_123_ABC', $exception2->getMessage());

        // Dot separated
        $exception3 = new SkuNotFoundException('SKU.123.ABC');
        $this->assertEquals('SKU 未找到: SKU.123.ABC', $exception3->getMessage());

        // Colon separated
        $exception4 = new SkuNotFoundException('SKU:123:ABC');
        $this->assertEquals('SKU 未找到: SKU:123:ABC', $exception4->getMessage());
    }

    public function testLongSkuIds(): void
    {
        $longSkuId = str_repeat('VERY_LONG_SKU_ID_', 10) . '123';
        $exception = new SkuNotFoundException($longSkuId);

        $expectedMessage = sprintf('SKU 未找到: %s', $longSkuId);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testSkuIdWithWhitespace(): void
    {
        // SKU with spaces (edge case)
        $exception1 = new SkuNotFoundException('SKU 123 ABC');
        $this->assertEquals('SKU 未找到: SKU 123 ABC', $exception1->getMessage());

        // SKU with tabs
        $exception2 = new SkuNotFoundException("SKU\t123\tABC");
        $this->assertEquals("SKU 未找到: SKU\t123\tABC", $exception2->getMessage());

        // SKU with newlines (edge case)
        $exception3 = new SkuNotFoundException("SKU\n123");
        $this->assertEquals("SKU 未找到: SKU\n123", $exception3->getMessage());
    }

    public function testCommonHttpErrorCodes(): void
    {
        // HTTP 404 Not Found (most common for SKU not found)
        $exception1 = new SkuNotFoundException('NOT_FOUND_SKU', 404);
        $this->assertEquals(404, $exception1->getCode());

        // HTTP 400 Bad Request
        $exception2 = new SkuNotFoundException('BAD_REQUEST_SKU', 400);
        $this->assertEquals(400, $exception2->getCode());

        // HTTP 422 Unprocessable Entity
        $exception3 = new SkuNotFoundException('UNPROCESSABLE_SKU', 422);
        $this->assertEquals(422, $exception3->getCode());

        // HTTP 500 Internal Server Error
        $exception4 = new SkuNotFoundException('SERVER_ERROR_SKU', 500);
        $this->assertEquals(500, $exception4->getCode());
    }

    public function testBusinessLogicScenarios(): void
    {
        // Deleted SKU
        $exception1 = new SkuNotFoundException('DELETED_SKU_001', 410);
        $this->assertEquals('SKU 未找到: DELETED_SKU_001', $exception1->getMessage());
        $this->assertEquals(410, $exception1->getCode());

        // Expired SKU
        $exception2 = new SkuNotFoundException('EXPIRED_SKU_002');
        $this->assertEquals('SKU 未找到: EXPIRED_SKU_002', $exception2->getMessage());

        // Inactive SKU
        $exception3 = new SkuNotFoundException('INACTIVE_SKU_003');
        $this->assertEquals('SKU 未找到: INACTIVE_SKU_003', $exception3->getMessage());

        // Out of stock SKU (treated as not found)
        $exception4 = new SkuNotFoundException('OUT_OF_STOCK_004');
        $this->assertEquals('SKU 未找到: OUT_OF_STOCK_004', $exception4->getMessage());
    }
}
