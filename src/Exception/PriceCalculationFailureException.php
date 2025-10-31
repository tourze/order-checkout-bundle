<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Exception;

/**
 * 当价格计算过程中发生通用错误时抛出的异常
 */
class PriceCalculationFailureException extends PriceCalculationException
{
    public function __construct(string $message = '价格计算失败', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}