<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationException;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationFailureException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(PriceCalculationFailureException::class)]
final class PriceCalculationFailureExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInheritance(): void
    {
        $exception = new PriceCalculationFailureException();

        $this->assertInstanceOf(PriceCalculationException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testDefaultConstructor(): void
    {
        $exception = new PriceCalculationFailureException();

        $this->assertSame('价格计算失败', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
        $this->assertIsString($exception->getFile());
        $this->assertIsInt($exception->getLine());
        $this->assertGreaterThan(0, $exception->getLine());
    }

    public function testConstructorWithMessage(): void
    {
        $message = 'Price calculation failed due to invalid data';
        $exception = new PriceCalculationFailureException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithMessageAndCode(): void
    {
        $message = 'Invalid price configuration';
        $code = 422;
        $exception = new PriceCalculationFailureException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithPreviousException(): void
    {
        $message = 'Price calculation service error';
        $code = 500;
        $previous = new \RuntimeException('Database connection failed');
        $exception = new PriceCalculationFailureException($message, $code, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionChaining(): void
    {
        $rootCause = new \InvalidArgumentException('Invalid price input');
        $calculationException = new \RuntimeException('Calculation engine failed', 0, $rootCause);
        $priceException = new PriceCalculationFailureException('Price calculation error', 500, $calculationException);

        $this->assertSame($calculationException, $priceException->getPrevious());
        $this->assertSame($rootCause, $priceException->getPrevious()->getPrevious());
    }
}