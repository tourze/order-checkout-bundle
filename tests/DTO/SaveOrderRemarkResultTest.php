<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\SaveOrderRemarkResult;

/**
 * @internal
 */
#[CoversClass(SaveOrderRemarkResult::class)]
final class SaveOrderRemarkResultTest extends TestCase
{
    public function testConstructorInitializesAllProperties(): void
    {
        // Arrange: 准备测试数据
        $orderId = 12345;
        $remark = '请在工作日配送，谢谢！';
        $filteredRemark = '请在工作日配送，谢谢！';
        $hasFilteredContent = false;
        $savedAt = new \DateTimeImmutable('2024-08-24 10:30:00');

        // Act: 创建结果对象
        $result = new SaveOrderRemarkResult(
            $orderId,
            $remark,
            $filteredRemark,
            $hasFilteredContent,
            $savedAt
        );

        // Assert: 验证所有属性
        $this->assertEquals($orderId, $result->orderId);
        $this->assertEquals($remark, $result->remark);
        $this->assertEquals($filteredRemark, $result->filteredRemark);
        $this->assertEquals($hasFilteredContent, $result->hasFilteredContent);
        $this->assertEquals($savedAt, $result->savedAt);
    }

    public function testConstructorWithFilteredContent(): void
    {
        // Arrange: 准备包含过滤内容的测试数据
        $orderId = 67890;
        $remark = '这个商品太垃圾了！质量差得不得了！';
        $filteredRemark = '这个商品质量有待改进！';
        $hasFilteredContent = true;
        $savedAt = new \DateTimeImmutable('2024-08-24 15:45:30');

        // Act: 创建结果对象
        $result = new SaveOrderRemarkResult(
            $orderId,
            $remark,
            $filteredRemark,
            $hasFilteredContent,
            $savedAt
        );

        // Assert: 验证过滤相关属性
        $this->assertEquals($remark, $result->remark);
        $this->assertEquals($filteredRemark, $result->filteredRemark);
        $this->assertTrue($result->hasFilteredContent);
        $this->assertNotEquals($result->remark, $result->filteredRemark);
    }

    public function testToArrayWithoutFilteredContent(): void
    {
        // Arrange: 准备无过滤内容的测试数据
        $orderId = 11111;
        $remark = '配送到门卫处即可';
        $filteredRemark = '配送到门卫处即可';
        $hasFilteredContent = false;
        $savedAt = new \DateTimeImmutable('2024-08-24 09:15:45');

        $result = new SaveOrderRemarkResult(
            $orderId,
            $remark,
            $filteredRemark,
            $hasFilteredContent,
            $savedAt
        );

        // Act: 转换为数组
        $array = $result->toArray();

        // Assert: 验证数组结构和内容
        $expectedArray = [
            'orderId' => 11111,
            'remark' => '配送到门卫处即可',
            'filteredRemark' => '配送到门卫处即可',
            'hasFilteredContent' => false,
            'savedAt' => '2024-08-24 09:15:45',
        ];

        $this->assertEquals($expectedArray, $array);
        $this->assertIsArray($array);
        $this->assertCount(5, $array);
    }

    public function testToArrayWithFilteredContent(): void
    {
        // Arrange: 准备有过滤内容的测试数据
        $orderId = 22222;
        $remark = '商品有问题，客服态度恶劣！';
        $filteredRemark = '商品有问题，客服需要改进服务！';
        $hasFilteredContent = true;
        $savedAt = new \DateTimeImmutable('2024-08-24 16:20:10');

        $result = new SaveOrderRemarkResult(
            $orderId,
            $remark,
            $filteredRemark,
            $hasFilteredContent,
            $savedAt
        );

        // Act: 转换为数组
        $array = $result->toArray();

        // Assert: 验证数组内容
        $this->assertEquals(22222, $array['orderId']);
        $this->assertEquals('商品有问题，客服态度恶劣！', $array['remark']);
        $this->assertEquals('商品有问题，客服需要改进服务！', $array['filteredRemark']);
        $this->assertTrue($array['hasFilteredContent']);
        $this->assertEquals('2024-08-24 16:20:10', $array['savedAt']);
    }

