<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\OrderCheckoutBundle\Param\Checkout\ValidateStockParam;
use Tourze\OrderCheckoutBundle\Procedure\Checkout\ValidateStockProcedure;
use Tourze\PHPUnitJsonRPC\AbstractProcedureTestCase;

/**
 * @internal
 */
#[CoversClass(ValidateStockProcedure::class)]
#[RunTestsInSeparateProcesses]
final class ValidateStockProcedureTest extends AbstractProcedureTestCase
{
    private ValidateStockProcedure $procedure;

    private function createJsonRpcRequest(): JsonRpcRequest
    {
        $request = new JsonRpcRequest();
        $request->setMethod('test');

        return $request;
    }

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(ValidateStockProcedure::class);
    }

    public function testExecuteThrowsExceptionWhenUserNotLoggedIn(): void
    {
        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $param = new ValidateStockParam();
        $this->procedure->execute($param);
    }

    public function testGetCacheKeyGeneratesCorrectKey(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $request = $this->createJsonRpcRequest();

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
        $request = $this->createJsonRpcRequest();

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $this->procedure->getCacheKey($request);
    }

    public function testGetCacheDurationReturns30Seconds(): void
    {
        // Arrange: 创建请求
        $request = $this->createJsonRpcRequest();

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

        $request = $this->createJsonRpcRequest();

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
        $request = $this->createJsonRpcRequest();

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        iterator_to_array($this->procedure->getCacheTags($request));
    }

}
