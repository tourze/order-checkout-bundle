<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use Tourze\OrderCheckoutBundle\Procedure\Checkout\ValidateStockProcedure;

/**
 * @internal
 */
#[CoversClass(ValidateStockProcedure::class)]
#[RunTestsInSeparateProcesses]
final class ValidateStockProcedureTest extends AbstractProcedureTestCase
{
    private ValidateStockProcedure $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(ValidateStockProcedure::class);
    }

    public function testExecuteThrowsExceptionWhenUserNotLoggedIn(): void
    {
        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $this->procedure->execute();
    }

    public function testGetCacheKeyGeneratesCorrectKey(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $request = $this->createMock(JsonRpcRequest::class);

        // Act: 生成缓存键
        $cacheKey = $this->procedure->getCacheKey($request);

        // Assert: 验证缓存键格式
        // UserInterface 有 getUserIdentifier() 方法
        $this->assertInstanceOf(UserInterface::class, $user);
        /** @var UserInterface $user */
        $expectedKey = 'stock_validation:' . $user->getUserIdentifier();
        $this->assertEquals($expectedKey, $cacheKey);
    }

    public function testGetCacheKeyThrowsExceptionWhenUserNotLoggedIn(): void
    {
        // Arrange: 设置未登录状态
        $request = $this->createMock(JsonRpcRequest::class);

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $this->procedure->getCacheKey($request);
    }

    public function testGetCacheDurationReturns30Seconds(): void
    {
        // Arrange: 创建请求
        $request = $this->createMock(JsonRpcRequest::class);

        // Act: 获取缓存时间
        $duration = $this->procedure->getCacheDuration($request);

        // Assert: 验证缓存时间
        $this->assertEquals(30, $duration);
    }

    public function testGetCacheTagsIncludesRequiredTags(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $request = $this->createMock(JsonRpcRequest::class);

        // Act: 获取缓存标签
        $tags = iterator_to_array($this->procedure->getCacheTags($request));

        // Assert: 验证缓存标签
        $this->assertContains('stock_validation', $tags);
        // UserInterface 有 getUserIdentifier() 方法
        $this->assertInstanceOf(UserInterface::class, $user);
        /** @var UserInterface $user */
        $this->assertContains('cart_user_' . $user->getUserIdentifier(), $tags);
    }

    public function testGetCacheTagsThrowsExceptionWhenUserNotLoggedIn(): void
    {
        // Arrange: 设置未登录状态
        $request = $this->createMock(JsonRpcRequest::class);

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        iterator_to_array($this->procedure->getCacheTags($request));
    }

    public function testGetMockResultReturnsValidStructure(): void
    {
        // Act: 获取Mock结果
        $mockResult = ValidateStockProcedure::getMockResult();

        // Assert: 验证Mock结果结构
        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('isValid', $mockResult);
        $this->assertArrayHasKey('hasWarnings', $mockResult);
        $this->assertArrayHasKey('errors', $mockResult);
        $this->assertArrayHasKey('warnings', $mockResult);
        $this->assertArrayHasKey('details', $mockResult);
        $this->assertArrayHasKey('summary', $mockResult);

        // 验证基本数据类型
        $this->assertIsBool($mockResult['isValid']);
        $this->assertIsBool($mockResult['hasWarnings']);
        $this->assertIsArray($mockResult['errors']);
        $this->assertIsArray($mockResult['warnings']);
        $this->assertIsArray($mockResult['details']);
        $this->assertIsArray($mockResult['summary']);

        // 验证摘要信息结构
        $summary = $mockResult['summary'];
        $this->assertArrayHasKey('totalItems', $summary);
        $this->assertArrayHasKey('validItems', $summary);
        $this->assertArrayHasKey('invalidItems', $summary);
        $this->assertArrayHasKey('warningItems', $summary);

        // 验证摘要数据类型
        $this->assertIsInt($summary['totalItems']);
        $this->assertIsInt($summary['validItems']);
        $this->assertIsInt($summary['invalidItems']);
        $this->assertIsInt($summary['warningItems']);
    }

    public function testMockResultShowsRealisticStockScenario(): void
    {
        // Act: 获取Mock结果
        $mockResult = ValidateStockProcedure::getMockResult();

        // Assert: 验证Mock结果反映真实的库存验证场景
        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('isValid', $mockResult);
        $this->assertFalse($mockResult['isValid']); // 有库存问题
        $this->assertTrue($mockResult['hasWarnings']); // 有警告
        $this->assertNotEmpty($mockResult['errors']); // 有错误
        $this->assertNotEmpty($mockResult['warnings']); // 有警告

        // 验证错误和警告的内容格式
        $this->assertIsArray($mockResult['errors']);
        foreach ($mockResult['errors'] as $error) {
            $this->assertIsString($error);
            $this->assertStringContainsString('库存不足', $error);
        }

        $this->assertIsArray($mockResult['warnings']);
        foreach ($mockResult['warnings'] as $warning) {
            $this->assertIsString($warning);
            $this->assertStringContainsString('库存较少', $warning);
        }

        // 验证详情信息的结构
        $this->assertIsArray($mockResult['details']);
        foreach ($mockResult['details'] as $detail) {
            $this->assertIsArray($detail);
            $this->assertArrayHasKey('sku_code', $detail);
            $this->assertArrayHasKey('sku_name', $detail);
            $this->assertArrayHasKey('requested_quantity', $detail);
            $this->assertArrayHasKey('available_quantity', $detail);
        }
    }
}
