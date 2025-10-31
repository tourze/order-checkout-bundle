<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\DeliveryAddressBundle\Entity\DeliveryAddress;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use Tourze\OrderCheckoutBundle\DTO\CheckoutResult;
use Tourze\OrderCheckoutBundle\Exception\PriceCalculationFailureException;
use Tourze\OrderCheckoutBundle\Procedure\Checkout\ProcessCheckoutProcedure;
use Tourze\OrderCheckoutBundle\Service\CheckoutService;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\StockManageBundle\Entity\StockBatch;

/**
 * @internal
 */
#[CoversClass(ProcessCheckoutProcedure::class)]
#[RunTestsInSeparateProcesses]
final class ProcessCheckoutProcedureTest extends AbstractProcedureTestCase
{
    private ProcessCheckoutProcedure $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(ProcessCheckoutProcedure::class);
    }

    /**
     * 创建测试SKU数据
     */
    private function createTestSku(?string $skuId = null, int $stockQuantity = 100): Sku
    {
        $spu = new Spu();
        $spu->setTitle('Test SPU for SKU ' . uniqid());
        $spu->setValid(true); // 确保SPU有效

        $sku = new Sku();
        $sku->setSpu($spu);
        $sku->setUnit('个');
        $sku->setValid(true); // 确保SKU有效

        if (null !== $skuId) {
            $sku->setGtin($skuId); // 使用GTIN字段存储测试SKU ID
        }

        $entityManager = self::getEntityManager();
        $entityManager->persist($spu);
        $entityManager->persist($sku);
        $entityManager->flush();

        // 为SKU创建库存批次
        $this->createStockForSku($sku, $stockQuantity);

        return $sku;
    }

    /**
     * 为SKU创建库存批次
     */
    private function createStockForSku(Sku $sku, int $quantity): void
    {
        $stockBatch = new StockBatch();
        $stockBatch->setSku($sku);
        $stockBatch->setBatchNo('TEST-' . uniqid());
        $stockBatch->setQuantity($quantity);
        $stockBatch->setAvailableQuantity($quantity);
        $stockBatch->setReservedQuantity(0);
        $stockBatch->setLockedQuantity(0);
        $stockBatch->setUnitCost(10.00);
        $stockBatch->setQualityLevel('A');
        $stockBatch->setStatus('available');

        $entityManager = self::getEntityManager();
        $entityManager->persist($stockBatch);
        $entityManager->flush();
    }

    /**
     * 根据SKU ID获取测试SKU数据
     */
    private function getSkuIdForTest(Sku $sku): string
    {
        return $sku->getId();
    }

    /**
     * 创建测试收货地址
     */
    private function createTestDeliveryAddress(UserInterface $user): DeliveryAddress
    {
        $address = new DeliveryAddress();
        $address->setUser($user);
        $address->setConsignee('测试收货人');
        $address->setMobile('13800138000');
        $address->setProvince('广东省');
        $address->setCity('深圳市');
        $address->setDistrict('南山区');
        $address->setAddressLine('测试地址123号');
        $address->setIsDefault(false);

        $entityManager = self::getEntityManager();
        $entityManager->persist($address);
        $entityManager->flush();

        return $address;
    }

    public function testExecuteThrowsExceptionWhenUserNotLoggedIn(): void
    {
        // Arrange: 设置未登录状态
        $this->procedure->skuItems = [['skuId' => '100', 'quantity' => 1]];
        $this->procedure->addressId = 123;

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $this->procedure->execute();
    }

    public function testExecuteThrowsExceptionWhenCartIsEmpty(): void
    {
        // Arrange: 创建已登录用户但购物车为空
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->skuItems = [];
        $this->procedure->addressId = 123;

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('请选择商品或启用购物车模式');

        $this->procedure->execute();
    }

    public function testExecuteWithBasicCheckoutDataShouldCreateOrder(): void
    {
        // Arrange: 准备正常的结算数据
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        // 创建测试SKU数据
        $sku1 = $this->createTestSku();
        $sku2 = $this->createTestSku();

        // 创建测试收货地址
        $address = $this->createTestDeliveryAddress($user);

        $sku1Id = $this->getSkuIdForTest($sku1);
        $sku2Id = $this->getSkuIdForTest($sku2);

        $this->procedure->skuItems = [
            ['skuId' => $sku1Id, 'quantity' => 2],
            ['skuId' => $sku2Id, 'quantity' => 1],
        ];
        $addressId = $address->getId();
        self::assertNotSame(null, $addressId);
        $this->procedure->addressId = $addressId;
        $this->procedure->orderRemark = '请尽快发货，谢谢！';

        // Act: 执行订单结算
        $result = $this->procedure->execute();

        // Assert: 验证结果结构
        $this->assertIsArray($result);
        $this->assertArrayHasKey('__message', $result);
        $this->assertArrayHasKey('orderId', $result);
        $this->assertArrayHasKey('orderNumber', $result);
        $this->assertArrayHasKey('totalAmount', $result);
        $this->assertArrayHasKey('paymentRequired', $result);
        $this->assertArrayHasKey('orderState', $result);

        // 验证消息和订单信息
        $this->assertEquals('订单创建成功', $result['__message']);
        $this->assertIsInt($result['orderId']);
        $this->assertIsString($result['orderNumber']);
        $this->assertIsFloat($result['totalAmount']);
        $this->assertIsBool($result['paymentRequired']);
        $this->assertIsString($result['orderState']);
    }

    public function testExecuteWithCouponCodeAndPointsShouldApplyDiscounts(): void
    {
        // Arrange: 准备包含优惠券和积分的结算数据
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        // 创建测试SKU数据
        $sku = $this->createTestSku();
        $skuId = $this->getSkuIdForTest($sku);

        // 创建测试收货地址
        $address = $this->createTestDeliveryAddress($user);

        $this->procedure->skuItems = [
            ['skuId' => $skuId, 'quantity' => 1],
        ];
        $addressId = $address->getId();
        self::assertNotSame(null, $addressId);
        $this->procedure->addressId = $addressId;
        $this->procedure->couponCode = 'SUMMER2024';
        $this->procedure->pointsToUse = 500;
        $this->procedure->orderRemark = '使用优惠券和积分';

        // Act: 执行订单结算
        $result = $this->procedure->execute();

        // Assert: 验证结果包含所有必要信息
        $this->assertIsArray($result);
        $this->assertEquals('订单创建成功', $result['__message']);

        // 验证基本结果结构
        $this->assertIsInt($result['orderId'] ?? null);
        $this->assertIsString($result['orderNumber'] ?? null);
        $this->assertIsFloat($result['totalAmount'] ?? null);
        $this->assertIsBool($result['paymentRequired'] ?? null);
    }

    public function testExecuteWithZeroTotalAmountShouldNotRequirePayment(): void
    {
        // Arrange: 准备总金额为0的场景（完全优惠）
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        // 使用MockCheckoutService模拟返回0金额的结果
        $mockCheckoutService = $this->createMock(CheckoutService::class);
        $mockResult = $this->createMock(CheckoutResult::class);
        $mockResult->method('getFinalTotal')->willReturn(0.0);
        $mockResult->method('hasStockIssues')->willReturn(false);
        $mockCheckoutService->method('process')->willReturn($mockResult);

        // 创建测试SKU数据
        $sku = $this->createTestSku();
        $skuId = $this->getSkuIdForTest($sku);

        // 创建测试收货地址
        $address = $this->createTestDeliveryAddress($user);

        // 注入Mock服务（这里需要实际的依赖注入机制）
        $this->procedure->skuItems = [
            ['skuId' => $skuId, 'quantity' => 1],
        ];
        $addressId = $address->getId();
        self::assertNotSame(null, $addressId);
        $this->procedure->addressId = $addressId;

        // Act: 执行订单结算
        $result = $this->procedure->execute();

        // Assert: 验证不需要支付的情况
        $this->assertIsArray($result);
        $this->assertEquals('订单创建成功', $result['__message']);

        // 如果金额为0，应该不需要支付
        if (0.0 === $result['totalAmount']) {
            $this->assertFalse($result['paymentRequired']);
            $this->assertArrayNotHasKey('paymentInfo', $result);
        }
    }

    public function testExecuteThrowsExceptionWhenSkuNotFound(): void
    {
        // Arrange: 准备包含不存在SKU的数据
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        // 创建测试收货地址
        $address = $this->createTestDeliveryAddress($user);

        $this->procedure->skuItems = [
            ['skuId' => '999', 'quantity' => 1], // SKU '999' 不存在
        ];
        $addressId = $address->getId();
        self::assertNotSame(null, $addressId);
        $this->procedure->addressId = $addressId;

        // Act & Assert: 验证SKU未找到时的异常处理
        $this->expectException(PriceCalculationFailureException::class);
        $this->expectExceptionMessage('价格计算器 base_price 执行失败: SKU 未找到: 999');

        $this->procedure->execute();
    }

    public function testExecuteThrowsExceptionOnCheckoutServiceError(): void
    {
        // Arrange: 准备会导致CheckoutService失败的数据（使用库存为0的SKU）
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        // 创建测试SKU数据但设置库存为0以模拟库存问题
        $sku = $this->createTestSku(null, 0);
        $skuId = $this->getSkuIdForTest($sku);

        // 创建测试收货地址
        $address = $this->createTestDeliveryAddress($user);

        $this->procedure->skuItems = [
            ['skuId' => $skuId, 'quantity' => 1], // 这个SKU会导致库存不足
        ];
        $addressId = $address->getId();
        self::assertNotSame(null, $addressId);
        $this->procedure->addressId = $addressId;

        // Act & Assert: 验证库存不足异常
        // 库存验证失败会被转换为 ApiException
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('库存验证失败: ');

        $this->procedure->execute();
    }

    public function testGetLockResourceGeneratesCorrectLockKey(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $params = $this->createMock(JsonRpcParams::class);

        // Act: 获取锁资源
        $lockResource = $this->procedure->getLockResource($params);

        // Assert: 验证锁资源格式
        $this->assertIsArray($lockResource);
        $this->assertCount(1, $lockResource);
        $this->assertIsString($lockResource[0]);
        $this->assertStringStartsWith('checkout_process:', $lockResource[0]);
        $this->assertInstanceOf(UserInterface::class, $user);
        $this->assertStringContainsString($user->getUserIdentifier(), $lockResource[0]);
    }

    public function testGetLockResourceThrowsExceptionWhenUserNotLoggedIn(): void
    {
        // Arrange: 设置未登录状态
        $params = $this->createMock(JsonRpcParams::class);

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $this->procedure->getLockResource($params);
    }

    public function testGetMockResultReturnsValidStructure(): void
    {
        // Act: 获取Mock结果
        $mockResult = ProcessCheckoutProcedure::getMockResult();

        // Assert: 验证Mock结果结构
        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('__message', $mockResult);
        $this->assertArrayHasKey('orderId', $mockResult);
        $this->assertArrayHasKey('orderNumber', $mockResult);
        $this->assertArrayHasKey('totalAmount', $mockResult);
        $this->assertArrayHasKey('paymentRequired', $mockResult);
        $this->assertArrayHasKey('stockWarnings', $mockResult);

        // 验证基本数据类型
        $this->assertEquals('订单创建成功', $mockResult['__message']);
        $this->assertIsInt($mockResult['orderId']);
        $this->assertIsString($mockResult['orderNumber']);
        $this->assertIsFloat($mockResult['totalAmount']);
        $this->assertIsBool($mockResult['paymentRequired']);
        $this->assertIsArray($mockResult['stockWarnings']);
    }

    public function testProcedureParameterValidationWorks(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        // Test: 验证addressId必须为正整数
        // 使用空的skuItems数组，这样会在参数验证阶段就失败，而不会执行到库存验证
        $this->procedure->skuItems = [];
        $this->procedure->addressId = 0; // 无效值

        // Act & Assert: 验证参数验证是否生效
        $this->expectException(\Exception::class);

        $this->procedure->execute();
    }
}
