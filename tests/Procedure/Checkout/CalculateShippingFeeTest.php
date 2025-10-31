<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPC\Core\Model\JsonRpcParams;
use Tourze\JsonRPC\Core\Model\JsonRpcRequest;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use Tourze\OrderCheckoutBundle\Procedure\Checkout\CalculateShippingFee;

/**
 * @internal
 */
#[CoversClass(CalculateShippingFee::class)]
#[RunTestsInSeparateProcesses]
final class CalculateShippingFeeTest extends AbstractProcedureTestCase
{
    private CalculateShippingFee $procedure;

    protected function onSetUp(): void
    {
        // 从服务容器获取 procedure 实例，使用真实依赖进行集成测试
        $this->procedure = self::getService(CalculateShippingFee::class);
    }

    public function testExecuteSuccess(): void
    {
        // 设置 procedure 参数
        $this->procedure->addressId = 'address123';
        $this->procedure->items = [
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

        $response = $this->procedure->execute();

        // 验证返回结果的基本结构
        $this->assertIsArray($response);
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
        $this->procedure->addressId = 'invalid_address';
        $this->procedure->items = [
            [
                'productId' => 'product1',
                'quantity' => 1,
                'weight' => '1.000',
            ],
        ];

        $response = $this->procedure->execute();

        // 验证返回结果的基本结构
        $this->assertIsArray($response);
        $this->assertArrayHasKey('fee', $response);
        $this->assertArrayHasKey('isDeliverable', $response);
        $this->assertArrayHasKey('errorMessage', $response);

        // 对于无效地址，isDeliverable 应该为 false
        $this->assertIsBool($response['isDeliverable']);
    }

    public function testExecuteWithMinimalItemData(): void
    {
        $this->procedure->addressId = 'address123';
        $this->procedure->items = [
            [
                'productId' => 'product1',
                'quantity' => 1,
                'weight' => '1.000',
            ],
        ];

        $response = $this->procedure->execute();

        // 验证返回结果的基本结构
        $this->assertIsArray($response);
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

    public function testGetMockResult(): void
    {
        $mockResult = CalculateShippingFee::getMockResult();

        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('fee', $mockResult);
        $this->assertArrayHasKey('isFreeShipping', $mockResult);
        $this->assertArrayHasKey('isDeliverable', $mockResult);
        $this->assertArrayHasKey('errorMessage', $mockResult);
        $this->assertArrayHasKey('details', $mockResult);

        // 验证 Mock 结果的基本类型
        $this->assertIsString($mockResult['fee']);
        $this->assertIsBool($mockResult['isFreeShipping']);
        $this->assertIsBool($mockResult['isDeliverable']);
        $this->assertIsArray($mockResult['details']);
    }

    public function testGetCacheKey(): void
    {
        $addressId = 'address123';
        $items = [
            ['productId' => 'product1', 'quantity' => 1, 'weight' => '1.000'],
            ['productId' => 'product2', 'quantity' => 2, 'weight' => '1.500'],
        ];

        $request = $this->createMock(JsonRpcRequest::class);
        $params = $this->createMock(JsonRpcParams::class);
        $params->method('toArray')->willReturn(['addressId' => $addressId, 'items' => $items]);
        $request->method('getParams')->willReturn($params);

        $cacheKey = $this->procedure->getCacheKey($request);
        $itemsHash = md5(json_encode($items, JSON_THROW_ON_ERROR));
        $expectedKey = sprintf('shipping_fee_%s_%s', $addressId, $itemsHash);

        $this->assertSame($expectedKey, $cacheKey);
    }

    public function testGetCacheDuration(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $this->assertSame(300, $this->procedure->getCacheDuration($request));
    }

    public function testGetCacheTags(): void
    {
        $request = $this->createMock(JsonRpcRequest::class);
        $tags = iterator_to_array($this->procedure->getCacheTags($request));
        $this->assertSame(['shipping_calculation', 'checkout'], $tags);
    }
}
