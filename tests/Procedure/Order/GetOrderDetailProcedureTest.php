<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use Tourze\OrderCheckoutBundle\Procedure\Order\GetOrderDetailProcedure;

/**
 * @internal
 */
#[CoversClass(GetOrderDetailProcedure::class)]
#[RunTestsInSeparateProcesses]
final class GetOrderDetailProcedureTest extends AbstractProcedureTestCase
{
    private GetOrderDetailProcedure $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(GetOrderDetailProcedure::class);
    }

    public function testExecuteThrowsExceptionWhenUserNotLoggedIn(): void
    {
        // Arrange: 设置订单ID但未登录
        $this->procedure->orderId = 12345;

        // Act & Assert: 验证异常
        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('用户未登录或类型错误');

        $this->procedure->execute();
    }

    public function testExecuteWithInvalidOrderIdShouldThrowException(): void
    {
        // Arrange: 设置无效的订单ID
        $this->procedure->orderId = 0; // 无效值

        // Act & Assert: 验证参数验证异常
        $this->expectException(\Exception::class);

        $this->procedure->execute();
    }

    public function testExecuteWithNegativeOrderIdShouldThrowException(): void
    {
        // Arrange: 设置负数订单ID
        $this->procedure->orderId = -1;

        // Act & Assert: 验证参数验证异常
        $this->expectException(\Exception::class);

        $this->procedure->execute();
    }

    public function testGetMockResultReturnsValidStructure(): void
    {
        // Act: 获取Mock结果
        $mockResult = GetOrderDetailProcedure::getMockResult();

        // Assert: 验证Mock结果结构
        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('__message', $mockResult);
        $this->assertArrayHasKey('orderId', $mockResult);
        $this->assertArrayHasKey('orderNumber', $mockResult);
        $this->assertArrayHasKey('status', $mockResult);
        $this->assertArrayHasKey('totalAmount', $mockResult);
        $this->assertArrayHasKey('createTime', $mockResult);
        $this->assertArrayHasKey('paymentMethod', $mockResult);
        $this->assertArrayHasKey('shippingAddress', $mockResult);
        $this->assertArrayHasKey('items', $mockResult);
        $this->assertArrayHasKey('customerRemark', $mockResult);
        $this->assertArrayHasKey('hasRemark', $mockResult);

        // 验证基本数据类型
        $this->assertEquals('获取订单详情成功', $mockResult['__message']);
        $this->assertIsInt($mockResult['orderId']);
        $this->assertIsString($mockResult['orderNumber']);
        $this->assertIsString($mockResult['status']);
        $this->assertIsFloat($mockResult['totalAmount']);
        $this->assertIsString($mockResult['createTime']);
        $this->assertIsString($mockResult['paymentMethod']);
        $this->assertIsArray($mockResult['shippingAddress']);
        $this->assertIsArray($mockResult['items']);

        $this->assertIsString($mockResult['customerRemark']);
        $this->assertIsBool($mockResult['hasRemark']);
    }

    public function testMockResultShowsValidShippingAddress(): void
    {
        // Act: 获取Mock结果
        $mockResult = GetOrderDetailProcedure::getMockResult();

        // Assert: 验证收货地址Mock结构
        $this->assertIsArray($mockResult);
        $shippingAddress = $mockResult['shippingAddress'];
        $this->assertIsArray($shippingAddress);
        $this->assertArrayHasKey('name', $shippingAddress);
        $this->assertArrayHasKey('phone', $shippingAddress);
        $this->assertArrayHasKey('address', $shippingAddress);

        $this->assertIsString($shippingAddress['name']);
        $this->assertIsString($shippingAddress['phone']);
        $this->assertIsString($shippingAddress['address']);
    }

    public function testMockResultShowsValidOrderItems(): void
    {
        // Act: 获取Mock结果
        $mockResult = GetOrderDetailProcedure::getMockResult();

        // Assert: 验证订单商品Mock结构
        $this->assertIsArray($mockResult);
        $items = $mockResult['items'];
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('skuId', $item);
            $this->assertArrayHasKey('productName', $item);
            $this->assertArrayHasKey('quantity', $item);
            $this->assertArrayHasKey('price', $item);
            $this->assertArrayHasKey('totalPrice', $item);

            $this->assertIsInt($item['skuId']);
            $this->assertIsString($item['productName']);
            $this->assertIsInt($item['quantity']);
            $this->assertIsFloat($item['price']);
            $this->assertIsFloat($item['totalPrice']);

            // 验证数值的合理性
            $this->assertGreaterThan(0, $item['skuId']);
            $this->assertNotEmpty($item['productName']);
            $this->assertGreaterThan(0, $item['quantity']);
            $this->assertGreaterThan(0, $item['price']);
            $this->assertGreaterThan(0, $item['totalPrice']);
        }
    }

    public function testMockResultShowsRealisticOrderData(): void
    {
        // Act: 获取Mock结果
        $mockResult = GetOrderDetailProcedure::getMockResult();

        // Assert: 验证Mock数据的真实性
        $this->assertIsArray($mockResult);

        // 验证状态是有效的订单状态
        $validStatuses = ['pending_payment', 'paid', 'shipped', 'delivered', 'cancelled', 'refunded'];
        $this->assertContains($mockResult['status'], $validStatuses);

        // 验证总金额为正数
        $this->assertGreaterThan(0, $mockResult['totalAmount']);

        // 验证订单号格式
        $this->assertIsString($mockResult['orderNumber']);
        $this->assertStringStartsWith('ORD', $mockResult['orderNumber']);

        // 验证商品价格计算的正确性
        $items = $mockResult['items'];
        $this->assertIsArray($items);
        foreach ($items as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('quantity', $item);
            $this->assertArrayHasKey('price', $item);
            $this->assertArrayHasKey('totalPrice', $item);
            // 验证总价计算
            $quantity = $item['quantity'];
            $price = $item['price'];
            $this->assertTrue(is_numeric($quantity));
            $this->assertTrue(is_numeric($price));
            $expectedTotal = (float) $quantity * (float) $price;
            $this->assertEquals($expectedTotal, $item['totalPrice']);
        }
    }
}
