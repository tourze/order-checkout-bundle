<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Exception;

/**
 * 当购物车项无效时抛出的异常
 */
final class InvalidCartItemException extends PriceCalculationException
{
    public function __construct(string $reason = '无效的购物车项对象', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($reason, $code, $previous);
    }
}
