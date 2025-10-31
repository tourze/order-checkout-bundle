<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Exception\MockServiceException;
use Tourze\OrderCheckoutBundle\Test\MockStockService;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockLog;

/**
 * MockStockService 测试
 *
 * 按照Linus原则：
 * - 测试核心功能行为
 * - 验证业务异常处理
 * - 保持测试简单直接
 * @internal
 */
#[CoversClass(MockStockService::class)]
final class MockStockServiceTest extends TestCase
{
    private MockStockService $service;

    protected function setUp(): void
    {
        $this->service = new MockStockService();
    }

    public function testGetAvailableStockReturnsValidSummary(): void
    {
        $sku = $this->createMock(SKU::class);
        $sku->method('getId')->willReturn('TEST-SKU-001');

        $summary = $this->service->getAvailableStock($sku);

        $this->assertSame('TEST-SKU-001', $summary->getSpuId());
        $this->assertSame(100, $summary->getTotalQuantity());
        $this->assertSame(95, $summary->getAvailableQuantity());
        $this->assertSame(5, $summary->getReservedQuantity());
    }

    public function testCheckStockAvailabilityReturnsTrueWhenEnoughStock(): void
    {
        $sku = $this->createMock(SKU::class);

        $this->assertTrue($this->service->checkStockAvailability($sku, 50));
        $this->assertTrue($this->service->checkStockAvailability($sku, 95));
    }

    public function testCheckStockAvailabilityReturnsFalseWhenInsufficientStock(): void
    {
        $sku = $this->createMock(SKU::class);

        $this->assertFalse($this->service->checkStockAvailability($sku, 96));
        $this->assertFalse($this->service->checkStockAvailability($sku, 200));
    }

    public function testGetBatchDetailsReturnsValidStructure(): void
    {
        $sku = $this->createMock(SKU::class);
        $batches = $this->service->getBatchDetails($sku);

        $this->assertIsArray($batches);
        $this->assertCount(1, $batches);

        $batch = $batches[0];
        $this->assertSame('TEST-BATCH-001', $batch['batch_no']);
        $this->assertSame(100, $batch['quantity']);
        $this->assertSame(95, $batch['available_quantity']);
        $this->assertIsString($batch['created_at']);
    }

    public function testGetStockStatsReturnsValidData(): void
    {
        $stats = $this->service->getStockStats();

        $this->assertIsArray($stats);
        $this->assertSame(1, $stats['total_batches']);
        $this->assertSame(100, $stats['total_quantity']);
        $this->assertSame(95, $stats['available_quantity']);
        $this->assertSame(5, $stats['reserved_quantity']);
    }

    public function testCreateBatchThrowsMockServiceException(): void
    {
        $this->expectException(MockServiceException::class);
        $this->expectExceptionMessage('createBatch should not be called in tests');

        $sku = $this->createMock(SKU::class);
        $this->service->createBatch(['sku' => $sku, 'quantity' => 10]);
    }

    public function testMergeBatchesThrowsMockServiceException(): void
    {
        $this->expectException(MockServiceException::class);
        $this->expectExceptionMessage('mergeBatches should not be called in tests');

        $this->service->mergeBatches([], 'TARGET-BATCH');
    }

    public function testSplitBatchThrowsMockServiceException(): void
    {
        $this->expectException(MockServiceException::class);
        $this->expectExceptionMessage('splitBatch should not be called in tests');

        $batch = $this->createMock(StockBatch::class);
        $this->service->splitBatch($batch, 10, 'NEW-BATCH');
    }

    public function testProcessThrowsMockServiceException(): void
    {
        $this->expectException(MockServiceException::class);
        $this->expectExceptionMessage('process should not be called in tests');

        $log = $this->createMock(StockLog::class);
        $this->service->process($log);
    }
}
