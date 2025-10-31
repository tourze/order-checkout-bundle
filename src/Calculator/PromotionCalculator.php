<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Calculator;

use Tourze\OrderCheckoutBundle\Contract\PriceCalculatorInterface;
use Tourze\OrderCheckoutBundle\Contract\PromotionMatcherInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\DTO\PromotionResult;

/**
 * 促销价格计算器
 * 基于促销匹配器计算促销折扣
 */
class PromotionCalculator implements PriceCalculatorInterface
{
    /**
     * @var PromotionMatcherInterface[]
     */
    private array $matchers = [];

    /**
     * 添加促销匹配器
     */
    public function addMatcher(PromotionMatcherInterface $matcher): void
    {
        $this->matchers[] = $matcher;

        // 按优先级排序
        usort($this->matchers, fn (PromotionMatcherInterface $a, PromotionMatcherInterface $b) => $b->getPriority() <=> $a->getPriority());
    }

    public function calculate(CalculationContext $context): PriceResult
    {
        $promotionResult = PromotionResult::empty();

        foreach ($this->matchers as $matcher) {
            if (!$matcher->supports($context)) {
                continue;
            }

            $result = $matcher->match($context);
            if ($result->hasPromotions()) {
                $promotionResult = $promotionResult->merge($result);
            }
        }

        return new PriceResult(
            originalPrice: 0.0, // 促销计算器不改变原价
            finalPrice: -$promotionResult->getDiscount(), // 负值表示折扣
            discount: $promotionResult->getDiscount(),
            details: [
                'promotions' => $promotionResult->getPromotions(),
                'promotion_details' => $promotionResult->getDetails(),
            ]
        );
    }

    public function supports(CalculationContext $context): bool
    {
        // 只要有促销匹配器，就尝试计算
        return count($this->matchers) > 0 && count($context->getItems()) > 0;
    }

    public function getPriority(): int
    {
        // 在基础价格之后计算
        return 800;
    }

    public function getType(): string
    {
        return 'promotion';
    }
}
