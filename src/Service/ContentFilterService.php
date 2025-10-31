<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Tourze\OrderCheckoutBundle\DTO\FilterResult;
use Tourze\OrderCheckoutBundle\Exception\ContentFilterException;

class ContentFilterService
{
    /** @var array<string> */
    private array $sensitiveWords = [
        '色情', '暴力', '政治', '反动', '恐怖', '血腥',
        '赌博', '毒品', '走私', '诈骗', '洗钱', '传销',
        '非法', '违法', '犯罪', '杀害', '自杀', '爆炸',
        'fuck', 'shit', 'damn', 'bitch', 'hell',
    ];

    public function filterContent(string $content): FilterResult
    {
        if ('' === $content) {
            return new FilterResult($content, $content, false, []);
        }

        $originalContent = $content;
        $filteredWords = [];
        $hasFilteredContent = false;

        foreach ($this->sensitiveWords as $word) {
            if (str_contains($content, $word)) {
                $replacement = str_repeat('*', mb_strlen($word));
                $content = str_replace($word, $replacement, $content);
                $filteredWords[] = $word;
                $hasFilteredContent = true;
            }
        }

        return new FilterResult(
            $originalContent,
            $content,
            $hasFilteredContent,
            $filteredWords
        );
    }

    public function isContentAllowed(string $content): bool
    {
        foreach ($this->sensitiveWords as $word) {
            if (str_contains($content, $word)) {
                return false;
            }
        }

        return true;
    }

    public function validateEmojiContent(string $content): bool
    {
        return mb_check_encoding($content, 'UTF-8');
    }

    public function sanitizeRemark(string $remark): string
    {
        $remark = trim($remark);

        if (!$this->validateEmojiContent($remark)) {
            throw new ContentFilterException('备注内容包含无效字符');
        }

        if (mb_strlen($remark) > 200) {
            throw new ContentFilterException('备注内容不能超过200个字符');
        }

        return $remark;
    }
}
