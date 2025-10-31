<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Contract;

use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\PromotionResult;

/**
 * 促销匹配器接口
 */
interface PromotionMatcherInterface
{
    /**
     * 匹配促销规则
     */
    public function match(CalculationContext $context): PromotionResult;

    /**
     * 是否支持当前上下文
     */
    public function supports(CalculationContext $context): bool;

    /**
     * 获取促销类型
     */
    public function getType(): string;

    /**
     * 获取优先级
     */
    public function getPriority(): int;
}
