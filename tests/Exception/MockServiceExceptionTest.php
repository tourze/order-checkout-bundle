<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Exception\MockServiceException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * MockServiceException 测试
 * @internal
 */
#[CoversClass(MockServiceException::class)]
final class MockServiceExceptionTest extends AbstractExceptionTestCase
{
    public function testMethodShouldNotBeCalled(): void
    {
        $method = 'TestClass::testMethod';
        $exception = MockServiceException::methodShouldNotBeCalled($method);

        $this->assertInstanceOf(MockServiceException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertStringContainsString($method, $exception->getMessage());
        $this->assertStringContainsString('should not be called in tests', $exception->getMessage());
    }

    public function testExceptionMessage(): void
    {
        $method = 'SomeService::someMethod';
        $exception = MockServiceException::methodShouldNotBeCalled($method);

        $expectedMessage = sprintf('%s should not be called in tests', $method);
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }
}
