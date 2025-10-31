<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Exception;

/**
 * Mock 服务异常
 *
 * 按照 Linus 原则：简单直接的业务异常类
 * 专门用于测试环境中的 Mock 服务调用错误
 */
final class MockServiceException extends \RuntimeException
{
    public static function methodShouldNotBeCalled(string $method): self
    {
        return new self(sprintf('%s should not be called in tests', $method));
    }
}
