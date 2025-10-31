<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Calculator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Calculator\BasePriceCalculator;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\PriceCalculationItem;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Entity\Spu;
use Tourze\ProductServiceContracts\SkuLoaderInterface;

/**
 * @internal
 */
#[CoversClass(BasePriceCalculator::class)]
final class BasePriceCalculatorTest extends TestCase
{
    private SkuLoaderInterface $skuLoader;

    protected function setUp(): void
    {
        $this->skuLoader = $this->createMock(SkuLoaderInterface::class);
    }

    public function testBasePriceCalculatorCanBeInstantiated(): void
    {
        $calculator = new BasePriceCalculator($this->skuLoader);

        $this->assertInstanceOf(BasePriceCalculator::class, $calculator);
    }

    public function testCalculateReturnsPriceResult(): void
    {
        $calculator = new BasePriceCalculator($this->skuLoader);
        $context = $this->createMock(CalculationContext::class);
        $context->method('getItems')->willReturn([]);

        $result = $calculator->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
    }

    public function testSupportsWithEmptyItems(): void
    {
        $calculator = new BasePriceCalculator($this->skuLoader);
        $context = $this->createMock(CalculationContext::class);
        $context->method('getItems')->willReturn([]);

        $this->assertFalse($calculator->supports($context));
    }

    public function testSupportsWithItems(): void
    {
        $calculator = new BasePriceCalculator($this->skuLoader);
        $context = $this->createMock(CalculationContext::class);
        $context->method('getItems')->willReturn(['some_item']);

        $this->assertTrue($calculator->supports($context));
    }

    public function testGetPriority(): void
    {
        $calculator = new BasePriceCalculator($this->skuLoader);

        $this->assertSame(1000, $calculator->getPriority());
    }

    public function testGetType(): void
    {
        $calculator = new BasePriceCalculator($this->skuLoader);

        $this->assertSame('base_price', $calculator->getType());
    }

    public function testCalculateWithProductsInfo(): void
    {
        $calculator = new BasePriceCalculator($this->skuLoader);

        // Mock SKU
        $sku = $this->createMock(Sku::class);
        $spu = $this->createMock(Spu::class);

        $sku->method('getMarketPrice')->willReturn('99.99');
        $sku->method('getSpu')->willReturn($spu);
        $sku->method('getMainThumb')->willReturn('https://example.com/thumb.jpg');
        $sku->method('getFullName')->willReturn('Test Product - Red');
        $sku->method('getDisplayAttribute')->willReturn('颜色红色');
        $spu->method('getId')->willReturn(456);

        // Mock calculation item
        $item = $this->createMock(PriceCalculationItem::class);
        $item->method('getSkuId')->willReturn('123');
        $item->method('getQuantity')->willReturn(2);
        $item->method('isSelected')->willReturn(true);
        $item->method('getSku')->willReturn($sku);
        $item->method('getEffectiveUnitPrice')->willReturn('99.99');
        $item->method('getSubtotal')->willReturn('199.98');

        $context = $this->createMock(CalculationContext::class);
        $context->method('getItems')->willReturn([$item]);

        $result = $calculator->calculate($context);

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertSame('199.98', $result->getOriginalPrice());
        $this->assertSame('199.98', $result->getFinalPrice());

        // 检验 products属性
        $products = $result->getProducts();
        $this->assertCount(1, $products);

        $product = $products[0];
        $this->assertSame('123', $product['skuId']);
        $this->assertSame(456, $product['spuId']);
        $this->assertSame(2, $product['quantity']);
        $this->assertSame('199.98', $product['payablePrice']);
        $this->assertSame('99.99', $product['unitPrice']);
        $this->assertSame('https://example.com/thumb.jpg', $product['mainThumb']);
        $this->assertSame('Test Product - Red', $product['productName']);
        $this->assertSame('颜色红色', $product['specifications']);
    }

    public function testCalculateWithUnselectedItemsExcludesFromProducts(): void
    {
        $calculator = new BasePriceCalculator($this->skuLoader);

        // Mock a SKU for the unselected item
        $sku = $this->createMock(Sku::class);

        $item = $this->createMock(PriceCalculationItem::class);
        $item->method('isSelected')->willReturn(false);
        $item->method('getSku')->willReturn($sku);

        $context = $this->createMock(CalculationContext::class);
        $context->method('getItems')->willReturn([$item]);

        $result = $calculator->calculate($context);

        $this->assertSame([], $result->getProducts());
        $this->assertSame('0.00', $result->getOriginalPrice());
        $this->assertSame('0.00', $result->getFinalPrice());
    }
}
