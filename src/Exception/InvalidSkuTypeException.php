<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Exception;

/**
 * 当 SKU 类型无效时抛出的异常
 */
final class InvalidSkuTypeException extends PriceCalculationException
{
    public function __construct(string $actualType, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('无效的 SKU 类型: %s', $actualType);
        parent::__construct($message, $code, $previous);
    }
}
