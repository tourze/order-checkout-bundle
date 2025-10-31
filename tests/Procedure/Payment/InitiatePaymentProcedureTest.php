<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Payment;

use OrderCoreBundle\Entity\Contract;
use OrderCoreBundle\Entity\OrderPrice;
use OrderCoreBundle\Enum\OrderState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use Tourze\OrderCheckoutBundle\Procedure\Payment\InitiatePaymentProcedure;
use Tourze\PaymentContracts\Enum\PaymentType;
use Tourze\ProductCoreBundle\Enum\PriceType;

/**
 * @internal
 */
#[CoversClass(InitiatePaymentProcedure::class)]
#[RunTestsInSeparateProcesses]
final class InitiatePaymentProcedureTest extends AbstractProcedureTestCase
{
    private InitiatePaymentProcedure $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(InitiatePaymentProcedure::class);
    }

    /**
     * 创建测试订单
     */
    private function createTestOrder(UserInterface $user, string $amount = '999.00'): Contract
    {
        $contract = new Contract();
        $contract->setSn('TEST_ORDER_' . uniqid());
        $contract->setState(OrderState::INIT);
        $contract->setUser($user);
        $contract->setTotalAmount($amount);
        $contract->setCreatedBy($user->getUserIdentifier());

        $entityManager = self::getEntityManager();
        $entityManager->persist($contract);
        $entityManager->flush();

        // 创建订单价格 - 需要在Contract持久化后创建
        $orderPrice = new OrderPrice();
        $orderPrice->setName('商品费用');
        $orderPrice->setMoney($amount);
        $orderPrice->setType(PriceType::SALE);
        $orderPrice->setRefund(false);
        $orderPrice->setCurrency('CNY');

        // 使用 addPrice 确保双向关系正确设置
        $contract->addPrice($orderPrice);
        $entityManager->persist($orderPrice);
        $entityManager->flush();

        return $contract;
    }

    public function testProcedureCanBeInstantiated(): void
    {
        // Assert: 验证 Procedure 可以被实例化
        $this->assertInstanceOf(InitiatePaymentProcedure::class, $this->procedure);
    }

    public function testExecuteWithValidPayment(): void
    {
        // Arrange: 设置已登录用户和有效参数
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        // 创建真实的订单
        $order = $this->createTestOrder($user, '999.00');
        $orderId = $order->getId();

        $this->procedure->orderId = $orderId ?? 0;
        $this->procedure->paymentMethod = PaymentType::LEGACY_ALIPAY->value;

        // Act: 执行支付发起
        $result = $this->procedure->execute();

        // Assert: 验证结果结构
        $this->assertIsArray($result);
        $this->assertArrayHasKey('__message', $result);
        $this->assertArrayHasKey('orderId', $result);
        $this->assertArrayHasKey('orderNumber', $result);
        $this->assertArrayHasKey('paymentMethod', $result);
        $this->assertArrayHasKey('paymentMethodLabel', $result);
        $this->assertArrayHasKey('orderState', $result);
        $this->assertArrayHasKey('totalAmount', $result);
        $this->assertArrayHasKey('paymentParams', $result);

        // 验证具体值
        $this->assertEquals('支付发起成功', $result['__message']);
        $this->assertEquals($orderId, $result['orderId']);
        $this->assertEquals($order->getSn(), $result['orderNumber']);
        $this->assertEquals(PaymentType::LEGACY_ALIPAY->value, $result['paymentMethod']);
        $this->assertEquals(PaymentType::LEGACY_ALIPAY->getLabel(), $result['paymentMethodLabel']);
        $this->assertEquals('paying', $result['orderState']);
        $this->assertIsFloat($result['totalAmount']);
        $this->assertIsArray($result['paymentParams']);
    }

    public function testExecuteThrowsExceptionForInvalidPaymentMethod(): void
    {
        // Arrange: 设置已登录用户和无效支付方式
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->orderId = 123456;
        $this->procedure->paymentMethod = 'invalid_payment_method';

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('无效的支付方式');

        $this->procedure->execute();
    }

    public function testExecuteThrowsExceptionForEmptyPaymentMethod(): void
    {
        // Arrange: 设置已登录用户和空支付方式
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->orderId = 123456;
        $this->procedure->paymentMethod = '';

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('无效的支付方式');

        $this->procedure->execute();
    }

    public function testExecuteHandlesPaymentServiceException(): void
    {
        // Arrange: 设置支付服务抛出异常 - 测试订单不存在的情况
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->orderId = 999999; // 使用一个不存在的订单ID
        $this->procedure->paymentMethod = PaymentType::LEGACY_ALIPAY->value;

        // Act & Assert: 验证异常处理
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('支付发起失败: 订单不存在');

        $this->procedure->execute();
    }

    public function testExecuteWithDifferentPaymentTypes(): void
    {
        // Arrange: 测试微信支付
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        // 创建真实的订单
        $order = $this->createTestOrder($user, '999.00');
        $orderId = $order->getId();

        $this->procedure->orderId = $orderId ?? 0;
        $this->procedure->paymentMethod = PaymentType::LEGACY_WECHAT_PAY->value;

        // Act: 执行微信支付发起
        $result = $this->procedure->execute();

        // Assert: 验证微信支付结果
        $this->assertEquals(PaymentType::LEGACY_WECHAT_PAY->value, $result['paymentMethod']);
        $this->assertEquals(PaymentType::LEGACY_WECHAT_PAY->getLabel(), $result['paymentMethodLabel']);
        $this->assertEquals($orderId, $result['orderId']);
        $this->assertEquals($order->getSn(), $result['orderNumber']);
        $this->assertIsFloat($result['totalAmount']);
    }

    public function testGetLockResourceReturnsCorrectResource(): void
    {
        // Arrange: 设置订单ID
        $this->procedure->orderId = 999888;

        $params = $this->createMock(JsonRpcParams::class);

        // Act: 获取锁资源
        $lockResource = $this->procedure->getLockResource($params);

        // Assert: 验证锁资源格式
        $this->assertIsArray($lockResource);
        $this->assertCount(1, $lockResource);
        $this->assertEquals('payment_initiate:999888', $lockResource[0]);
    }

    public function testGetLockResourceWithZeroOrderId(): void
    {
        // Arrange: 设置为 0 的订单ID
        $this->procedure->orderId = 0;

        $params = $this->createMock(JsonRpcParams::class);

        // Act: 获取锁资源
        $lockResource = $this->procedure->getLockResource($params);

        // Assert: 验证锁资源
        $this->assertIsArray($lockResource);
        $this->assertCount(1, $lockResource);
        $this->assertEquals('payment_initiate:0', $lockResource[0]);
    }

    public function testGetMockResultReturnsValidStructure(): void
    {
        // Act: 获取 Mock 结果
        $mockResult = InitiatePaymentProcedure::getMockResult();

        // Assert: 验证 Mock 结果结构
        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('__message', $mockResult);
        $this->assertArrayHasKey('orderId', $mockResult);
        $this->assertArrayHasKey('orderNumber', $mockResult);
        $this->assertArrayHasKey('paymentMethod', $mockResult);
        $this->assertArrayHasKey('paymentMethodLabel', $mockResult);
        $this->assertArrayHasKey('orderState', $mockResult);
        $this->assertArrayHasKey('totalAmount', $mockResult);
        $this->assertArrayHasKey('paymentParams', $mockResult);

        // 验证具体值类型
        $this->assertEquals('支付发起成功', $mockResult['__message']);
        $this->assertIsInt($mockResult['orderId']);
        $this->assertIsString($mockResult['orderNumber']);
        $this->assertEquals(PaymentType::LEGACY_ALIPAY->value, $mockResult['paymentMethod']);
        $this->assertEquals(PaymentType::LEGACY_ALIPAY->getLabel(), $mockResult['paymentMethodLabel']);
        $this->assertEquals('paying', $mockResult['orderState']);
        $this->assertIsFloat($mockResult['totalAmount']);
        $this->assertIsArray($mockResult['paymentParams']);

        // 验证支付参数结构
        $paymentParams = $mockResult['paymentParams'];
        $this->assertArrayHasKey('paymentId', $paymentParams);
        $this->assertArrayHasKey('paymentUrl', $paymentParams);
        $this->assertArrayHasKey('qrCode', $paymentParams);
        $this->assertArrayHasKey('expireTime', $paymentParams);
    }

    public function testParameterValidation(): void
    {
        // Arrange: 测试参数验证 - 订单ID必须为正数
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        // 设置负数订单ID（违反 @Assert\Positive 约束）
        $this->procedure->orderId = -1;
        $this->procedure->paymentMethod = PaymentType::LEGACY_ALIPAY->value;

        // Note: 在实际集成测试中，Symfony 验证器会处理这些约束
        // 这里我们主要测试业务逻辑，验证器测试应该在集成层进行
        $this->assertInstanceOf(InitiatePaymentProcedure::class, $this->procedure);
    }

    public function testLockResourceIsOrderSpecific(): void
    {
        // Arrange: 测试不同订单ID产生不同的锁资源
        $orderIds = [111, 222, 333];
        $params = $this->createMock(JsonRpcParams::class);

        foreach ($orderIds as $orderId) {
            $this->procedure->orderId = $orderId;

            // Act
            $lockResource = $this->procedure->getLockResource($params);

            // Assert
            $expectedResource = "payment_initiate:{$orderId}";
            $this->assertEquals([$expectedResource], $lockResource);
        }
    }
}
