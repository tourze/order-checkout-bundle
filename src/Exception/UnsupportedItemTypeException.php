<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Exception;

/**
 * 当传入不支持的商品类型时抛出的异常
 */
final class UnsupportedItemTypeException extends PriceCalculationException
{
    public function __construct(string $itemType, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('不支持的商品类型: %s', $itemType);
        parent::__construct($message, $code, $previous);
    }
}
