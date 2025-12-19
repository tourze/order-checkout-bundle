<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Calculator;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Tourze\OrderCheckoutBundle\Contract\PriceCalculatorInterface;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\DTO\PriceCalculationItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\Exception\InvalidSkuTypeException;
use Tourze\OrderCheckoutBundle\Exception\SkuNotFoundException;
use Tourze\OrderCheckoutBundle\Exception\UnsupportedItemTypeException;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductServiceContracts\SkuLoaderInterface;

/**
 * 基础价格计算器
 * 负责计算商品的原价总和
 */
#[AutoconfigureTag(name: 'order_checkout.price_calculator')]
#[WithMonologChannel(channel: 'order_checkout')]
final class BasePriceCalculator implements PriceCalculatorInterface
{
    public function __construct(
        private readonly SkuLoaderInterface $skuLoader,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function calculate(CalculationContext $context): PriceResult
    {
        $this->logger->debug('[基础计价器] 开始计算基础价格', [
            'itemsCount' => count($context->getItems()),
            'userId' => $context->getUser()->getUserIdentifier(),
        ]);

        /** @var numeric-string $totalPrice */
        $totalPrice = '0.00';
        $totalIntegral = 0;
        $details = [];
        $products = [];

        // 获取支付模式
        $paymentMode = $context->getMetadataValue('paymentMode', 'CASH_ONLY');
        $useIntegralAmount = $context->getMetadataValue('useIntegralAmount', 0);

        foreach ($context->getItems() as $index => $item) {
            $calculationItem = $this->processItem($item, $index);
            if (null === $calculationItem) {
                continue;
            }

            $itemResult = $this->calculateItemPrice($calculationItem, $index, $paymentMode, $useIntegralAmount);
            $totalPrice = bcadd($totalPrice, $itemResult['itemTotal'], 2);
            $totalIntegral += $itemResult['totalIntegralForItem'];
            $details[] = $itemResult['detail'];

            $productInfo = $this->collectProductInfo($calculationItem, $itemResult);
            if (null !== $productInfo) {
                $products[] = $productInfo;
            }
        }

        return $this->buildResult($totalPrice, $totalIntegral, $details, $products);
    }

    /**
     * 处理单个商品项，返回规范化后的计算项，未选中返回 null
     */
    private function processItem(mixed $item, int $index): ?PriceCalculationItem
    {
        $this->logger->debug("[基础计价器] 处理商品 #{$index}", [
            'itemType' => get_class($item),
            'itemData' => method_exists($item, 'toArray') ? $item->toArray() : 'N/A',
        ]);

        $calculationItem = $this->normalizeItem($item);

        if (!$calculationItem->isSelected()) {
            $this->logger->debug("[基础计价器] 商品未选中，跳过 #{$index}", [
                'skuId' => $calculationItem->getSkuId(),
            ]);
            return null;
        }

        return $this->loadSkuForItem($calculationItem, $index);
    }

    /**
     * 为计算项加载 SKU 数据
     */
    private function loadSkuForItem(PriceCalculationItem $calculationItem, int $index): PriceCalculationItem
    {
        try {
            $calculationItem = $this->ensureSkuLoaded($calculationItem);
            $this->logger->debug("[基础计价器] SKU加载完成 #{$index}", [
                'skuId' => $calculationItem->getSkuId(),
                'skuLoaded' => $calculationItem->getSku() !== null,
            ]);
            return $calculationItem;
        } catch (\Exception $e) {
            $this->logger->error("[基础计价器] SKU加载失败 #{$index}", [
                'skuId' => $calculationItem->getSkuId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 计算单个商品的价格
     *
     * @return array{itemTotal: numeric-string, totalIntegralForItem: int, integralRequired: int, unitPrice: string, quantity: int, detail: array<string, mixed>}
     */
    private function calculateItemPrice(PriceCalculationItem $calculationItem, int $index, string $paymentMode = 'CASH_ONLY', int $useIntegralAmount = 0): array
    {
        $quantity = $calculationItem->getQuantity();
        $sku = $calculationItem->getSku();

        // 根据支付模式计算价格
        [$unitPrice, $itemTotal, $integralRequired, $totalIntegralForItem] = $this->calculatePriceByMode(
            $sku,
            $quantity,
            $paymentMode,
            $useIntegralAmount
        );

        $this->logger->debug("[基础计价器] 价格计算 #{$index}", [
            'skuId' => $calculationItem->getSkuId(),
            'paymentMode' => $paymentMode,
            'unitPrice' => $unitPrice,
            'quantity' => $quantity,
            'itemTotal' => $itemTotal,
            'integralRequired' => $integralRequired,
            'totalIntegralForItem' => $totalIntegralForItem,
        ]);

        return [
            'itemTotal' => $itemTotal,
            'totalIntegralForItem' => $totalIntegralForItem,
            'integralRequired' => $integralRequired,
            'unitPrice' => $unitPrice,
            'quantity' => $quantity,
            'detail' => [
                'type' => 'base_price',
                'sku_id' => $calculationItem->getSkuId(),
                'sku_code' => (null !== $sku) ? ($sku->getGtin() ?? $sku->getMpn() ?? '') : '',
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'total_price' => $itemTotal,
                'integral_required' => $integralRequired,
                'total_integral' => $totalIntegralForItem,
                'payment_mode' => $paymentMode,
            ],
        ];
    }

    /**
     * 收集产品信息
     *
     * @param array{itemTotal: numeric-string, totalIntegralForItem: int, integralRequired: int, unitPrice: string, quantity: int, detail: array<string, mixed>} $itemResult
     * @return array<string, mixed>|null
     */
    private function collectProductInfo(PriceCalculationItem $calculationItem, array $itemResult): ?array
    {
        $sku = $calculationItem->getSku();
        if (null === $sku) {
            $this->logger->warning('[基础计价器] SKU为空，无法收集产品信息', [
                'skuId' => $calculationItem->getSkuId(),
            ]);
            return null;
        }

        return [
            'skuId' => $calculationItem->getSkuId(),
            'spuId' => $sku->getSpu()?->getId(),
            'quantity' => $itemResult['quantity'],
            'payablePrice' => $itemResult['itemTotal'],
            'unitPrice' => $itemResult['unitPrice'],
            'mainThumb' => $sku->getMainThumb(),
            'productName' => $sku->getFullName(),
            'specifications' => $sku->getDisplayAttribute(),
            'integralPrice' => $itemResult['integralRequired'],
            'totalIntegral' => $itemResult['totalIntegralForItem'],
        ];
    }

    /**
     * 构建计算结果
     *
     * @param numeric-string $totalPrice
     * @param array<int, array<string, mixed>> $details
     * @param array<int, array<string, mixed>> $products
     */
    private function buildResult(string $totalPrice, int $totalIntegral, array $details, array $products): PriceResult
    {
        $this->logger->debug('[基础计价器] 基础价格计算完成', [
            'totalPrice' => $totalPrice,
            'totalIntegral' => $totalIntegral,
            'detailsCount' => count($details),
            'productsCount' => count($products),
        ]);

        return new PriceResult(
            originalPrice: $totalPrice,
            finalPrice: $totalPrice,
            discount: '0.00',
            details: [
                'base_price' => $details,
                'base_total' => $totalPrice,
                'total_integral_required' => $totalIntegral,
            ],
            products: $products
        );
    }

    /**
     * 将不同格式的商品数据规范化为 PriceCalculationItem
     */
    private function normalizeItem(mixed $item): PriceCalculationItem
    {
        // 如果已经是 PriceCalculationItem，直接返回
        if ($item instanceof PriceCalculationItem) {
            return $this->ensureSkuLoaded($item);
        }

        // 如果是 CheckoutItem，使用专门的转换方法
        if ($item instanceof CheckoutItem) {
            return new PriceCalculationItem(
                skuId: $item->getSkuId(),
                quantity: $item->getQuantity(),
                selected: $item->isSelected(),
                sku: $item->getSku()
            );
        }

        // 如果是 CartItem 等其他实体
        if (is_object($item) && method_exists($item, 'getSku') && method_exists($item, 'getQuantity')) {
            return PriceCalculationItem::fromCartItem($item);
        }

        // 如果是数组格式
        if (is_array($item)) {
            /** @var array{id?: int, skuId: int|string, quantity: int, selected?: bool} $item */
            return PriceCalculationItem::fromArray($item);
        }

        throw new UnsupportedItemTypeException(get_debug_type($item));
    }

    /**
     * 确保 PriceCalculationItem 包含 SKU 信息（始终加载以获取准确价格）
     */
    private function ensureSkuLoaded(PriceCalculationItem $item): PriceCalculationItem
    {
        $skuId = (string) $item->getSkuId();
        
        $this->logger->debug('[SKU加载器] 开始加载SKU', [
            'skuId' => $skuId,
            'hasExistingSku' => $item->getSku() !== null,
        ]);

        // 如果已经有 SKU 信息，直接返回
        if (null !== $item->getSku()) {
            $this->logger->debug('[SKU加载器] SKU已存在，无需加载', [
                'skuId' => $skuId,
            ]);
            return $item;
        }

        // 总是加载 SKU 以获取最新价格
        $this->logger->debug('[SKU加载器] 从数据库加载SKU', [
            'skuId' => $skuId,
        ]);

        $sku = $this->skuLoader->loadSkuByIdentifier($skuId);
        
        if (null === $sku) {
            $this->logger->error('[SKU加载器] SKU未找到', [
                'skuId' => $skuId,
            ]);
            throw new SkuNotFoundException($skuId);
        }

        $this->logger->debug('[SKU加载器] SKU加载成功', [
            'skuId' => $skuId,
            'skuType' => get_class($sku),
            'skuDetails' => [
                'id' => method_exists($sku, 'getId') ? $sku->getId() : 'N/A',
                'gtin' => method_exists($sku, 'getGtin') ? $sku->getGtin() : 'N/A',
                'marketPrice' => method_exists($sku, 'getMarketPrice') ? $sku->getMarketPrice() : 'N/A',
                'originalPrice' => method_exists($sku, 'getOriginalPrice') ? $sku->getOriginalPrice() : 'N/A',
                'valid' => method_exists($sku, 'getValid') ? $sku->getValid() : 'N/A',
            ],
        ]);

        // 类型转换：SkuLoaderInterface 返回的是接口类型，需要转换为实体类型
        if (!$sku instanceof Sku) {
            $this->logger->error('[SKU加载器] SKU类型错误', [
                'skuId' => $skuId,
                'expectedType' => Sku::class,
                'actualType' => get_class($sku),
            ]);
            throw new InvalidSkuTypeException(get_class($sku));
        }

        $resultItem = $item->withSku($sku);
        $this->logger->debug('[SKU加载器] SKU加载完成', [
            'skuId' => $skuId,
        ]);

        return $resultItem;
    }

    public function supports(CalculationContext $context): bool
    {
        // 基础价格计算器始终支持
        return count($context->getItems()) > 0;
    }

    public function getPriority(): int
    {
        // 最高优先级，首先计算基础价格
        return 1000;
    }

    public function getType(): string
    {
        return 'base_price';
    }

    /**
     * 根据支付模式计算价格
     *
     * @return array{string, string, int, int} [unitPrice, itemTotal, integralRequired, totalIntegralForItem]
     */
    private function calculatePriceByMode(?Sku $sku, int $quantity, string $paymentMode, int $useIntegralAmount): array
    {
        if (null === $sku) {
            return ['0.00', '0.00', 0, 0];
        }

        $marketPrice = $sku->getMarketPrice() ?? '0.00';
        $integralPrice = $sku->getIntegralPrice() ?? 0;

        return match ($paymentMode) {
            'INTEGRAL_ONLY' => $this->calculateIntegralOnlyPrice($integralPrice, $quantity),
            'MIXED' => $this->calculateMixedPrice($marketPrice, $integralPrice, $quantity, $useIntegralAmount),
            default => $this->calculateCashOnlyPrice($marketPrice, $quantity),
        };
    }

    /**
     * @return array{string, string, int, int}
     */
    private function calculateIntegralOnlyPrice(int $integralPrice, int $quantity): array
    {
        return [
            '0.00',
            '0.00',
            $integralPrice,
            $integralPrice * $quantity,
        ];
    }

    /**
     * @return array{string, string, int, int}
     */
    private function calculateMixedPrice(string $marketPrice, int $integralPrice, int $quantity, int $useIntegralAmount): array
    {
        if ($integralPrice <= 0 || $useIntegralAmount <= 0) {
            return $this->calculateCashOnlyPrice($marketPrice, $quantity);
        }

        $integralUsedForThisItem = min($integralPrice * $quantity, $useIntegralAmount);
        $integralRatio = $integralUsedForThisItem / ($integralPrice * $quantity);
        $cashRatio = 1.0 - $integralRatio;

        $unitPrice = bcmul($marketPrice, (string)$cashRatio, 2);
        $itemTotal = bcmul($unitPrice, (string)$quantity, 2);
        $integralRequired = (int)($integralPrice * $integralRatio);
        $totalIntegralForItem = $integralRequired * $quantity;

        return [$unitPrice, $itemTotal, $integralRequired, $totalIntegralForItem];
    }

    /**
     * @return array{string, string, int, int}
     */
    private function calculateCashOnlyPrice(string $marketPrice, int $quantity): array
    {
        $unitPrice = $marketPrice;
        $itemTotal = bcmul($unitPrice, (string)$quantity, 2);

        return [$unitPrice, $itemTotal, 0, 0];
    }
}