    public function testToArrayDateTimeFormatting(): void
    {
        // Arrange: 准备测试不同时间格式
        $orderId = 33333;
        $remark = '测试备注';
        $filteredRemark = '测试备注';
        $hasFilteredContent = false;

        // 测试不同的时间点
        $testCases = [
            new \DateTimeImmutable('2024-01-01 00:00:00'),
            new \DateTimeImmutable('2024-12-31 23:59:59'),
            new \DateTime('2024-06-15 12:30:45'),
        ];

        foreach ($testCases as $savedAt) {
            // Act: 创建结果并转换为数组
            $result = new SaveOrderRemarkResult(
                $orderId,
                $remark,
                $filteredRemark,
                $hasFilteredContent,
                $savedAt
            );
            $array = $result->toArray();

            // Assert: 验证时间格式
            $this->assertIsString($array['savedAt']);
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
                $array['savedAt'],
                '时间格式应为 Y-m-d H:i:s'
            );
            $this->assertEquals($savedAt->format('Y-m-d H:i:s'), $array['savedAt']);
        }
    }

    public function testReadOnlyPropertiesCannotBeModified(): void
    {
        // Arrange: 创建结果对象
        $result = new SaveOrderRemarkResult(
            orderId: 99999,
            remark: '原始备注',
            filteredRemark: '过滤备注',
            hasFilteredContent: false,
            savedAt: new \DateTimeImmutable('2024-08-24 20:00:00')
        );

        // Act & Assert: 验证属性是只读的
        $this->assertEquals(99999, $result->orderId);
        $this->assertEquals('原始备注', $result->remark);
        $this->assertEquals('过滤备注', $result->filteredRemark);
        $this->assertFalse($result->hasFilteredContent);

        // 由于是 readonly 类，属性无法修改，这里只需验证读取正确
        $this->assertInstanceOf(\DateTimeInterface::class, $result->savedAt);
    }

    public function testWithEmptyRemarks(): void
    {
        // Arrange: 准备空备注的测试数据
        $orderId = 44444;
        $remark = '';
        $filteredRemark = '';
        $hasFilteredContent = false;
        $savedAt = new \DateTimeImmutable('2024-08-24 08:00:00');

        // Act: 创建结果对象
        $result = new SaveOrderRemarkResult(
            $orderId,
            $remark,
            $filteredRemark,
            $hasFilteredContent,
            $savedAt
        );

        // Assert: 验证空字符串处理
        $this->assertEquals('', $result->remark);
        $this->assertEquals('', $result->filteredRemark);
        $this->assertFalse($result->hasFilteredContent);

        $array = $result->toArray();
        $this->assertEquals('', $array['remark']);
        $this->assertEquals('', $array['filteredRemark']);
    }

    public function testWithSpecialCharacters(): void
    {
        // Arrange: 准备包含特殊字符的测试数据
        $orderId = 55555;
        $remark = '备注包含特殊字符：@#$%^&*()，中文，English，123';
        $filteredRemark = '备注包含特殊字符：@#$%^&*()，中文，English，123';
        $hasFilteredContent = false;
        $savedAt = new \DateTimeImmutable('2024-08-24 14:30:15');

        // Act: 创建结果对象
        $result = new SaveOrderRemarkResult(
            $orderId,
            $remark,
            $filteredRemark,
            $hasFilteredContent,
            $savedAt
        );

        // Assert: 验证特殊字符处理
        $this->assertEquals($remark, $result->remark);
        $this->assertEquals($filteredRemark, $result->filteredRemark);

        $array = $result->toArray();
        $this->assertEquals($remark, $array['remark']);
        $this->assertEquals($filteredRemark, $array['filteredRemark']);
    }
}
