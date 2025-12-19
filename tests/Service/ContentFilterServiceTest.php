<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\OrderCheckoutBundle\DTO\FilterResult;
use Tourze\OrderCheckoutBundle\Exception\ContentFilterException;
use Tourze\OrderCheckoutBundle\Service\ContentFilterService;

/**
 * @internal
 */
#[CoversClass(ContentFilterService::class)]
#[RunTestsInSeparateProcesses]
final class ContentFilterServiceTest extends AbstractIntegrationTestCase
{
    private ContentFilterService $contentFilterService;

    protected function onSetUp(): void
    {
        $this->contentFilterService = self::getService(ContentFilterService::class);
    }

    public function testFilterContentWithNoSensitiveWords(): void
    {
        $content = '这是一个正常的备注内容';
        $result = $this->contentFilterService->filterContent($content);

        $this->assertInstanceOf(FilterResult::class, $result);
        $this->assertEquals($content, $result->originalContent);
        $this->assertEquals($content, $result->filteredContent);
        $this->assertFalse($result->hasFilteredContent);
        $this->assertEmpty($result->filteredWords);
    }

    public function testFilterContentWithSensitiveWords(): void
    {
        $content = '这里有色情和暴力内容';
        $result = $this->contentFilterService->filterContent($content);

        $this->assertEquals('这里有色情和暴力内容', $result->originalContent);
        $this->assertEquals('这里有**和**内容', $result->filteredContent);
        $this->assertTrue($result->hasFilteredContent);
        $this->assertContains('色情', $result->filteredWords);
        $this->assertContains('暴力', $result->filteredWords);
    }

    public function testFilterContentWithEnglishSensitiveWords(): void
    {
        $content = 'This is fuck shit content';
        $result = $this->contentFilterService->filterContent($content);

        $this->assertEquals('This is fuck shit content', $result->originalContent);
        $this->assertEquals('This is **** **** content', $result->filteredContent);
        $this->assertTrue($result->hasFilteredContent);
        $this->assertContains('fuck', $result->filteredWords);
        $this->assertContains('shit', $result->filteredWords);
    }

    public function testFilterContentWithEmptyString(): void
    {
        $result = $this->contentFilterService->filterContent('');

        $this->assertEquals('', $result->originalContent);
        $this->assertEquals('', $result->filteredContent);
        $this->assertFalse($result->hasFilteredContent);
        $this->assertEmpty($result->filteredWords);
    }

    public function testIsContentAllowedWithCleanContent(): void
    {
        $content = '这是一个正常的内容';
        $result = $this->contentFilterService->isContentAllowed($content);

        $this->assertTrue($result);
    }

    public function testIsContentAllowedWithSensitiveContent(): void
    {
        $content = '这里有色情内容';
        $result = $this->contentFilterService->isContentAllowed($content);

        $this->assertFalse($result);
    }

    public function testValidateEmojiContentWithValidUtf8(): void
    {
        $content = '这是包含emoji的内容😊🎉👍';
        $result = $this->contentFilterService->validateEmojiContent($content);

        $this->assertTrue($result);
    }

    public function testValidateEmojiContentWithInvalidUtf8(): void
    {
        $content = "Invalid UTF-8: \xFF\xFE";
        $result = $this->contentFilterService->validateEmojiContent($content);

        $this->assertFalse($result);
    }

    public function testSanitizeRemarkWithValidContent(): void
    {
        $content = '  这是一个正常的备注内容😊  ';
        $result = $this->contentFilterService->sanitizeRemark($content);

        $this->assertEquals('这是一个正常的备注内容😊', $result);
    }

    public function testSanitizeRemarkWithTooLongContent(): void
    {
        $content = str_repeat('长', 201);

        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('备注内容不能超过200个字符');

        $this->contentFilterService->sanitizeRemark($content);
    }

    public function testSanitizeRemarkWithInvalidEncoding(): void
    {
        $content = "Invalid UTF-8: \xFF\xFE";

        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('备注内容包含无效字符');

        $this->contentFilterService->sanitizeRemark($content);
    }

    public function testSanitizeRemarkWithExactly200Characters(): void
    {
        $content = str_repeat('正', 200);
        $result = $this->contentFilterService->sanitizeRemark($content);

        $this->assertEquals($content, $result);
    }

    public function testFilterContentWithMultipleSameWords(): void
    {
        $content = '色情色情暴力';
        $result = $this->contentFilterService->filterContent($content);

        $this->assertEquals('色情色情暴力', $result->originalContent);
        $this->assertEquals('******', $result->filteredContent);
        $this->assertTrue($result->hasFilteredContent);
        $this->assertContains('色情', $result->filteredWords);
        $this->assertContains('暴力', $result->filteredWords);
    }

    public function testFilterContentWithMixedLanguage(): void
    {
        $content = '这里有fuck和色情内容';
        $result = $this->contentFilterService->filterContent($content);

        $this->assertEquals('这里有fuck和色情内容', $result->originalContent);
        $this->assertEquals('这里有****和**内容', $result->filteredContent);
        $this->assertTrue($result->hasFilteredContent);
        $this->assertContains('fuck', $result->filteredWords);
        $this->assertContains('色情', $result->filteredWords);
    }
}
