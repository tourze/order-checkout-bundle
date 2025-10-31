<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(CheckoutException::class)]
final class CheckoutExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new CheckoutException();

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testDefaultConstructor(): void
    {
        $exception = new CheckoutException();

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertIsString($exception->getFile());
        $this->assertIsInt($exception->getLine());
        $this->assertGreaterThan(0, $exception->getLine());
    }

    public function testConstructorWithMessage(): void
    {
        $message = 'Checkout process failed';
        $exception = new CheckoutException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithMessageAndCode(): void
    {
        $message = 'Payment validation failed';
        $code = 402;
        $exception = new CheckoutException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $message = 'Checkout service unavailable';
        $code = 503;
        $previous = new \RuntimeException('Payment gateway timeout');
        $exception = new CheckoutException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testConstructorWithEmptyMessage(): void
    {
        $exception = new CheckoutException('');

        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithZeroCode(): void
    {
        $message = 'Checkout error';
        $exception = new CheckoutException($message, 0);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithNegativeCode(): void
    {
        $message = 'Checkout system error';
        $code = -1;
        $exception = new CheckoutException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \InvalidArgumentException('Invalid payment method');
        $serviceException = new \RuntimeException('Payment processing failed', 0, $rootCause);
        $checkoutException = new CheckoutException('Checkout failed', 500, $serviceException);

        $this->assertSame($serviceException, $checkoutException->getPrevious());
        $this->assertSame($rootCause, $checkoutException->getPrevious()->getPrevious());
    }

    public function testStackTrace(): void
    {
        $exception = new CheckoutException('Test exception');
        $trace = $exception->getTrace();

        $this->assertIsArray($trace);
        $this->assertArrayHasKey('file', $trace[0]);
        $this->assertArrayHasKey('line', $trace[0]);
        $this->assertArrayHasKey('function', $trace[0]);
    }

    public function testTraceAsString(): void
    {
        $exception = new CheckoutException('Test exception');
        $traceString = $exception->getTraceAsString();

        $this->assertIsString($traceString);
        $this->assertStringContainsString(__CLASS__, $traceString);
        $this->assertStringContainsString(__FUNCTION__, $traceString);
    }

    public function testToString(): void
    {
        $message = 'Checkout exception message';
        $exception = new CheckoutException($message);
        $string = (string) $exception;

        $this->assertIsString($string);
        $this->assertStringContainsString(CheckoutException::class, $string);
        $this->assertStringContainsString($message, $string);
        $this->assertStringContainsString(__FILE__, $string);
    }

    public function testMultipleParameterCombinations(): void
    {
        // Test with null previous
        $exception1 = new CheckoutException('Test message', 100, null);
        $this->assertSame('Test message', $exception1->getMessage());
        $this->assertSame(100, $exception1->getCode());
        $this->assertNull($exception1->getPrevious());

        // Test with large code
        $exception2 = new CheckoutException('Large code test', 999999);
        $this->assertSame('Large code test', $exception2->getMessage());
        $this->assertSame(999999, $exception2->getCode());
    }

    public function testExceptionWithSpecialCharactersInMessage(): void
    {
        $message = 'Checkout failed: "Special chars" & symbols <>&';
        $exception = new CheckoutException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function testExceptionWithUnicodeMessage(): void
    {
        $message = '结账失败：支付方式无效 🔄';
        $exception = new CheckoutException($message);

        $this->assertSame($message, $exception->getMessage());
    }
}
