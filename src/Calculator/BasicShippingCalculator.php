<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Calculator;

use Tourze\OrderCheckoutBundle\Contract\ShippingCalculatorInterface;
use Tourze\OrderCheckoutBundle\DTO\ShippingContext;
use Tourze\OrderCheckoutBundle\DTO\ShippingResult;

/**
 * 基础运费计算器
 * 实现简单的运费计算逻辑：满额包邮 + 地区差异化运费
 */
class BasicShippingCalculator implements ShippingCalculatorInterface
{
    /**
     * 免邮门槛
     */
    private const FREE_SHIPPING_THRESHOLD = 99.0;

    /**
     * 地区运费配置
     *
     * @var array<string, float>
     */
    private const REGION_SHIPPING_FEES = [
        // 江浙沪包邮区域
        'shanghai' => 0.0,
        'jiangsu' => 0.0,
        'zhejiang' => 0.0,

        // 一线城市
        'beijing' => 8.0,
        'guangzhou' => 8.0,
        'shenzhen' => 8.0,

        // 普通地区
        'default' => 12.0,

        // 偏远地区
        'xinjiang' => 25.0,
        'xizang' => 25.0,
        'qinghai' => 20.0,
        'gansu' => 15.0,
    ];

    public function calculate(ShippingContext $context): ShippingResult
    {
        $totalValue = $context->getTotalValue();
        $region = $context->getRegion();

        // 检查是否满足免邮条件
        if ($totalValue >= self::FREE_SHIPPING_THRESHOLD) {
            return ShippingResult::free(
                "满{$this->formatPrice(self::FREE_SHIPPING_THRESHOLD)}包邮",
                [
                    'order_value' => $totalValue,
                    'threshold' => self::FREE_SHIPPING_THRESHOLD,
                    'region' => $region,
                ]
            );
        }

        // 计算运费
        $shippingFee = $this->getRegionShippingFee($region);

        return ShippingResult::paid(
            $shippingFee,
            'standard',
            [
                'order_value' => $totalValue,
                'region' => $region,
                'base_fee' => $shippingFee,
                'free_threshold' => self::FREE_SHIPPING_THRESHOLD,
                'needed_for_free' => self::FREE_SHIPPING_THRESHOLD - $totalValue,
            ]
        );
    }

    public function supports(array $items, string $region): bool
    {
        // 基础运费计算器支持所有地区
        return count($items) > 0;
    }

    public function getType(): string
    {
        return 'basic_shipping';
    }

    public function getPriority(): int
    {
        return 100;
    }

    /**
     * 获取地区运费
     */
    private function getRegionShippingFee(string $region): float
    {
        return self::REGION_SHIPPING_FEES[$region] ?? self::REGION_SHIPPING_FEES['default'];
    }

    /**
     * 格式化价格显示
     */
    private function formatPrice(float $price): string
    {
        return '¥' . number_format($price, 2);
    }
}
