<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Tourze\OrderCheckoutBundle\Contract\StockValidatorInterface;
use Tourze\OrderCheckoutBundle\DTO\StockValidationResult;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\ProductServiceContracts\SKU as SkuContract;
use Tourze\StockManageBundle\Service\StockServiceInterface;

/**
 * 基础库存验证器
 * 这是一个简单的实现，实际项目中应该对接真实的库存系统
 */
#[WithMonologChannel(channel: 'order_checkout')]
readonly class BasicStockValidator implements StockValidatorInterface
{
    public function __construct(
        private StockServiceInterface $stockService,
        private LoggerInterface $logger,
    ) {
    }

    public function validate(array $items): StockValidationResult
    {
        $errors = [];
        $warnings = [];
        $details = [];

        foreach ($items as $item) {
            $this->logger->debug('检查库存信息', [
                'skuId' => $item->getSkuId(),
                'item' => $item->toArray(),
            ]);
            if (false === $item->isSelected()) {
                continue;
            }

            $sku = $item->getSku();
            if (null === $sku) {
                continue; // 如果SKU不存在，跳过该项
            }
            $spu = $sku->getSpu();
            $spuIsValid = (null === $spu) ? false : (bool) $spu->isValid();
            if (false === $sku->isValid() || false === $spuIsValid) {
                // 商品已下架
                throw new CheckoutException("商品 {$sku->getFullName()} 已下架");
            }

            $skuId = (string) $sku->getId();
            $requestedQuantity = $item->getQuantity() ?? 0;
            $availableQuantity = $this->getAvailableQuantity($sku);

            $skuCode = $sku->getGtin() ?? $sku->getMpn() ?? $skuId;
            $skuName = $sku->getFullName();

            $details[$skuId] = [
                'sku_code' => $skuCode,
                'sku_name' => $skuName,
                'requested_quantity' => $requestedQuantity,
                'available_quantity' => $availableQuantity,
            ];

            // 检查库存充足性
            if ($availableQuantity <= 0) {
                $errors[$skuId] = "商品 {$skuName} 库存不足";
            } elseif ($requestedQuantity > $availableQuantity) {
                $errors[$skuId] = "商品 {$skuName} 库存不足";
            } elseif ($availableQuantity < 10) {
                // 库存较少时给出警告
                $warnings[$skuId] = "商品 {$skuName} 库存较少";
            }
        }

        if (0 === count($errors)) {
            return StockValidationResult::success($details, $warnings);
        }

        return StockValidationResult::failure($errors, $warnings, $details);
    }

    /**
     * 获取可用库存数量
     * 直接查询库存，不使用缓存
     *
     * @param SkuContract $sku SKU对象
     * @return int 可用库存数量
     */
    public function getAvailableQuantity(SkuContract $sku): int
    {
        // 这里要返回真实库存
        $stockSummary = $this->stockService->getAvailableStock($sku);

        return $stockSummary->getAvailableQuantity();
    }

    public function getAvailableQuantities(array $skus): array
    {
        $quantities = [];
        foreach ($skus as $sku) {
            $skuId = (string) $sku->getId();
            $quantities[$skuId] = $this->getAvailableQuantity($sku);
        }

        return $quantities;
    }
}
