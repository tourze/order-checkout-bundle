<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use Tourze\OrderCheckoutBundle\Procedure\Checkout\CalculatePriceProcedure;

/**
 * @internal
 */
#[CoversClass(CalculatePriceProcedure::class)]
#[RunTestsInSeparateProcesses]
final class CalculatePriceProcedureTest extends AbstractProcedureTestCase
{
    private CalculatePriceProcedure $procedure;

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
        // Arrange: 设置未登录用户 - 不设置认证用户
        $this->procedure->cartItems = [['id' => 1, 'skuId' => 100, 'quantity' => 1]];

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $this->procedure->execute();
    }

    public function testExecuteThrowsExceptionWhenCartIsEmpty(): void
    {
        // Arrange: 设置已登录用户但购物车为空
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->cartItems = [];

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('购物车中没有选中的商品');

        $this->procedure->execute();
    }

    public function testExecuteThrowsExceptionWhenSkuNotFound(): void
    {
        // Arrange: 准备正常数据但使用不存在的 SKU
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->cartItems = [['id' => 1, 'skuId' => 100, 'quantity' => 2, 'price' => 50.0]];
        $this->procedure->addressId = 123;
        $this->procedure->couponCode = 'TEST10';
        $this->procedure->pointsToUse = 100;

        // Act & Assert: 验证 SKU 不存在时的异常处理
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('价格计算器 base_price 执行失败: SKU 未找到: 100');

        $this->procedure->execute();
    }

    public function testGetMockResultProvidesValidStructure(): void
    {
        // Act: 获取 Mock 结果
        $result = CalculatePriceProcedure::getMockResult();

        // Assert: 验证结果结构符合 execute() 方法的预期格式
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pricing', $result);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertArrayHasKey('appliedPromotions', $result);
        $this->assertArrayHasKey('items', $result);

        // 验证 pricing 结构存在必要字段
        $pricing = $result['pricing'];
        $this->assertIsArray($pricing);
        $this->assertArrayHasKey('basePrice', $pricing);
        $this->assertArrayHasKey('finalPrice', $pricing);
        $this->assertArrayHasKey('savings', $pricing);
        $this->assertIsFloat($pricing['basePrice']);
        $this->assertIsFloat($pricing['finalPrice']);
        $this->assertIsFloat($pricing['savings']);

        // 验证 items 结构
        $this->assertNotEmpty($result['items']);
        $this->assertIsArray($result['items']);
        $item = $result['items'][0];
        $this->assertIsArray($item);
        $this->assertArrayHasKey('skuId', $item);
        $this->assertArrayHasKey('quantity', $item);
        $this->assertEquals(100, $item['skuId']);
    }

    public function testGetCacheKeyGeneratesCorrectKey(): void
    {
        // Arrange: 准备用户和参数
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->addressId = 456;
        $this->procedure->couponCode = 'TEST20';
        $this->procedure->pointsToUse = 200;

        $request = $this->createMock(JsonRpcRequest::class);

        // Act: 生成缓存键
        $cacheKey = $this->procedure->getCacheKey($request);

        // Assert: 验证缓存键格式
        $this->assertInstanceOf(UserInterface::class, $user);
        $expectedKey = 'price_calc:' . $user->getUserIdentifier() . ':456:TEST20:200';
        $this->assertEquals($expectedKey, $cacheKey);
    }

    public function testGetCacheKeyWithNullValues(): void
    {
        // Arrange: 准备空值参数
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->addressId = null;
        $this->procedure->couponCode = null;
        $this->procedure->pointsToUse = 0;

        $request = $this->createMock(JsonRpcRequest::class);

        // Act: 生成缓存键
        $cacheKey = $this->procedure->getCacheKey($request);

        // Assert: 验证缓存键处理空值
        $this->assertInstanceOf(UserInterface::class, $user);
        $expectedKey = 'price_calc:' . $user->getUserIdentifier() . ':no_addr:no_coupon:0';
        $this->assertEquals($expectedKey, $cacheKey);
    }

    public function testGetCacheDurationReturns120Seconds(): void
    {
        // Arrange: 创建请求
        $request = $this->createMock(JsonRpcRequest::class);

        // Act: 获取缓存时间
        $duration = $this->procedure->getCacheDuration($request);

        // Assert: 验证缓存时间
        $this->assertEquals(120, $duration);
    }

    public function testGetCacheTagsIncludesBasicTags(): void
    {
        // Arrange: 准备用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);
        $this->procedure->couponCode = null;

        $request = $this->createMock(JsonRpcRequest::class);

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
        // Arrange: 准备用户和优惠券
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);
        $this->procedure->couponCode = 'SUMMER2024';

        $request = $this->createMock(JsonRpcRequest::class);

        // Act: 获取缓存标签
        $tags = iterator_to_array($this->procedure->getCacheTags($request));

        // Assert: 验证优惠券标签
        $this->assertContains('coupon_SUMMER2024', $tags);
    }

    public function testGetMockResultReturnsValidStructure(): void
    {
        // Act: 获取Mock结果
        $mockResult = CalculatePriceProcedure::getMockResult();

        // Assert: 验证Mock结果结构
        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('pricing', $mockResult);
        $this->assertArrayHasKey('breakdown', $mockResult);
        $this->assertArrayHasKey('appliedPromotions', $mockResult);
        $this->assertArrayHasKey('items', $mockResult);

        // 验证pricing数据
        $pricing = $mockResult['pricing'];
        $this->assertIsArray($pricing);
        $this->assertIsFloat($pricing['finalPrice']);
        $this->assertIsFloat($pricing['savings']);
    }
}
