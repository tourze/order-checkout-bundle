<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Exception;

/**
 * 当 SKU 未找到时抛出的异常
 */
final class SkuNotFoundException extends PriceCalculationException
{
    public function __construct(string $skuId, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('SKU 未找到: %s', $skuId);
        parent::__construct($message, $code, $previous);
    }
}
