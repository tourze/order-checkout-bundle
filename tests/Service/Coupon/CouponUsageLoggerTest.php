<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service\Coupon;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Entity\CouponAllocationDetail;
use Tourze\OrderCheckoutBundle\Entity\CouponUsageLog;
use Tourze\OrderCheckoutBundle\Repository\CouponAllocationDetailRepository;
use Tourze\OrderCheckoutBundle\Repository\CouponUsageLogRepository;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponUsageLogger;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CouponUsageLogger::class)]
#[RunTestsInSeparateProcesses]
final class CouponUsageLoggerTest extends AbstractIntegrationTestCase
{
    private CouponUsageLogger $logger;
    private CouponUsageLogRepository $usageLogRepository;
    private CouponAllocationDetailRepository $allocationRepository;

    protected function onSetUp(): void
    {
        $this->logger = self::getService(CouponUsageLogger::class);
        $this->usageLogRepository = self::getService(CouponUsageLogRepository::class);
        $this->allocationRepository = self::getService(CouponAllocationDetailRepository::class);
    }

    public function testService应能从容器正确获取(): void
    {
        $this->assertInstanceOf(CouponUsageLogger::class, $this->logger);
    }

    public function testLogUsage方法存在且参数正确(): void
    {
        $reflection = new \ReflectionClass($this->logger);
        $method = $reflection->getMethod('logUsage');

        $this->assertTrue($method->isPublic());
        $this->assertSame(8, $method->getNumberOfParameters());

        $parameters = $method->getParameters();
        $this->assertSame('couponCode', $parameters[0]->getName());
        $this->assertSame('couponType', $parameters[1]->getName());
        $this->assertSame('userIdentifier', $parameters[2]->getName());
        $this->assertSame('orderId', $parameters[3]->getName());
        $this->assertSame('orderNumber', $parameters[4]->getName());
        $this->assertSame('discountAmount', $parameters[5]->getName());
        $this->assertSame('allocations', $parameters[6]->getName());
        $this->assertSame('metadata', $parameters[7]->getName());
    }

    public function test记录优惠券使用日志时创建基本记录(): void
    {
        // Arrange
        $couponCode = 'TEST-COUPON-001';
        $couponType = 'percentage';
        $userIdentifier = 'user@example.com';
        $orderId = 12345;
        $orderNumber = 'ORD-2025-001';
        $discountAmount = '100.50';

        // Act
        $this->logger->logUsage(
            $couponCode,
            $couponType,
            $userIdentifier,
            $orderId,
            $orderNumber,
            $discountAmount
        );

        // Assert - 验证使用日志已创建
        self::getEntityManager()->clear();
        $logs = $this->usageLogRepository->findBy(['couponCode' => $couponCode]);

        $this->assertCount(1, $logs);
        $log = $logs[0];
        $this->assertInstanceOf(CouponUsageLog::class, $log);
        $this->assertSame($couponCode, $log->getCouponCode());
        $this->assertSame($couponType, $log->getCouponType());
        $this->assertSame($userIdentifier, $log->getUserIdentifier());
        $this->assertSame($orderId, $log->getOrderId());
        $this->assertSame($orderNumber, $log->getOrderNumber());
        $this->assertSame('100.5', $log->getDiscountAmount());
        $this->assertInstanceOf(\DateTimeImmutable::class, $log->getUsageTime());
    }

    public function test记录优惠券使用日志时保存元数据(): void
    {
        // Arrange
        $metadata = [
            'allocation_rule' => 'proportional',
            'campaign_id' => 'CAMP-001',
            'promotion_type' => 'flash_sale',
        ];

        // Act
        $this->logger->logUsage(
            'TEST-METADATA',
            'fixed',
            'user@test.com',
            999,
            'ORD-META-001',
            '50.00',
            [],
            $metadata
        );

        // Assert
        self::getEntityManager()->clear();
        $log = $this->usageLogRepository->findOneBy(['couponCode' => 'TEST-METADATA']);

        $this->assertNotNull($log);
        $this->assertIsArray($log->getMetadata());
        $this->assertSame('proportional', $log->getMetadata()['allocation_rule']);
        $this->assertSame('CAMP-001', $log->getMetadata()['campaign_id']);
        $this->assertSame('flash_sale', $log->getMetadata()['promotion_type']);
    }

