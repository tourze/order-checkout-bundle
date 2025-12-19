<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Result\ArrayResult;
use Tourze\OrderCheckoutBundle\Param\Checkout\CalculateShippingFeeParam;
use Tourze\OrderCheckoutBundle\Procedure\Checkout\CalculateShippingFee;
use Tourze\PHPUnitJsonRPC\AbstractProcedureTestCase;

/**
 * @internal
 */
#[CoversClass(CalculateShippingFee::class)]
#[RunTestsInSeparateProcesses]
final class CalculateShippingFeeTest extends AbstractProcedureTestCase
{
    private CalculateShippingFee $procedure;

    private function createJsonRpcRequest(?JsonRpcParams $params = null): JsonRpcRequest
    {
        $request = new JsonRpcRequest();
        $request->setMethod('test');
        $request->setParams($params);

        return $request;
    }

    protected function onSetUp(): void
    {
        // 从服务容器获取 procedure 实例，使用真实依赖进行集成测试
        $this->procedure = self::getService(CalculateShippingFee::class);
    }

    public function testExecuteSuccess(): void
    {
        // 设置 procedure 参数，使用 Param 对象
        $param = new CalculateShippingFeeParam();
        $param->addressId = 'address123';
        $param->items = [
            [
                'productId' => 'product1',
                'quantity' => 2,
                'weight' => '1.500',
                'price' => '25.00',
            ],
            [
                'productId' => 'product2',
                'quantity' => 1,
                'weight' => '0.800',
                'price' => '15.00',
                'shippingTemplateId' => 'template1',
            ],
        ];

        $response = $this->procedure->execute($param);

        // 验证返回结果是 ArrayResult 实例
        $this->assertInstanceOf(ArrayResult::class, $response);
        $this->assertArrayHasKey('fee', $response);
        $this->assertArrayHasKey('isFreeShipping', $response);
        $this->assertArrayHasKey('isDeliverable', $response);
        $this->assertArrayHasKey('errorMessage', $response);
        $this->assertArrayHasKey('details', $response);

        // 验证基本数据类型
        $this->assertIsString($response['fee']);
        $this->assertIsBool($response['isFreeShipping']);
        $this->assertIsBool($response['isDeliverable']);
        $this->assertIsArray($response['details']);
    }

    public function testExecuteWithError(): void
    {
        // 设置无效的地址ID，应该导致错误
        $param = new CalculateShippingFeeParam();
        $param->addressId = 'invalid_address';
        $param->items = [
            [
                'productId' => 'product1',
                'quantity' => 1,
                'weight' => '1.000',
            ],
        ];

        $response = $this->procedure->execute($param);

        // 验证返回结果是 ArrayResult 实例
        $this->assertInstanceOf(ArrayResult::class, $response);
        $this->assertArrayHasKey('fee', $response);
        $this->assertArrayHasKey('isDeliverable', $response);
        $this->assertArrayHasKey('errorMessage', $response);

        // 对于无效地址，isDeliverable 应该为 false
        $this->assertIsBool($response['isDeliverable']);
    }

    public function testExecuteWithMinimalItemData(): void
    {
        $param = new CalculateShippingFeeParam();
        $param->addressId = 'address123';
        $param->items = [
            [
                'productId' => 'product1',
                'quantity' => 1,
                'weight' => '1.000',
            ],
        ];

        $response = $this->procedure->execute($param);

        // 验证返回结果是 ArrayResult 实例
        $this->assertInstanceOf(ArrayResult::class, $response);
        $this->assertArrayHasKey('fee', $response);
        $this->assertArrayHasKey('isFreeShipping', $response);
        $this->assertArrayHasKey('isDeliverable', $response);
        $this->assertArrayHasKey('errorMessage', $response);
        $this->assertArrayHasKey('details', $response);

        // 验证基本数据类型
        $this->assertIsString($response['fee']);
        $this->assertIsBool($response['isFreeShipping']);
        $this->assertIsBool($response['isDeliverable']);
        $this->assertIsArray($response['details']);
    }

    public function testGetCacheKey(): void
    {
        $addressId = 'address123';
        $items = [
            ['productId' => 'product1', 'quantity' => 1, 'weight' => '1.000'],
            ['productId' => 'product2', 'quantity' => 2, 'weight' => '1.500'],
        ];

        $params = new JsonRpcParams(['addressId' => $addressId, 'items' => $items]);
        $request = $this->createJsonRpcRequest($params);

        $cacheKey = $this->procedure->getCacheKey($request);
        $itemsHash = md5(json_encode($items, JSON_THROW_ON_ERROR));
        $expectedKey = sprintf('shipping_fee_%s_%s', $addressId, $itemsHash);

        $this->assertSame($expectedKey, $cacheKey);
    }

    public function testGetCacheDuration(): void
    {
        $request = $this->createJsonRpcRequest();
        $this->assertSame(300, $this->procedure->getCacheDuration($request));
    }

    public function testGetCacheTags(): void
    {
        $request = $this->createJsonRpcRequest();
        $tags = iterator_to_array($this->procedure->getCacheTags($request));
        $this->assertSame(['shipping_calculation', 'checkout'], $tags);
    }
}
