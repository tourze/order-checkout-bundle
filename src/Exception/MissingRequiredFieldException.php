<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Exception;

/**
 * 当缺少必要字段时抛出的异常
 */
class MissingRequiredFieldException extends PriceCalculationException
{
    public function __construct(string $fieldName, int $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('缺少必要字段: %s', $fieldName);
        parent::__construct($message, $code, $previous);
    }
}