    public function test记录分摊明细时创建多个详细记录(): void
    {
        // Arrange
        $couponCode = 'ALLOC-COUPON-001';
        $orderId = 888;
        $allocations = [
            [
                'sku_id' => 'SKU-001',
                'amount' => '30.00',
                'order_product_id' => 1,
            ],
            [
                'sku_id' => 'SKU-002',
                'amount' => '20.00',
                'order_product_id' => 2,
            ],
            [
                'sku_id' => 'SKU-003',
                'amount' => '10.00',
                'order_product_id' => 3,
            ],
        ];

        // Act
        $this->logger->logUsage(
            $couponCode,
            'fixed',
            'user@test.com',
            $orderId,
            'ORD-ALLOC-001',
            '60.00',
            $allocations,
            ['allocation_rule' => 'weighted']
        );

        // Assert
        self::getEntityManager()->clear();
        $details = $this->allocationRepository->findBy(['couponCode' => $couponCode]);

        $this->assertCount(3, $details);

        // 验证第一个分摊明细
        $detail1 = $this->findDetailBySkuId($details, 'SKU-001');
        $this->assertNotNull($detail1);
        $this->assertSame($couponCode, $detail1->getCouponCode());
        $this->assertSame($orderId, $detail1->getOrderId());
        $this->assertSame(1, $detail1->getOrderProductId());
        $this->assertSame('SKU-001', $detail1->getSkuId());
        $this->assertSame('30', $detail1->getAllocatedAmount());
        $this->assertSame('weighted', $detail1->getAllocationRule());

        // 验证第二个分摊明细
        $detail2 = $this->findDetailBySkuId($details, 'SKU-002');
        $this->assertNotNull($detail2);
        $this->assertSame('20', $detail2->getAllocatedAmount());

        // 验证第三个分摊明细
        $detail3 = $this->findDetailBySkuId($details, 'SKU-003');
        $this->assertNotNull($detail3);
        $this->assertSame('10', $detail3->getAllocatedAmount());
    }

    public function test分摊规则从元数据中提取(): void
    {
        // Arrange
        $allocations = [
            [
                'sku_id' => 'SKU-RULE-001',
                'amount' => '15.00',
                'order_product_id' => 100,
            ],
        ];

        // Act
        $this->logger->logUsage(
            'RULE-TEST',
            'percentage',
            'user@test.com',
            777,
            'ORD-RULE-001',
            '15.00',
            $allocations,
            ['allocation_rule' => 'equal_distribution']
        );

        // Assert
        self::getEntityManager()->clear();
        $detail = $this->allocationRepository->findOneBy(['skuId' => 'SKU-RULE-001']);

        $this->assertNotNull($detail);
        $this->assertSame('equal_distribution', $detail->getAllocationRule());
    }

    public function test当元数据中无分摊规则时使用空字符串(): void
    {
        // Arrange
        $allocations = [
            [
                'sku_id' => 'SKU-NO-RULE',
                'amount' => '25.00',
                'order_product_id' => 200,
            ],
        ];

        // Act
        $this->logger->logUsage(
            'NO-RULE-COUPON',
            'fixed',
            'user@test.com',
            666,
            'ORD-NO-RULE',
            '25.00',
            $allocations,
            [] // 空元数据
        );

        // Assert
        self::getEntityManager()->clear();
        $detail = $this->allocationRepository->findOneBy(['skuId' => 'SKU-NO-RULE']);

        $this->assertNotNull($detail);
        $this->assertSame('', $detail->getAllocationRule());
    }

    public function test当元数据分摊规则为非字符串时转换为字符串(): void
    {
        // Arrange
        $allocations = [
            [
                'sku_id' => 'SKU-NUMERIC-RULE',
                'amount' => '10.00',
                'order_product_id' => 300,
            ],
        ];

        // Act
        $this->logger->logUsage(
            'NUMERIC-RULE',
            'fixed',
            'user@test.com',
            555,
            'ORD-NUMERIC',
            '10.00',
            $allocations,
            ['allocation_rule' => 123] // 数字类型
        );

        // Assert
        self::getEntityManager()->clear();
        $detail = $this->allocationRepository->findOneBy(['skuId' => 'SKU-NUMERIC-RULE']);

        $this->assertNotNull($detail);
        $this->assertSame('123', $detail->getAllocationRule());
    }

