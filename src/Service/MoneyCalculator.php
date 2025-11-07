<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

/**
 * 金额计算服务
 * 使用 BCMath 进行精确的货币计算，避免浮点数精度问题
 */
final class MoneyCalculator
{
    private const DEFAULT_SCALE = 2;

    /**
     * 将输入转换为BCMath兼容的数值字符串
     * @return numeric-string
     */
    private static function toNumericString(string|int|float $value): string
    {
        if (is_numeric($value)) {
            /** @var numeric-string */
            return (string) $value;
        }
        return '0';
    }

    /**
     * 金额相加
     */
    public static function add(string|int|float $left, string|int|float $right, int $scale = self::DEFAULT_SCALE): string
    {
        return bcadd(self::toNumericString($left), self::toNumericString($right), $scale);
    }

    /**
     * 金额相减
     */
    public static function subtract(string|int|float $left, string|int|float $right, int $scale = self::DEFAULT_SCALE): string
    {
        return bcsub(self::toNumericString($left), self::toNumericString($right), $scale);
    }

    /**
     * 金额相乘
     */
    public static function multiply(string|int|float $left, string|int|float $right, int $scale = self::DEFAULT_SCALE): string
    {
        return bcmul(self::toNumericString($left), self::toNumericString($right), $scale);
    }

    /**
     * 金额相除
     */
    public static function divide(string|int|float $left, string|int|float $right, int $scale = self::DEFAULT_SCALE): string
    {
        $rightStr = self::toNumericString($right);
        if ('0' === $rightStr || '0.0' === $rightStr) {
            throw new \InvalidArgumentException('除数不能为零');
        }

        return bcdiv(self::toNumericString($left), $rightStr, $scale);
    }

    /**
     * 金额比较
     * @return int -1 if left < right, 0 if equal, 1 if left > right
     */
    public static function compare(string|int|float $left, string|int|float $right, int $scale = self::DEFAULT_SCALE): int
    {
        return bccomp(self::toNumericString($left), self::toNumericString($right), $scale);
    }

    /**
     * 金额是否相等
     */
    public static function equals(string|int|float $left, string|int|float $right, int $scale = self::DEFAULT_SCALE): bool
    {
        return 0 === self::compare($left, $right, $scale);
    }

    /**
     * 金额是否大于
     */
    public static function greaterThan(string|int|float $left, string|int|float $right, int $scale = self::DEFAULT_SCALE): bool
    {
        return 1 === self::compare($left, $right, $scale);
    }

    /**
     * 金额是否小于
     */
    public static function lessThan(string|int|float $left, string|int|float $right, int $scale = self::DEFAULT_SCALE): bool
    {
        return -1 === self::compare($left, $right, $scale);
    }

    /**
     * 格式化金额显示
     */
    public static function format(string|int|float $amount, int $decimals = 2, string $thousandsSeparator = ','): string
    {
        return number_format((float) $amount, $decimals, '.', $thousandsSeparator);
    }

    /**
     * 将金额转换为分（整数）
     * 用于微信支付等需要以分为单位的场景
     */
    public static function toCents(string|int|float $amount): int
    {
        return (int) self::multiply($amount, '100', 0);
    }

    /**
     * 将分转换为元
     */
    public static function fromCents(int $cents): string
    {
        return self::divide($cents, '100', 2);
    }

    /**
     * 金额求和
     * @param array<string|int|float> $amounts
     */
    public static function sum(array $amounts, int $scale = self::DEFAULT_SCALE): string
    {
        $total = '0.00';
        foreach ($amounts as $amount) {
            // 在求和过程中保持更高精度，最后统一四舍五入
            $total = bcadd($total, self::toNumericString($amount), $scale + 4);
        }

        return self::round($total, $scale);
    }

    /**
     * 百分比计算
     */
    public static function percentage(string|int|float $amount, string|int|float $percentage, int $scale = self::DEFAULT_SCALE): string
    {
        return self::multiply($amount, self::divide($percentage, '100', $scale + 2), $scale);
    }

    /**
     * 四舍五入到指定小数位
     */
    public static function round(string|int|float $amount, int $decimals = 2): string
    {
        $factor = bcpow('10', (string) $decimals, 0);
        $multiplied = self::multiply($amount, $factor, $decimals + 2);
        $rounded = round((float) $multiplied);

        $result = self::divide($rounded, $factor, $decimals);
        
        // 确保结果格式正确
        if ($decimals > 0) {
            return number_format((float) $result, $decimals, '.', '');
        }
        
        return $result;
    }
}