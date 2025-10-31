<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\FilterResult;

/**
 * @internal
 */
#[CoversClass(FilterResult::class)]
final class FilterResultTest extends TestCase
{
    public function testFilterResultCanBeCreated(): void
    {
        $originalContent = '原始内容包含敏感词';
        $filteredContent = '测试内容';
        $hasFilteredContent = true;
        $filteredWords = ['敏感词1', '敏感词2'];

        $result = new FilterResult($originalContent, $filteredContent, $hasFilteredContent, $filteredWords);

        $this->assertSame($originalContent, $result->originalContent);
        $this->assertSame($filteredContent, $result->filteredContent);
        $this->assertTrue($result->hasFilteredContent);
        $this->assertSame($filteredWords, $result->filteredWords);
    }

    public function testFilterResultWithoutFilter(): void
    {
        $originalContent = '正常内容';
        $filteredContent = '正常内容';
        $hasFilteredContent = false;
        $filteredWords = [];

        $result = new FilterResult($originalContent, $filteredContent, $hasFilteredContent, $filteredWords);

        $this->assertSame($originalContent, $result->originalContent);
        $this->assertSame($filteredContent, $result->filteredContent);
        $this->assertFalse($result->hasFilteredContent);
        $this->assertSame([], $result->filteredWords);
    }

    public function testFilterResultWithEmptyFilteredWords(): void
    {
        $originalContent = '内容';
        $filteredContent = '内容';
        $hasFilteredContent = false;
        $filteredWords = [];

        $result = new FilterResult($originalContent, $filteredContent, $hasFilteredContent, $filteredWords);

        $this->assertSame($originalContent, $result->originalContent);
        $this->assertSame($filteredContent, $result->filteredContent);
        $this->assertFalse($result->hasFilteredContent);
        $this->assertEmpty($result->filteredWords);
    }

    public function testToArray(): void
    {
        $originalContent = '原始内容';
        $filteredContent = '过滤内容';
        $hasFilteredContent = true;
        $filteredWords = ['敏感词'];

        $result = new FilterResult($originalContent, $filteredContent, $hasFilteredContent, $filteredWords);
        $array = $result->toArray();

        $expected = [
            'originalContent' => $originalContent,
            'filteredContent' => $filteredContent,
            'hasFilteredContent' => $hasFilteredContent,
            'filteredWords' => $filteredWords,
        ];

        $this->assertSame($expected, $array);
    }
}
