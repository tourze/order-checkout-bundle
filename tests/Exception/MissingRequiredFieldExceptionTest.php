<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Exception\MissingRequiredFieldException;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(MissingRequiredFieldException::class)]
final class MissingRequiredFieldExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new MissingRequiredFieldException('testField');

        $this->assertInstanceOf(PriceCalculationException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testConstructorWithFieldName(): void
    {
        $fieldName = 'skuId';
        $exception = new MissingRequiredFieldException($fieldName);

        $expectedMessage = sprintf('缺少必要字段: %s', $fieldName);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertIsString($exception->getFile());
        $this->assertIsInt($exception->getLine());
        $this->assertGreaterThan(0, $exception->getLine());
    }

    public function testConstructorWithFieldNameAndCode(): void
    {
        $fieldName = 'quantity';
        $code = 400;
        $exception = new MissingRequiredFieldException($fieldName, $code);

        $expectedMessage = sprintf('缺少必要字段: %s', $fieldName);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $fieldName = 'orderId';
        $code = 422;
        $previous = new \InvalidArgumentException('字段验证失败');
        $exception = new MissingRequiredFieldException($fieldName, $code, $previous);

        $expectedMessage = sprintf('缺少必要字段: %s', $fieldName);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithEmptyFieldName(): void
    {
        $exception = new MissingRequiredFieldException('');

        $this->assertEquals('缺少必要字段: ', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithZeroCode(): void
    {
        $fieldName = 'paymentMethod';
        $exception = new MissingRequiredFieldException($fieldName, 0);

        $expectedMessage = sprintf('缺少必要字段: %s', $fieldName);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithNegativeCode(): void
    {
        $fieldName = 'userId';
        $code = -500;
        $exception = new MissingRequiredFieldException($fieldName, $code);

        $expectedMessage = sprintf('缺少必要字段: %s', $fieldName);
        $this->assertEquals($expectedMessage, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \RuntimeException('数据结构验证失败');
        $validationException = new \InvalidArgumentException('必要字段缺失', 0, $rootCause);
        $fieldException = new MissingRequiredFieldException('addressId', 500, $validationException);

        $this->assertEquals('缺少必要字段: addressId', $fieldException->getMessage());
        $this->assertEquals(500, $fieldException->getCode());
        $this->assertSame($validationException, $fieldException->getPrevious());
        $this->assertSame($rootCause, $fieldException->getPrevious()->getPrevious());
    }

    public function testStackTrace(): void
    {
        $exception = new MissingRequiredFieldException('testField');
        $trace = $exception->getTrace();

        $this->assertIsArray($trace);
        $this->assertArrayHasKey('file', $trace[0]);
        $this->assertArrayHasKey('line', $trace[0]);
        $this->assertArrayHasKey('function', $trace[0]);
    }

    public function testTraceAsString(): void
    {
        $exception = new MissingRequiredFieldException('testField');
        $traceString = $exception->getTraceAsString();

        $this->assertIsString($traceString);
        $this->assertStringContainsString(__CLASS__, $traceString);
        $this->assertStringContainsString(__FUNCTION__, $traceString);
    }

    public function testToString(): void
    {
        $fieldName = 'testField';
        $exception = new MissingRequiredFieldException($fieldName);
        $string = (string) $exception;

        $this->assertIsString($string);
        $this->assertStringContainsString(MissingRequiredFieldException::class, $string);
        $this->assertStringContainsString('缺少必要字段: ' . $fieldName, $string);
        $this->assertStringContainsString(__FILE__, $string);
    }

    public function testMultipleParameterCombinations(): void
    {
        // Test with null previous
        $exception1 = new MissingRequiredFieldException('testField', 100, null);
        $this->assertEquals('缺少必要字段: testField', $exception1->getMessage());
        $this->assertEquals(100, $exception1->getCode());
        $this->assertNull($exception1->getPrevious());

        // Test with large code
        $exception2 = new MissingRequiredFieldException('largeCodeField', 999999);
        $this->assertEquals('缺少必要字段: largeCodeField', $exception2->getMessage());
        $this->assertEquals(999999, $exception2->getCode());
    }

    public function testExceptionWithSpecialCharactersInFieldName(): void
    {
        $fieldName = 'special_field_"with"_<chars>&symbols';
        $exception = new MissingRequiredFieldException($fieldName);

        $expectedMessage = sprintf('缺少必要字段: %s', $fieldName);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testExceptionWithUnicodeFieldName(): void
    {
        $fieldName = '用户字段_🔍_必要';
        $exception = new MissingRequiredFieldException($fieldName);

        $expectedMessage = sprintf('缺少必要字段: %s', $fieldName);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testCommonFieldNames(): void
    {
        // Case 1: SKU ID field
        $exception1 = new MissingRequiredFieldException('skuId');
        $this->assertEquals('缺少必要字段: skuId', $exception1->getMessage());

        // Case 2: Quantity field
        $exception2 = new MissingRequiredFieldException('quantity', 400);
        $this->assertEquals('缺少必要字段: quantity', $exception2->getMessage());
        $this->assertEquals(400, $exception2->getCode());

        // Case 3: User ID field
        $exception3 = new MissingRequiredFieldException('userId');
        $this->assertEquals('缺少必要字段: userId', $exception3->getMessage());

        // Case 4: Order ID field
        $exception4 = new MissingRequiredFieldException('orderId');
        $this->assertEquals('缺少必要字段: orderId', $exception4->getMessage());
    }

    public function testNestedFieldNames(): void
    {
        // Test with dot notation field names
        $exception1 = new MissingRequiredFieldException('user.profile.email');
        $this->assertEquals('缺少必要字段: user.profile.email', $exception1->getMessage());

        // Test with array notation field names
        $exception2 = new MissingRequiredFieldException('items[0].skuId');
        $this->assertEquals('缺少必要字段: items[0].skuId', $exception2->getMessage());

        // Test with complex nested field names
        $exception3 = new MissingRequiredFieldException('checkout.payment.method.type');
        $this->assertEquals('缺少必要字段: checkout.payment.method.type', $exception3->getMessage());
    }

    public function testFieldNamesWithUnderscores(): void
    {
        // Test with snake_case field names
        $exception1 = new MissingRequiredFieldException('payment_method');
        $this->assertEquals('缺少必要字段: payment_method', $exception1->getMessage());

        // Test with long snake_case field names
        $exception2 = new MissingRequiredFieldException('shipping_address_postal_code');
        $this->assertEquals('缺少必要字段: shipping_address_postal_code', $exception2->getMessage());
    }

    public function testFieldNamesWithCamelCase(): void
    {
        // Test with camelCase field names
        $exception1 = new MissingRequiredFieldException('paymentMethod');
        $this->assertEquals('缺少必要字段: paymentMethod', $exception1->getMessage());

        // Test with long camelCase field names
        $exception2 = new MissingRequiredFieldException('shippingAddressPostalCode');
        $this->assertEquals('缺少必要字段: shippingAddressPostalCode', $exception2->getMessage());
    }

    public function testNumericFieldNames(): void
    {
        // Test with numeric field names
        $exception1 = new MissingRequiredFieldException('123');
        $this->assertEquals('缺少必要字段: 123', $exception1->getMessage());

        // Test with mixed alphanumeric field names
        $exception2 = new MissingRequiredFieldException('field123');
        $this->assertEquals('缺少必要字段: field123', $exception2->getMessage());

        // Test with field names starting with numbers
        $exception3 = new MissingRequiredFieldException('1stField');
        $this->assertEquals('缺少必要字段: 1stField', $exception3->getMessage());
    }

    public function testCommonErrorCodes(): void
    {
        // HTTP 400 Bad Request
        $exception1 = new MissingRequiredFieldException('badField', 400);
        $this->assertEquals(400, $exception1->getCode());

        // HTTP 422 Unprocessable Entity
        $exception2 = new MissingRequiredFieldException('unprocessableField', 422);
        $this->assertEquals(422, $exception2->getCode());

        // HTTP 500 Internal Server Error
        $exception3 = new MissingRequiredFieldException('serverErrorField', 500);
        $this->assertEquals(500, $exception3->getCode());
    }
}
