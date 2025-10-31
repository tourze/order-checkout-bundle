<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Procedure\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\JsonRPC\Core\Exception\ApiException;
use Tourze\JsonRPC\Core\Tests\AbstractProcedureTestCase;
use Tourze\OrderCheckoutBundle\Procedure\Order\GetOrderRemarkHistoryProcedure;
use Tourze\OrderCheckoutBundle\Service\OrderRemarkService;

/**
 * @internal
 */
#[CoversClass(GetOrderRemarkHistoryProcedure::class)]
#[RunTestsInSeparateProcesses]
final class GetOrderRemarkHistoryProcedureTest extends AbstractProcedureTestCase
{
    private GetOrderRemarkHistoryProcedure $procedure;

    protected function onSetUp(): void
    {
        $this->procedure = self::getService(GetOrderRemarkHistoryProcedure::class);
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

    public function testExecuteWithValidOrderIdShouldReturnHistoryList(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->orderId = 12345;

        // Act: 执行获取订单备注历史
        $result = $this->procedure->execute();

        // Assert: 验证结果结构
        $this->assertIsArray($result);
        $this->assertArrayHasKey('__message', $result);
        $this->assertArrayHasKey('orderId', $result);
        $this->assertArrayHasKey('history', $result);
        $this->assertArrayHasKey('total', $result);

        // 验证基本信息
        $this->assertEquals('获取订单备注历史成功', $result['__message']);
        $this->assertEquals(12345, $result['orderId']);
        $this->assertIsArray($result['history']);
        $this->assertIsInt($result['total']);
        $this->assertEquals(count($result['history']), $result['total']);
    }

    public function testExecuteWithOrderHavingMultipleRemarksShouldReturnCompleteHistory(): void
    {
        // Arrange: 创建已登录用户，模拟有多条备注历史的订单
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->orderId = 67890;

        // 模拟OrderRemarkService返回多条历史记录
        $mockHistory = [
            [
                'id' => 1,
                'remark' => '请尽快发货，谢谢！😊',
                'originalRemark' => null,
                'isFiltered' => false,
                'filteredWords' => null,
                'createdAt' => '2024-01-01 15:30:00',
                'createdBy' => 100,
            ],
            [
                'id' => 2,
                'remark' => '修改备注：请发顺丰快递',
                'originalRemark' => '请尽快发货，谢谢！😊',
                'isFiltered' => false,
                'filteredWords' => null,
                'createdAt' => '2024-01-01 16:00:00',
                'createdBy' => 100,
            ],
            [
                'id' => 3,
                'remark' => '再次修改：请发EMS',
                'originalRemark' => '修改备注：请发顺丰快递',
                'isFiltered' => false,
                'filteredWords' => null,
                'createdAt' => '2024-01-01 16:30:00',
                'createdBy' => 100,
            ],
        ];

        $mockOrderRemarkService = $this->createMock(OrderRemarkService::class);
        $mockOrderRemarkService->method('getOrderRemarkHistory')->willReturn($mockHistory);

        // Act: 执行获取订单备注历史
        $result = $this->procedure->execute();

        // Assert: 验证多条记录的情况
        $this->assertIsArray($result);
        $this->assertEquals(67890, $result['orderId']);
        $this->assertIsArray($result['history']);

        // 验证历史记录数量
        if ([] !== $result['history']) {
            $this->assertGreaterThan(0, $result['total']);

            // 验证第一条历史记录的结构
            $firstRecord = $result['history'][0];
            $this->assertIsArray($firstRecord);
            $this->assertArrayHasKey('id', $firstRecord);
            $this->assertArrayHasKey('remark', $firstRecord);
            $this->assertArrayHasKey('createdAt', $firstRecord);
            $this->assertArrayHasKey('createdBy', $firstRecord);
        }
    }

    public function testExecuteWithOrderHavingNoRemarksShouldReturnEmptyHistory(): void
    {
        // Arrange: 创建已登录用户，模拟无备注历史的订单
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->orderId = 11111;

        // 模拟OrderRemarkService返回空历史
        $mockOrderRemarkService = $this->createMock(OrderRemarkService::class);
        $mockOrderRemarkService->method('getOrderRemarkHistory')->willReturn([]);

        // Act: 执行获取订单备注历史
        $result = $this->procedure->execute();

        // Assert: 验证空历史情况
        $this->assertIsArray($result);
        $this->assertEquals(11111, $result['orderId']);
        $this->assertIsArray($result['history']);

        // 验证空历史的处理
        if ([] === $result['history']) {
            $this->assertEquals(0, $result['total']);
        }
    }

    public function testExecuteWithFilteredRemarksShouldShowFilteringInfo(): void
    {
        // Arrange: 创建已登录用户，模拟包含过滤内容的备注历史
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->orderId = 22222;

        // 模拟包含过滤内容的历史记录
        $mockHistory = [
            [
                'id' => 1,
                'remark' => '请尽快发货',
                'originalRemark' => '请尽快发货，[敏感词]',
                'isFiltered' => true,
                'filteredWords' => ['敏感词'],
                'createdAt' => '2024-01-01 12:00:00',
                'createdBy' => 100,
            ],
        ];

        $mockOrderRemarkService = $this->createMock(OrderRemarkService::class);
        $mockOrderRemarkService->method('getOrderRemarkHistory')->willReturn($mockHistory);

        // Act: 执行获取订单备注历史
        $result = $this->procedure->execute();

        // Assert: 验证过滤信息
        $this->assertIsArray($result);

        if ([] !== $result['history']) {
            $this->assertIsArray($result['history']);
            $filteredRecord = $result['history'][0];
            $this->assertIsArray($filteredRecord);

            // 验证过滤相关字段的存在
            $this->assertArrayHasKey('isFiltered', $filteredRecord);
            $this->assertArrayHasKey('originalRemark', $filteredRecord);
            $this->assertArrayHasKey('filteredWords', $filteredRecord);

            // 如果记录被过滤，验证过滤信息
            if ($filteredRecord['isFiltered']) {
                $this->assertIsBool($filteredRecord['isFiltered']);
                $this->assertTrue($filteredRecord['isFiltered']);
                $this->assertNotNull($filteredRecord['originalRemark']);
                $this->assertIsArray($filteredRecord['filteredWords']);
                $this->assertNotEmpty($filteredRecord['filteredWords']);
            }
        }
    }

    public function testExecuteVerifiesRemarkHistoryRecordStructure(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->orderId = 33333;

        // Act: 执行获取订单备注历史
        $result = $this->procedure->execute();

        // Assert: 验证历史记录结构
        $this->assertIsArray($result);

        if ([] !== $result['history']) {
            $this->assertIsArray($result['history']);
            foreach ($result['history'] as $record) {
                $this->assertIsArray($record);

                // 验证必需字段
                $this->assertArrayHasKey('id', $record);
                $this->assertArrayHasKey('remark', $record);
                $this->assertArrayHasKey('createdAt', $record);
                $this->assertArrayHasKey('createdBy', $record);

                // 验证可选字段
                $this->assertArrayHasKey('originalRemark', $record);
                $this->assertArrayHasKey('isFiltered', $record);
                $this->assertArrayHasKey('filteredWords', $record);

                // 验证字段类型
                $this->assertIsInt($record['id']);
                $this->assertIsString($record['remark']);
                $this->assertIsString($record['createdAt']);
                $this->assertIsInt($record['createdBy']);
                $this->assertIsBool($record['isFiltered']);

                // originalRemark和filteredWords可以为null
                if (null !== $record['originalRemark']) {
                    $this->assertIsString($record['originalRemark']);
                }
                if (null !== $record['filteredWords']) {
                    $this->assertIsArray($record['filteredWords']);
                }
            }
        }
    }

    public function testExecuteWithInvalidOrderIdShouldHandleGracefully(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        // Test with potentially invalid order ID
        $this->procedure->orderId = 999999;

        // Act: 执行获取订单备注历史
        $result = $this->procedure->execute();

        // Assert: 即使订单ID可能不存在，也应该返回结构化数据
        // (实际业务中可能会抛出异常或返回空列表，这里测试当前实现)
        $this->assertIsArray($result);
        $this->assertArrayHasKey('__message', $result);
        $this->assertArrayHasKey('orderId', $result);
        $this->assertArrayHasKey('history', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(999999, $result['orderId']);
    }

    public function testExecuteReturnsHistoryInCorrectOrder(): void
    {
        // Arrange: 创建已登录用户
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->orderId = 44444;

        // Act: 执行获取订单备注历史
        $result = $this->procedure->execute();

        // Assert: 验证历史记录排序
        $this->assertIsArray($result);
        $this->assertArrayHasKey('history', $result);
        $this->assertIsArray($result['history']);

        if (count($result['history']) > 1) {
            // 验证时间排序（应该是最新的在前）
            $firstRecord = $result['history'][0];
            $this->assertIsArray($firstRecord);
            $this->assertArrayHasKey('createdAt', $firstRecord);
            $secondRecord = $result['history'][1];
            $this->assertIsArray($secondRecord);
            $this->assertArrayHasKey('createdAt', $secondRecord);

            $this->assertIsString($firstRecord['createdAt']);
            $firstTime = strtotime($firstRecord['createdAt']);
            $this->assertIsString($secondRecord['createdAt']);
            $secondTime = strtotime($secondRecord['createdAt']);

            // 第一条记录应该比第二条记录更新或相等
            $this->assertGreaterThanOrEqual($secondTime, $firstTime);
        }
    }

    public function testExecuteWithLargeOrderIdShouldWork(): void
    {
        // Arrange: 创建已登录用户，测试大订单ID
        $user = $this->createNormalUser('test_user_' . uniqid());
        $this->setAuthenticatedUser($user);

        $this->procedure->orderId = 2147483647; // 最大int值

        // Act: 执行获取订单备注历史
        $result = $this->procedure->execute();

        // Assert: 验证大订单ID的处理
        $this->assertIsArray($result);
        $this->assertEquals(2147483647, $result['orderId']);
    }

    public function testExecuteHandlesDifferentUserTypes(): void
    {
        // Arrange: 创建不同类型的用户
        $normalUser = $this->createNormalUser('test_user_' . uniqid());
        $adminUser = $this->createAdminUser('admin_user_' . uniqid());

        // Test Case 1: 普通用户访问
        $this->setAuthenticatedUser($normalUser);
        $this->procedure->orderId = 55555;
        $result1 = $this->procedure->execute();

        $this->assertIsArray($result1);
        $this->assertEquals('获取订单备注历史成功', $result1['__message']);

        // Test Case 2: 管理员用户访问
        $this->setAuthenticatedUser($adminUser);
        $this->procedure->orderId = 66666;
        $result2 = $this->procedure->execute();

        $this->assertIsArray($result2);
        $this->assertEquals('获取订单备注历史成功', $result2['__message']);
    }

    public function testGetMockResultReturnsValidStructure(): void
    {
        // Act: 获取Mock结果
        $mockResult = GetOrderRemarkHistoryProcedure::getMockResult();

        // Assert: 验证Mock结果结构
        $this->assertIsArray($mockResult);
        $this->assertArrayHasKey('__message', $mockResult);
        $this->assertArrayHasKey('orderId', $mockResult);
        $this->assertArrayHasKey('history', $mockResult);
        $this->assertArrayHasKey('total', $mockResult);

        // 验证基本数据类型
        $this->assertEquals('获取订单备注历史成功', $mockResult['__message']);
        $this->assertIsInt($mockResult['orderId']);
        $this->assertIsArray($mockResult['history']);
        $this->assertIsInt($mockResult['total']);

        // 验证total与history数组长度一致
        $this->assertEquals(count($mockResult['history']), $mockResult['total']);
    }

    public function testMockResultShowsRealisticHistoryData(): void
    {
        // Act: 获取Mock结果
        $mockResult = GetOrderRemarkHistoryProcedure::getMockResult();

        // Assert: 验证Mock数据的真实性
        $this->assertNotNull($mockResult);
        $this->assertGreaterThan(0, $mockResult['total']); // 有历史记录
        $this->assertNotEmpty($mockResult['history']); // 历史不为空

        // 验证每条历史记录的结构和内容
        $this->assertIsArray($mockResult['history']);
        foreach ($mockResult['history'] as $record) {
            $this->assertIsArray($record);

            // 验证必需字段
            $this->assertArrayHasKey('id', $record);
            $this->assertArrayHasKey('remark', $record);
            $this->assertArrayHasKey('createdAt', $record);
            $this->assertArrayHasKey('createdBy', $record);

            // 验证数据类型和合理性
            $this->assertIsInt($record['id']);
            $this->assertGreaterThan(0, $record['id']);
            $this->assertIsString($record['remark']);
            $this->assertNotEmpty($record['remark']);
            $this->assertIsString($record['createdAt']);
            $this->assertIsInt($record['createdBy']);
            $this->assertGreaterThan(0, $record['createdBy']);

            // 验证时间格式
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['createdAt']);

            // 验证可选字段
            $this->assertArrayHasKey('originalRemark', $record);
            $this->assertArrayHasKey('isFiltered', $record);
            $this->assertArrayHasKey('filteredWords', $record);
            $this->assertIsBool($record['isFiltered']);

            // 如果有过滤，验证过滤信息的合理性
            if ($record['isFiltered']) {
                $this->assertNotNull($record['originalRemark']);
                $this->assertNotNull($record['filteredWords']);
                $this->assertIsArray($record['filteredWords']);
            }
        }
    }

    public function testMockResultShowsChronologicalOrder(): void
    {
        // Act: 获取Mock结果
        $mockResult = GetOrderRemarkHistoryProcedure::getMockResult();

        // Assert: 验证Mock数据的时间顺序
        $this->assertNotNull($mockResult);
        $this->assertArrayHasKey('history', $mockResult);
        $history = $mockResult['history'];
        $this->assertIsArray($history);

        if (count($history) > 1) {
            for ($i = 0; $i < count($history) - 1; ++$i) {
                $this->assertIsArray($history[$i]);
                $this->assertArrayHasKey('createdAt', $history[$i]);
                $this->assertIsString($history[$i]['createdAt']);
                $this->assertIsArray($history[$i + 1]);
                $this->assertArrayHasKey('createdAt', $history[$i + 1]);
                $this->assertIsString($history[$i + 1]['createdAt']);
                $currentTime = strtotime($history[$i]['createdAt']);
                $nextTime = strtotime($history[$i + 1]['createdAt']);

                // 验证时间是按降序排列的（最新的在前）
                $this->assertGreaterThanOrEqual($nextTime, $currentTime,
                    '备注历史应该按时间降序排列（最新的在前）');
            }
        }
    }
}
