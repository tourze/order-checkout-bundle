<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\OrderCheckoutBundle\Param\Checkout\CalculatePriceParam;
use Tourze\OrderCheckoutBundle\Procedure\Checkout\CalculatePriceProcedure;
use Tourze\PHPUnitJsonRPC\AbstractProcedureTestCase;

/**
 * @internal
 */
#[CoversClass(CalculatePriceProcedure::class)]
#[RunTestsInSeparateProcesses]
final class CalculatePriceProcedureTest extends AbstractProcedureTestCase
{
    private CalculatePriceProcedure $procedure;

    /**
     * @param array<string, mixed> $params
     */
    private function createJsonRpcRequest(array $params = []): JsonRpcRequest
    {
        $request = new JsonRpcRequest();
        $request->setMethod('test');
        $request->setParams(new JsonRpcParams($params));

        return $request;
    }

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(CalculatePriceProcedure::class);
    }

    public function testProcedureCanBeInstantiated(): void
    {
        // Assert: 验证Procedure可以被实例化
        $this->assertInstanceOf(CalculatePriceProcedure::class, $this->procedure);
    }

    public function testExecuteThrowsExceptionWhenUserNotLoggedIn(): void
    {
        // Arrange: 设置未登录用户 - 不设置认证用户，使用 Param 对象
        $param = new CalculatePriceParam(
            cartItems: [['id' => 1, 'skuId' => 100, 'quantity' => 1]]
        );

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $this->procedure->execute($param);
    }

    public function testExecuteThrowsExceptionWhenCartIsEmpty(): void
    {
        // Arrange: 设置已登录用户但购物车为空
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $param = new CalculatePriceParam(cartItems: []);

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('购物车中没有选中的商品');

        $this->procedure->execute($param);
    }

    /**
     * TODO: 此测试需要在集成测试中实现
     * 原因：SKU 不存在的场景需要真实的数据库环境和 SkuLoader 实现
     * 单元测试层面无法有效 mock 已初始化的服务容器
     *
     * 建议实现位置：packages/order-checkout-bundle/tests/Integration/Procedure/Checkout/CalculatePriceProcedureIntegrationTest.php
     * 测试步骤：
     * 1. 创建测试用户
     * 2. 准备不存在的 skuId（如 999999）
     * 3. 调用 procedure->execute()
     * 4. 断言抛出 ApiException，消息包含"SKU 未找到"
     */

    public function testGetMockResultProvidesValidStructure(): void
    {
        // Act: 获取 Mock 结果
        $result = ArrayResult::getMockResult();

        // Assert: 验证 Mock 结果是 ArrayResult 实例
        $this->assertInstanceOf(ArrayResult::class, $result);
        $this->assertArrayHasKey('example_key', $result);
        $this->assertEquals('example_value', $result['example_key']);
    }

    public function testGetCacheKeyGeneratesCorrectKey(): void
    {
        // Arrange: 准备用户和参数（通过 JsonRpcRequest 参数传递）
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $request = $this->createJsonRpcRequest([
            'cartItems' => [['id' => 1, 'skuId' => 100, 'quantity' => 2]],
            'addressId' => 456,
            'couponCode' => 'TEST20',
            'pointsToUse' => 200,
        ]);

        // Act: 生成缓存键
        $cacheKey = $this->procedure->getCacheKey($request);

        // Assert: 验证缓存键格式包含必要组成部分
        $this->assertInstanceOf(UserInterface::class, $user);
        $this->assertStringStartsWith('price_calc:' . $user->getUserIdentifier() . ':', $cacheKey);
        $this->assertStringContainsString(':456:', $cacheKey);
        $this->assertStringContainsString(':TEST20:', $cacheKey);
        $this->assertStringContainsString(':200:', $cacheKey);
        $this->assertStringEndsWith(':CASH_ONLY:0', $cacheKey);
    }

    public function testGetCacheKeyWithNullValues(): void
    {
        // Arrange: 准备空值参数（通过 JsonRpcRequest 参数传递）
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $request = $this->createJsonRpcRequest([
            'cartItems' => [],
            'addressId' => null,
            'couponCode' => null,
            'pointsToUse' => 0,
        ]);

        // Act: 生成缓存键
        $cacheKey = $this->procedure->getCacheKey($request);

        // Assert: 验证缓存键处理空值，包含购物车哈希
        $this->assertInstanceOf(UserInterface::class, $user);
        $this->assertStringStartsWith('price_calc:' . $user->getUserIdentifier() . ':', $cacheKey);
        $this->assertStringContainsString(':no_addr:', $cacheKey);
        $this->assertStringContainsString(':auto_apply:', $cacheKey);
        $this->assertStringEndsWith(':CASH_ONLY:0', $cacheKey);
    }

    public function testGetCacheDurationReturns120Seconds(): void
    {
        // Arrange: 设置有优惠券的场景（通过 JsonRpcRequest 参数传递）
        $request = $this->createJsonRpcRequest(['couponCode' => 'TEST20']);

        // Act: 获取缓存时间
        $duration = $this->procedure->getCacheDuration($request);

        // Assert: 验证有优惠券时缓存时间为120秒
        $this->assertEquals(120, $duration);
    }

    public function testGetCacheDurationReturns60SecondsForAutoCoupon(): void
    {
        // Arrange: 设置无优惠券（自动应用）场景
        $request = $this->createJsonRpcRequest(['couponCode' => null]);

        // Act: 获取缓存时间
        $duration = $this->procedure->getCacheDuration($request);

        // Assert: 验证无优惠券时缓存时间为60秒
        $this->assertEquals(60, $duration);
    }

    public function testGetCacheTagsIncludesBasicTags(): void
    {
        // Arrange: 准备用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $request = $this->createJsonRpcRequest(['couponCode' => null]);

        // Act: 获取缓存标签
        $tags = iterator_to_array($this->procedure->getCacheTags($request));

        // Assert: 验证基本标签
        $this->assertContains('checkout', $tags);
        $this->assertContains('price_calculation', $tags);
        $this->assertInstanceOf(UserInterface::class, $user);
        $this->assertContains('cart_user_' . $user->getUserIdentifier(), $tags);
    }

    public function testGetCacheTagsIncludesCouponTag(): void
    {
        // Arrange: 准备用户和优惠券（通过 JsonRpcRequest 参数传递）
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $request = $this->createJsonRpcRequest(['couponCode' => 'SUMMER2024']);

        // Act: 获取缓存标签
        $tags = iterator_to_array($this->procedure->getCacheTags($request));

        // Assert: 验证优惠券标签
        $this->assertContains('coupon_SUMMER2024', $tags);
    }

    public function testGetMockResultReturnsValidStructure(): void
    {
        // Act: 获取Mock结果
        $mockResult = ArrayResult::getMockResult();

        // Assert: 验证Mock结果是 ArrayResult 实例
        $this->assertInstanceOf(ArrayResult::class, $mockResult);
        $this->assertArrayHasKey('example_key', $mockResult);
        $this->assertEquals('example_value', $mockResult['example_key']);
    }
}