    public function test分摊明细金额格式化为两位小数(): void
    {
        // Arrange
        $allocations = [
            [
                'sku_id' => 'SKU-FORMAT-001',
                'amount' => '12.345', // 三位小数
                'order_product_id' => 400,
            ],
            [
                'sku_id' => 'SKU-FORMAT-002',
                'amount' => 15, // 整数
                'order_product_id' => 401,
            ],
            [
                'sku_id' => 'SKU-FORMAT-003',
                'amount' => '8.1', // 一位小数
                'order_product_id' => 402,
            ],
        ];

        // Act
        $this->logger->logUsage(
            'FORMAT-TEST',
            'fixed',
            'user@test.com',
            444,
            'ORD-FORMAT',
            '35.45',
            $allocations
        );

        // Assert
        self::getEntityManager()->clear();
        $details = $this->allocationRepository->findBy(['couponCode' => 'FORMAT-TEST']);

        $detail1 = $this->findDetailBySkuId($details, 'SKU-FORMAT-001');
        $this->assertSame('12.35', $detail1->getAllocatedAmount()); // 四舍五入

        $detail2 = $this->findDetailBySkuId($details, 'SKU-FORMAT-002');
        $this->assertSame('15', $detail2->getAllocatedAmount());

        $detail3 = $this->findDetailBySkuId($details, 'SKU-FORMAT-003');
        $this->assertSame('8.1', $detail3->getAllocatedAmount());
    }

    public function test优惠金额格式化为两位小数(): void
    {
        // Arrange & Act
        $this->logger->logUsage(
            'AMOUNT-FORMAT-001',
            'percentage',
            'user@test.com',
            333,
            'ORD-AMOUNT-001',
            '99.999' // 三位小数
        );

        // Assert
        self::getEntityManager()->clear();
        $log = $this->usageLogRepository->findOneBy(['couponCode' => 'AMOUNT-FORMAT-001']);

        $this->assertNotNull($log);
        $this->assertSame('100', $log->getDiscountAmount()); // 四舍五入
    }

    public function test处理非法金额值时使用默认值(): void
    {
        // Arrange
        $allocations = [
            [
                'sku_id' => 'SKU-INVALID',
                'amount' => 'invalid-amount', // 非法字符串
                'order_product_id' => 500,
            ],
            [
                'sku_id' => 'SKU-MISSING',
                // 缺少 amount 字段
                'order_product_id' => 501,
            ],
        ];

        // Act
        $this->logger->logUsage(
            'INVALID-AMOUNT',
            'fixed',
            'user@test.com',
            222,
            'ORD-INVALID',
            'not-a-number', // 非法优惠金额
            $allocations
        );

        // Assert
        self::getEntityManager()->clear();
        $log = $this->usageLogRepository->findOneBy(['couponCode' => 'INVALID-AMOUNT']);
        $this->assertSame('0', $log->getDiscountAmount());

        $details = $this->allocationRepository->findBy(['couponCode' => 'INVALID-AMOUNT']);
        $detail1 = $this->findDetailBySkuId($details, 'SKU-INVALID');
        $this->assertSame('0', $detail1->getAllocatedAmount());

        $detail2 = $this->findDetailBySkuId($details, 'SKU-MISSING');
        $this->assertSame('0', $detail2->getAllocatedAmount());
    }

    public function test跳过非数组类型的分摊项(): void
    {
        // Arrange
        $allocations = [
            [
                'sku_id' => 'SKU-VALID',
                'amount' => '10.00',
                'order_product_id' => 600,
            ],
            'invalid-item', // 非数组类型
            null, // null 值
            [
                'sku_id' => 'SKU-VALID-2',
                'amount' => '5.00',
                'order_product_id' => 601,
            ],
        ];

        // Act
        $this->logger->logUsage(
            'SKIP-INVALID',
            'fixed',
            'user@test.com',
            111,
            'ORD-SKIP',
            '15.00',
            $allocations
        );

        // Assert
        self::getEntityManager()->clear();
        $details = $this->allocationRepository->findBy(['couponCode' => 'SKIP-INVALID']);

        // 只应创建 2 个有效的分摊明细
        $this->assertCount(2, $details);
        $this->assertNotNull($this->findDetailBySkuId($details, 'SKU-VALID'));
        $this->assertNotNull($this->findDetailBySkuId($details, 'SKU-VALID-2'));
    }

    public function test分摊明细中order_product_id可以为null(): void
    {
        // Arrange
        $allocations = [
            [
                'sku_id' => 'SKU-NULL-PRODUCT',
                'amount' => '20.00',
                // order_product_id 未设置
            ],
        ];

        // Act
        $this->logger->logUsage(
            'NULL-PRODUCT-ID',
            'fixed',
            'user@test.com',
            999,
            'ORD-NULL-PRODUCT',
            '20.00',
            $allocations
        );

        // Assert
        self::getEntityManager()->clear();
        $detail = $this->allocationRepository->findOneBy(['skuId' => 'SKU-NULL-PRODUCT']);

        $this->assertNotNull($detail);
        $this->assertNull($detail->getOrderProductId());
    }

