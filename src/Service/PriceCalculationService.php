<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Tourze\OrderCheckoutBundle\Contract\PriceCalculatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationFailureException;

/**
 * 价格计算服务
 */
#[Autoconfigure(lazy: true)]
#[WithMonologChannel(channel: 'order_checkout')]
final class PriceCalculationService
{
    /**
     * @var PriceCalculatorInterface[]
     */
    private array $calculators = [];

    public function __construct(
        private readonly LoggerInterface $logger,
        #[AutowireIterator(tag: 'order_checkout.price_calculator')] $calculators,
    )
    {
        foreach ($calculators as $calculator) {
            $this->addCalculator($calculator);
        }
    }

    /**
     * 添加价格计算器
     */
    public function addCalculator(PriceCalculatorInterface $calculator): void
    {
        $this->calculators[] = $calculator;

        $this->logger->debug('[计价服务] 添加价格计算器', [
            'calculatorType' => $calculator->getType(),
            'calculatorClass' => get_class($calculator),
            'priority' => $calculator->getPriority(),
            'totalCalculators' => count($this->calculators),
        ]);

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
        $this->logger->debug('[计价服务] 开始计价流程', [
            'registeredCalculatorsCount' => count($this->calculators),
            'registeredCalculators' => array_map(fn($calc) => [
                'type' => $calc->getType(),
                'class' => get_class($calc),
                'priority' => $calc->getPriority(),
            ], $this->calculators),
            'itemsCount' => count($context->getItems()),
            'appliedCouponsCount' => count($context->getAppliedCoupons()),
        ]);

        // 如果没有商品且没有优惠券，直接返回空结果
        if (0 === count($context->getItems()) && 0 === count($context->getAppliedCoupons())) {
            $this->logger->debug('[计价服务] 没有商品和优惠券，返回空价格结果');
            return PriceResult::empty();
        }

        $result = PriceResult::empty();

        foreach ($this->calculators as $calculator) {
            $this->logger->debug('[计价服务] 检查计算器支持', [
                'calculatorType' => $calculator->getType(),
                'supports' => $calculator->supports($context),
            ]);

            if (!$calculator->supports($context)) {
                $this->logger->debug('[计价服务] 跳过不支持的价格计算器', [
                    'calculatorType' => $calculator->getType(),
                ]);
                continue;
            }

            try {
                $this->logger->debug('[计价服务] 执行价格计算器', [
                    'calculatorType' => $calculator->getType(),
                ]);
                $calculatorResult = $calculator->calculate($context);
                $this->logger->debug('[计价服务] 价格计算器执行完成', [
                    'calculatorType' => $calculator->getType(),
                    'resultOriginalPrice' => $calculatorResult->getOriginalPrice(),
                    'resultFinalPrice' => $calculatorResult->getFinalPrice(),
                    'resultProductsCount' => count($calculatorResult->getProducts()),
                ]);
                $result = $result->merge($calculatorResult);
            } catch (\Exception $e) {
                $this->logger->error('[计价服务] 价格计算器执行失败', [
                    'calculatorType' => $calculator->getType(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
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
