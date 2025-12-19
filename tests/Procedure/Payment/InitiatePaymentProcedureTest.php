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
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\OrderCheckoutBundle\Param\Payment\InitiatePaymentParam;
use Tourze\OrderCheckoutBundle\Procedure\Payment\InitiatePaymentProcedure;
use Tourze\PaymentContracts\Enum\PaymentType;
use Tourze\PHPUnitJsonRPC\AbstractProcedureTestCase;
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

        $param = new InitiatePaymentParam(
            orderId: $orderId ?? 0,
            paymentMethod: PaymentType::LEGACY_ALIPAY->value
        );

        // Act: 执行支付发起
        $result = $this->procedure->execute($param);

        // Assert: 验证结果结构
        $this->assertInstanceOf(ArrayResult::class, $result);
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
    }

    public function testExecuteWithInvalidPaymentMethodThrowsException(): void
    {
        // Arrange: 设置已登录用户和无效支付方式
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $param = new InitiatePaymentParam(orderId: 123456, paymentMethod: 'invalid_payment_method');

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('无效的支付方式');

        $this->procedure->execute($param);
    }

    public function testExecuteWithEmptyPaymentMethodThrowsException(): void
    {
        // Arrange: 设置已登录用户和空支付方式
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $param = new InitiatePaymentParam(orderId: 123456, paymentMethod: '');

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('无效的支付方式');

        $this->procedure->execute($param);
    }

    public function testExecuteHandlesPaymentServiceException(): void
    {
        // Arrange: 测试订单不存在的情况
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $param = new InitiatePaymentParam(
            orderId: 999999, // 使用一个不存在的订单ID
            paymentMethod: PaymentType::LEGACY_ALIPAY->value
        );

        // Act & Assert: 验证异常处理
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('支付发起失败: 订单不存在');

        $this->procedure->execute($param);
    }

    public function testGetLockResourceReturnsCorrectResource(): void
    {
        // Arrange: 创建参数
        $params = new JsonRpcParams(['orderId' => 999888, 'paymentMethod' => 'alipay']);

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
        $params = new JsonRpcParams(['orderId' => 0, 'paymentMethod' => 'alipay']);

        // Act: 获取锁资源
        $lockResource = $this->procedure->getLockResource($params);

        // Assert: 验证锁资源
        $this->assertIsArray($lockResource);
        $this->assertCount(1, $lockResource);
        $this->assertEquals('payment_initiate:0', $lockResource[0]);
    }
}
