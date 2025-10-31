<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Test;

use Tourze\OrderCheckoutBundle\Exception\MockServiceException;
use Tourze\ProductServiceContracts\SKU;
use Tourze\StockManageBundle\Entity\StockBatch;
use Tourze\StockManageBundle\Entity\StockLog;
use Tourze\StockManageBundle\Model\StockSummary;
use Tourze\StockManageBundle\Service\StockServiceInterface;

/**
 * 测试用的Mock库存服务
 *
 * 按照Linus "Good Taste"原则：
 * - 简单直接，没有复杂的逻辑
 * - 返回合理的测试数据
 * - 不处理特殊情况，保持一致性
 */
final class MockStockService implements StockServiceInterface
{
    private const DEFAULT_STOCK_QUANTITY = 100;
    private const DEFAULT_AVAILABLE_QUANTITY = 95;

    public function createBatch(array $data): StockBatch
    {
        throw MockServiceException::methodShouldNotBeCalled('createBatch');
    }

    public function getAvailableStock(SKU $sku, array $criteria = []): StockSummary
    {
        $summary = new StockSummary($sku->getId());
        $summary->setTotalQuantity(self::DEFAULT_STOCK_QUANTITY);
        $summary->setAvailableQuantity(self::DEFAULT_AVAILABLE_QUANTITY);
        $summary->setReservedQuantity(5);

        return $summary;
    }

    public function mergeBatches(array $batches, string $targetBatchNo): StockBatch
    {
        throw MockServiceException::methodShouldNotBeCalled('mergeBatches');
    }

    public function splitBatch(StockBatch $batch, int $splitQuantity, string $newBatchNo): StockBatch
    {
        throw MockServiceException::methodShouldNotBeCalled('splitBatch');
    }

    public function checkStockAvailability(SKU $sku, int $quantity, array $criteria = []): bool
    {
        return $quantity <= self::DEFAULT_AVAILABLE_QUANTITY;
    }

    public function getBatchDetails(SKU $sku): array
    {
        return [
            [
                'batch_no' => 'TEST-BATCH-001',
                'quantity' => self::DEFAULT_STOCK_QUANTITY,
                'available_quantity' => self::DEFAULT_AVAILABLE_QUANTITY,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        ];
    }

    public function getStockStats(): array
    {
        return [
            'total_batches' => 1,
            'total_quantity' => self::DEFAULT_STOCK_QUANTITY,
            'available_quantity' => self::DEFAULT_AVAILABLE_QUANTITY,
            'reserved_quantity' => 5,
        ];
    }

    public function process(StockLog $log): void
    {
        throw MockServiceException::methodShouldNotBeCalled('process');
    }
}
