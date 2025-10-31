<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Tourze\OrderCheckoutBundle\Contract\PriceCalculatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationFailureException;

/**
 * 价格计算服务
 */
class PriceCalculationService
{
    /**
     * @var PriceCalculatorInterface[]
     */
    private array $calculators = [];

    public function __construct()
    {
        // 简化构造函数，使用依赖注入时通过 addCalculator 方法调用
    }

    /**
     * 添加价格计算器
     */
    public function addCalculator(PriceCalculatorInterface $calculator): void
    {
        $this->calculators[] = $calculator;

        // 按优先级排序（高优先级先执行）
        usort($this->calculators, fn (PriceCalculatorInterface $a, PriceCalculatorInterface $b) => $b->getPriority() <=> $a->getPriority());
    }

    /**
     * 计算价格
     *
     * @throws PriceCalculationFailureException
     */
    public function calculate(CalculationContext $context): PriceResult
    {
        if (0 === count($context->getItems())) {
            return PriceResult::empty();
        }

        $result = PriceResult::empty();

        foreach ($this->calculators as $calculator) {
            if (!$calculator->supports($context)) {
                continue;
            }

            try {
                $calculatorResult = $calculator->calculate($context);
                $result = $result->merge($calculatorResult);
            } catch (\Exception $e) {
                throw new PriceCalculationFailureException(sprintf('价格计算器 %s 执行失败: %s', $calculator->getType(), $e->getMessage()), 0, $e);
            }
        }

        return $result;
    }

    /**
     * 根据类型获取计算器
     */
    public function getCalculatorByType(string $type): ?PriceCalculatorInterface
    {
        foreach ($this->calculators as $calculator) {
            if ($calculator->getType() === $type) {
                return $calculator;
            }
        }

        return null;
    }

    /**
     * 获取所有计算器
     *
     * @return PriceCalculatorInterface[]
     */
    public function getCalculators(): array
    {
        return $this->calculators;
    }
}
