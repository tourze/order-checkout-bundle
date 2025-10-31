<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\PriceCalculationItem;
use Tourze\OrderCheckoutBundle\Exception\InvalidCartItemException;
use Tourze\OrderCheckoutBundle\Exception\MissingRequiredFieldException;
use Tourze\ProductCoreBundle\Entity\Sku;

/**
 * @internal
 */
#[CoversClass(PriceCalculationItem::class)]
final class PriceCalculationItemTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $item = new PriceCalculationItem('sku-123', 5, false, null, 99.99);

        $this->assertEquals('sku-123', $item->getSkuId());
        $this->assertEquals(5, $item->getQuantity());
        $this->assertFalse($item->isSelected());
        $this->assertNull($item->getSku());
        $this->assertEquals(99.99, $item->getUnitPrice());
    }

    public function testConstructorWithDefaults(): void
    {
        $item = new PriceCalculationItem('sku-456', 3);

        $this->assertEquals('sku-456', $item->getSkuId());
        $this->assertEquals(3, $item->getQuantity());
        $this->assertTrue($item->isSelected()); // 默认为true
        $this->assertNull($item->getSku());
        $this->assertNull($item->getUnitPrice());
    }

    public function testFromArrayWithRequiredFields(): void
    {
        $data = [
            'skuId' => 'sku-789',
            'quantity' => 2,
            'selected' => false,
        ];

        $item = PriceCalculationItem::fromArray($data);

        $this->assertEquals('sku-789', $item->getSkuId());
        $this->assertEquals(2, $item->getQuantity());
        $this->assertFalse($item->isSelected());
        $this->assertNull($item->getSku());
        $this->assertNull($item->getUnitPrice());
    }

    public function testFromArrayWithDefaults(): void
    {
        $data = ['skuId' => 'sku-101', 'quantity' => 1];

        $item = PriceCalculationItem::fromArray($data);

        $this->assertEquals('sku-101', $item->getSkuId());
        $this->assertEquals(1, $item->getQuantity()); // 默认为1
        $this->assertTrue($item->isSelected()); // 默认为true
    }

    public function testFromArrayWithIdAsSkuId(): void
    {
        $data = ['id' => 'sku-202', 'quantity' => 3];

        $item = PriceCalculationItem::fromArray($data);

        $this->assertEquals('sku-202', $item->getSkuId());
        $this->assertEquals(3, $item->getQuantity());
    }

    public function testFromArrayThrowsExceptionWhenSkuIdMissing(): void
    {
        $this->expectException(MissingRequiredFieldException::class);
        $this->expectExceptionMessage('缺少必要字段: skuId');

        PriceCalculationItem::fromArray(['quantity' => 1]);
    }

    public function testFromCartItemWithValidObject(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('sku-303');

        $cartItem = new class($sku) {
            public function __construct(private readonly Sku $sku)
            {
            }

            public function getSku(): Sku
            {
                return $this->sku;
            }

            public function getQuantity(): int
            {
                return 4;
            }

            public function isSelected(): bool
            {
                return false;
            }
        };

        $item = PriceCalculationItem::fromCartItem($cartItem);

        $this->assertEquals('sku-303', $item->getSkuId());
        $this->assertEquals(4, $item->getQuantity());
        $this->assertFalse($item->isSelected());
        $this->assertSame($sku, $item->getSku());
    }

    public function testFromCartItemWithoutIsSelectedMethod(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('sku-404');

        $cartItem = new class($sku) {
            public function __construct(private readonly Sku $sku)
            {
            }

            public function getSku(): Sku
            {
                return $this->sku;
            }

            public function getQuantity(): int
            {
                return 2;
            }
        };

        $item = PriceCalculationItem::fromCartItem($cartItem);

        $this->assertEquals('sku-404', $item->getSkuId());
        $this->assertEquals(2, $item->getQuantity());
        $this->assertTrue($item->isSelected()); // 默认为true
    }

    public function testFromCartItemWithNullSku(): void
    {
        $cartItem = new class {
            public function getSku(): null
            {
                return null;
            }

            public function getQuantity(): int
            {
                return 1;
            }
        };

        $item = PriceCalculationItem::fromCartItem($cartItem);

        $this->assertEquals('0', $item->getSkuId()); // null SKU 使用 '0'
        $this->assertEquals(1, $item->getQuantity());
        $this->assertNull($item->getSku());
    }

    public function testFromCartItemThrowsExceptionForInvalidObject(): void
    {
        $this->expectException(InvalidCartItemException::class);

        $invalidCartItem = new \stdClass();
        PriceCalculationItem::fromCartItem($invalidCartItem);
    }

    public function testWithSku(): void
    {
        $originalItem = new PriceCalculationItem('sku-505', 3, false, null, 50.0);

        $sku = $this->createMock(Sku::class);
        $newItem = $originalItem->withSku($sku);

        // 验证原始对象没有改变
        $this->assertNull($originalItem->getSku());

        // 验证新对象有 SKU
        $this->assertSame($sku, $newItem->getSku());
        $this->assertEquals('sku-505', $newItem->getSkuId());
        $this->assertEquals(3, $newItem->getQuantity());
        $this->assertFalse($newItem->isSelected());
        $this->assertEquals(50.0, $newItem->getUnitPrice());
    }

    public function testGetEffectiveUnitPriceWithoutSku(): void
    {
        $item = new PriceCalculationItem('sku-606', 1);

        $this->assertEquals(0.0, $item->getEffectiveUnitPrice());
    }

    public function testGetEffectiveUnitPriceWithSkuButNoPrice(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getMarketPrice')->willReturn(null);

        $item = new PriceCalculationItem('sku-707', 1, true, $sku);

        $this->assertEquals(0.0, $item->getEffectiveUnitPrice());
    }

    public function testGetEffectiveUnitPriceWithSkuAndPrice(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getMarketPrice')->willReturn('123.45');

        $item = new PriceCalculationItem('sku-808', 1, true, $sku);

        $this->assertEquals(123.45, $item->getEffectiveUnitPrice());
    }

    public function testGetEffectiveUnitPriceWithNullPrice(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getMarketPrice')->willReturn(null);

        $item = new PriceCalculationItem('sku-909', 1, true, $sku);

        $this->assertEquals(0.0, $item->getEffectiveUnitPrice());
    }

    public function testGetSubtotal(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getMarketPrice')->willReturn('25.50');

        $item = new PriceCalculationItem('sku-1010', 4, true, $sku);

        $this->assertEquals(102.0, $item->getSubtotal()); // 25.50 * 4
    }

    public function testGetSubtotalWithZeroPrice(): void
    {
        $item = new PriceCalculationItem('sku-1111', 10);

        $this->assertEquals(0.0, $item->getSubtotal()); // 0.0 * 10
    }
}
