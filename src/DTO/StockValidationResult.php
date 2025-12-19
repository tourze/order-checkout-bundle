<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DTO;

/**
 * 库存验证结果
 */
final class StockValidationResult
{
    /**
     * @param array<string, mixed> $errors   库存错误信息
     * @param array<string, mixed> $warnings 库存警告信息
     * @param array<string, mixed> $details  验证详情
     */
    public function __construct(
        private readonly bool $valid,
        private readonly array $errors = [],
        private readonly array $warnings = [],
        private readonly array $details = [],
    ) {
    }

    /**
     * 是否验证通过
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * 获取错误信息
     *
     * @return array<string, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取警告信息
     *
     * @return array<string, mixed>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * 获取验证详情
     *
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * 是否有错误
     */
    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }

    /**
     * 是否有警告
     */
    public function hasWarnings(): bool
    {
        return [] !== $this->warnings;
    }

    /**
     * 创建成功结果
     *
     * @param array<string, mixed> $details
     * @param array<string, mixed> $warnings
     */
    public static function success(array $details = [], array $warnings = []): self
    {
        return new self(true, [], $warnings, $details);
    }

    /**
     * 创建失败结果
     *
     * @param array<string, mixed> $errors
     * @param array<string, mixed> $warnings
     * @param array<string, mixed> $details
     */
    public static function failure(array $errors, array $warnings = [], array $details = []): self
    {
        return new self(false, $errors, $warnings, $details);
    }

    /**
     * 转换为数组
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'details' => $this->details,
        ];
    }
}
