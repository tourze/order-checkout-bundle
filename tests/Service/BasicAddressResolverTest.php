<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Service\BasicAddressResolver;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(BasicAddressResolver::class)]
#[RunTestsInSeparateProcesses]
final class BasicAddressResolverTest extends AbstractIntegrationTestCase
{
    private BasicAddressResolver $resolver;

    protected function onSetUp(): void
    {
        $this->resolver = self::getService(BasicAddressResolver::class);
    }

    public function testResolveAddressReturnsCorrectFormat(): void
    {
        $result = $this->resolver->resolveAddress('test_address_id');

        // 测试有效地址ID（数字）
        $validResult = $this->resolver->resolveAddress('123');

        // 有效地址应该返回包含必要字段的数组
        if (null !== $validResult) {
            $this->assertArrayHasKey('province', $validResult);
            $this->assertArrayHasKey('city', $validResult);
            $this->assertArrayHasKey('district', $validResult);
        }

        // 测试无效地址ID
        $invalidResult = $this->resolver->resolveAddress('invalid');
        $this->assertNull($invalidResult);
    }

    public function testAddressExistsReturnsBool(): void
    {
        $result = $this->resolver->addressExists('test_address_id');
        $this->assertIsBool($result);
    }

    public function testResolveAddressWithInvalidId(): void
    {
        $result = $this->resolver->resolveAddress('invalid_address_id');
        $this->assertNull($result);
    }

    public function testAddressExistsWithInvalidId(): void
    {
        $result = $this->resolver->addressExists('invalid_address_id');
        $this->assertFalse($result);
    }
}