    public function test分摊明细中缺少sku_id时使用空字符串(): void
    {
        // Arrange
        $allocations = [
            [
                // sku_id 未设置
                'amount' => '15.00',
                'order_product_id' => 700,
            ],
        ];

        // Act
        $this->logger->logUsage(
            'MISSING-SKU',
            'fixed',
            'user@test.com',
            888,
            'ORD-MISSING-SKU',
            '15.00',
            $allocations
        );

        // Assert
        self::getEntityManager()->clear();
        $detail = $this->allocationRepository->findOneBy(['orderProductId' => 700]);

        $this->assertNotNull($detail);
        $this->assertSame('', $detail->getSkuId());
    }

    public function test完整场景包含使用日志和多个分摊明细(): void
    {
        // Arrange
        $couponCode = 'FULL-SCENARIO-COUPON';
        $couponType = 'combination';
        $userIdentifier = 'premium-user@example.com';
        $orderId = 10001;
        $orderNumber = 'ORD-2025-FULL-001';
        $discountAmount = '150.00';
        $allocations = [
            [
                'sku_id' => 'SKU-PREMIUM-001',
                'amount' => '80.00',
                'order_product_id' => 1001,
            ],
            [
                'sku_id' => 'SKU-PREMIUM-002',
                'amount' => '50.00',
                'order_product_id' => 1002,
            ],
            [
                'sku_id' => 'SKU-PREMIUM-003',
                'amount' => '20.00',
                'order_product_id' => 1003,
            ],
        ];
        $metadata = [
            'allocation_rule' => 'value_based',
            'campaign_id' => 'PREMIUM-2025',
            'promotion_tier' => 'platinum',
        ];

        // Act
        $this->logger->logUsage(
            $couponCode,
            $couponType,
            $userIdentifier,
            $orderId,
            $orderNumber,
            $discountAmount,
            $allocations,
            $metadata
        );

        // Assert - 验证使用日志
        self::getEntityManager()->clear();
        $log = $this->usageLogRepository->findOneBy(['couponCode' => $couponCode]);

        $this->assertNotNull($log);
        $this->assertSame($couponCode, $log->getCouponCode());
        $this->assertSame($couponType, $log->getCouponType());
        $this->assertSame($userIdentifier, $log->getUserIdentifier());
        $this->assertSame($orderId, $log->getOrderId());
        $this->assertSame($orderNumber, $log->getOrderNumber());
        $this->assertSame('150', $log->getDiscountAmount());
        $this->assertSame('value_based', $log->getMetadata()['allocation_rule']);
        $this->assertSame('PREMIUM-2025', $log->getMetadata()['campaign_id']);
        $this->assertSame('platinum', $log->getMetadata()['promotion_tier']);

        // Assert - 验证分摊明细
        $details = $this->allocationRepository->findBy(['couponCode' => $couponCode]);
        $this->assertCount(3, $details);

        // 验证各个分摊明细（注意数据库返回的金额可能不保留尾随零）
        $detail1 = $this->findDetailBySkuId($details, 'SKU-PREMIUM-001');
        $this->assertNotNull($detail1);
        $this->assertSame($couponCode, $detail1->getCouponCode());
        $this->assertSame($orderId, $detail1->getOrderId());
        $this->assertSame(1001, $detail1->getOrderProductId());
        $this->assertSame('SKU-PREMIUM-001', $detail1->getSkuId());
        $this->assertSame('80', $detail1->getAllocatedAmount());
        $this->assertSame('value_based', $detail1->getAllocationRule());

        $detail2 = $this->findDetailBySkuId($details, 'SKU-PREMIUM-002');
        $this->assertNotNull($detail2);
        $this->assertSame('50', $detail2->getAllocatedAmount());

        $detail3 = $this->findDetailBySkuId($details, 'SKU-PREMIUM-003');
        $this->assertNotNull($detail3);
        $this->assertSame('20', $detail3->getAllocatedAmount());
    }

    /**
     * 辅助方法：从分摊明细列表中查找指定 SKU ID 的记录
     *
     * @param array<CouponAllocationDetail> $details
     */
    private function findDetailBySkuId(array $details, string $skuId): ?CouponAllocationDetail
    {
        foreach ($details as $detail) {
            if ($detail->getSkuId() === $skuId) {
                return $detail;
            }
        }

        return null;
    }
}
