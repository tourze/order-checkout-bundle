<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Contract;

use Tourze\OrderCartBundle\Entity\CartItem;
use Tourze\OrderCheckoutBundle\DTO\ShippingContext;
use Tourze\OrderCheckoutBundle\DTO\ShippingResult;

/**
 * 运费计算器接口
 */
interface ShippingCalculatorInterface
{
    /**
     * 计算运费
     */
    public function calculate(ShippingContext $context): ShippingResult;

    /**
     * 是否支持当前配送场景
     *
     * @param CartItem[] $items
     */
    public function supports(array $items, string $region): bool;

    /**
     * 获取计算器类型
     */
    public function getType(): string;

    /**
     * 获取优先级
     */
    public function getPriority(): int;
}
