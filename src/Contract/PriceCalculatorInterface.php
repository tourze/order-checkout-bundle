<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Contract;

use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;

/**
 * 价格计算器接口
 */
interface PriceCalculatorInterface
{
    /**
     * 计算价格
     */
    public function calculate(CalculationContext $context): PriceResult;

    /**
     * 是否支持当前计算上下文
     */
    public function supports(CalculationContext $context): bool;

    /**
     * 获取计算器优先级（数字越大优先级越高）
     */
    public function getPriority(): int;

    /**
     * 获取计算器类型标识
     */
    public function getType(): string;
}
