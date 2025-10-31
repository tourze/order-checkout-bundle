<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationException;
use Tourze\OrderCheckoutBundle\Exception\UnsupportedItemTypeException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(UnsupportedItemTypeException::class)]
final class UnsupportedItemTypeExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new UnsupportedItemTypeException('test_type');

        $this->assertInstanceOf(PriceCalculationException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testConstructorWithItemType(): void
    {
        $itemType = 'unsupported_item';
        $exception = new UnsupportedItemTypeException($itemType);

        $expectedMessage = sprintf('不支持的商品类型: %s', $itemType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertIsString($exception->getFile());
        $this->assertIsInt($exception->getLine());
        $this->assertGreaterThan(0, $exception->getLine());
    }

    public function testConstructorWithItemTypeAndCode(): void
    {
        $itemType = 'deprecated_type';
        $code = 400;
        $exception = new UnsupportedItemTypeException($itemType, $code);

        $expectedMessage = sprintf('不支持的商品类型: %s', $itemType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $itemType = 'invalid_type';
        $code = 422;
        $previous = new \InvalidArgumentException('商品类型验证失败');
        $exception = new UnsupportedItemTypeException($itemType, $code, $previous);

        $expectedMessage = sprintf('不支持的商品类型: %s', $itemType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithEmptyItemType(): void
    {
        $exception = new UnsupportedItemTypeException('');

        $this->assertEquals('不支持的商品类型: ', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithZeroCode(): void
    {
        $itemType = 'zero_code_type';
        $exception = new UnsupportedItemTypeException($itemType, 0);

        $expectedMessage = sprintf('不支持的商品类型: %s', $itemType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithNegativeCode(): void
    {
        $itemType = 'negative_code_type';
        $code = -500;
        $exception = new UnsupportedItemTypeException($itemType, $code);

        $expectedMessage = sprintf('不支持的商品类型: %s', $itemType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \RuntimeException('商品类型数据库查询失败');
        $validationException = new \InvalidArgumentException('商品类型格式不正确', 0, $rootCause);
        $itemTypeException = new UnsupportedItemTypeException('unknown_format', 500, $validationException);

        $this->assertEquals('不支持的商品类型: unknown_format', $itemTypeException->getMessage());
        $this->assertEquals(500, $itemTypeException->getCode());
        $this->assertSame($validationException, $itemTypeException->getPrevious());
        $this->assertSame($rootCause, $itemTypeException->getPrevious()->getPrevious());
    }

    public function testStackTrace(): void
    {
        $exception = new UnsupportedItemTypeException('trace_test_type');
        $trace = $exception->getTrace();

        $this->assertIsArray($trace);
        $this->assertArrayHasKey('file', $trace[0]);
        $this->assertArrayHasKey('line', $trace[0]);
        $this->assertArrayHasKey('function', $trace[0]);
    }

    public function testTraceAsString(): void
    {
        $exception = new UnsupportedItemTypeException('string_test_type');
        $traceString = $exception->getTraceAsString();

        $this->assertIsString($traceString);
        $this->assertStringContainsString(__CLASS__, $traceString);
        $this->assertStringContainsString(__FUNCTION__, $traceString);
    }

    public function testToString(): void
    {
        $itemType = 'to_string_test_type';
        $exception = new UnsupportedItemTypeException($itemType);
        $string = (string) $exception;

        $this->assertIsString($string);
        $this->assertStringContainsString(UnsupportedItemTypeException::class, $string);
        $this->assertStringContainsString('不支持的商品类型: ' . $itemType, $string);
        $this->assertStringContainsString(__FILE__, $string);
    }

    public function testMultipleParameterCombinations(): void
    {
        // Test with null previous
        $exception1 = new UnsupportedItemTypeException('test_type', 100, null);
        $this->assertEquals('不支持的商品类型: test_type', $exception1->getMessage());
        $this->assertEquals(100, $exception1->getCode());
        $this->assertNull($exception1->getPrevious());

        // Test with large code
        $exception2 = new UnsupportedItemTypeException('large_code_type', 999999);
        $this->assertEquals('不支持的商品类型: large_code_type', $exception2->getMessage());
        $this->assertEquals(999999, $exception2->getCode());
    }

    public function testExceptionWithSpecialCharactersInItemType(): void
    {
        $itemType = 'special_type_"with"_<chars>&symbols';
        $exception = new UnsupportedItemTypeException($itemType);

        $expectedMessage = sprintf('不支持的商品类型: %s', $itemType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testExceptionWithUnicodeItemType(): void
    {
        $itemType = '不支持商品_🚫_错误类型';
        $exception = new UnsupportedItemTypeException($itemType);

        $expectedMessage = sprintf('不支持的商品类型: %s', $itemType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testCommonItemTypes(): void
    {
        // Case 1: Virtual product type
        $exception1 = new UnsupportedItemTypeException('virtual_product');
        $this->assertEquals('不支持的商品类型: virtual_product', $exception1->getMessage());

        // Case 2: Subscription type
        $exception2 = new UnsupportedItemTypeException('subscription', 400);
        $this->assertEquals('不支持的商品类型: subscription', $exception2->getMessage());
        $this->assertEquals(400, $exception2->getCode());

        // Case 3: Gift card type
        $exception3 = new UnsupportedItemTypeException('gift_card');
        $this->assertEquals('不支持的商品类型: gift_card', $exception3->getMessage());

        // Case 4: Bundle type
        $exception4 = new UnsupportedItemTypeException('bundle_product');
        $this->assertEquals('不支持的商品类型: bundle_product', $exception4->getMessage());
    }

    public function testECommerceItemTypes(): void
    {
        // Physical goods
        $exception1 = new UnsupportedItemTypeException('physical_goods');
        $this->assertEquals('不支持的商品类型: physical_goods', $exception1->getMessage());

        // Digital downloads
        $exception2 = new UnsupportedItemTypeException('digital_download');
        $this->assertEquals('不支持的商品类型: digital_download', $exception2->getMessage());

        // Services
        $exception3 = new UnsupportedItemTypeException('service_item');
        $this->assertEquals('不支持的商品类型: service_item', $exception3->getMessage());

        // Memberships
        $exception4 = new UnsupportedItemTypeException('membership');
        $this->assertEquals('不支持的商品类型: membership', $exception4->getMessage());
    }

    public function testDeprecatedItemTypes(): void
    {
        // Deprecated product type
        $exception1 = new UnsupportedItemTypeException('deprecated_v1');
        $this->assertEquals('不支持的商品类型: deprecated_v1', $exception1->getMessage());

        // Legacy format
        $exception2 = new UnsupportedItemTypeException('legacy_format', 410);
        $this->assertEquals('不支持的商品类型: legacy_format', $exception2->getMessage());
        $this->assertEquals(410, $exception2->getCode());

        // Discontinued type
        $exception3 = new UnsupportedItemTypeException('discontinued_type');
        $this->assertEquals('不支持的商品类型: discontinued_type', $exception3->getMessage());
    }

    public function testNumericItemTypes(): void
    {
        // Test with numeric type as string
        $exception1 = new UnsupportedItemTypeException('123');
        $this->assertEquals('不支持的商品类型: 123', $exception1->getMessage());

        // Test with decimal type
        $exception2 = new UnsupportedItemTypeException('45.67');
        $this->assertEquals('不支持的商品类型: 45.67', $exception2->getMessage());

        // Test with negative type
        $exception3 = new UnsupportedItemTypeException('-999');
        $this->assertEquals('不支持的商品类型: -999', $exception3->getMessage());
    }

    public function testComplexItemTypeNames(): void
    {
        // Camel case type
        $exception1 = new UnsupportedItemTypeException('complexItemType');
        $this->assertEquals('不支持的商品类型: complexItemType', $exception1->getMessage());

        // Snake case type
        $exception2 = new UnsupportedItemTypeException('complex_item_type');
        $this->assertEquals('不支持的商品类型: complex_item_type', $exception2->getMessage());

        // Kebab case type
        $exception3 = new UnsupportedItemTypeException('complex-item-type');
        $this->assertEquals('不支持的商品类型: complex-item-type', $exception3->getMessage());

        // Mixed case with numbers
        $exception4 = new UnsupportedItemTypeException('ItemType_v2.0');
        $this->assertEquals('不支持的商品类型: ItemType_v2.0', $exception4->getMessage());
    }

    public function testLongItemTypeNames(): void
    {
        $longType = str_repeat('very_long_item_type_name_', 5);
        $exception = new UnsupportedItemTypeException($longType);

        $expectedMessage = sprintf('不支持的商品类型: %s', $longType);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testCommonErrorCodes(): void
    {
        // HTTP 400 Bad Request
        $exception1 = new UnsupportedItemTypeException('bad_type', 400);
        $this->assertEquals(400, $exception1->getCode());

        // HTTP 404 Not Found
        $exception2 = new UnsupportedItemTypeException('not_found_type', 404);
        $this->assertEquals(404, $exception2->getCode());

        // HTTP 410 Gone
        $exception3 = new UnsupportedItemTypeException('gone_type', 410);
        $this->assertEquals(410, $exception3->getCode());

        // HTTP 422 Unprocessable Entity
        $exception4 = new UnsupportedItemTypeException('unprocessable_type', 422);
        $this->assertEquals(422, $exception4->getCode());

        // HTTP 501 Not Implemented
        $exception5 = new UnsupportedItemTypeException('not_implemented_type', 501);
        $this->assertEquals(501, $exception5->getCode());
    }
}
