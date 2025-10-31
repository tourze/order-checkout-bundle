<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\Exception\InvalidCartItemException;
use Tourze\OrderCheckoutBundle\Exception\MissingRequiredFieldException;
use Tourze\ProductCoreBundle\Entity\Sku;

/**
 * @internal
 */
#[CoversClass(CheckoutItem::class)]
final class CheckoutItemTest extends TestCase
{
    public function testCheckoutItemCanBeInstantiated(): void
    {
        $item = new CheckoutItem(skuId: 123, quantity: 2);

        $this->assertInstanceOf(CheckoutItem::class, $item);
        $this->assertEquals(123, $item->getSkuId());
        $this->assertEquals(2, $item->getQuantity());
        $this->assertTrue($item->isSelected());
        $this->assertNull($item->getSku());
        $this->assertNull($item->getId());
    }

    public function testConstructorWithAllParameters(): void
    {
        $sku = $this->createMock(Sku::class);
        $item = new CheckoutItem(
            skuId: 'ABC123',
            quantity: 5,
            selected: false,
            sku: $sku,
            id: 999
        );

        $this->assertEquals('ABC123', $item->getSkuId());
        $this->assertEquals(5, $item->getQuantity());
        $this->assertFalse($item->isSelected());
        $this->assertSame($sku, $item->getSku());
        $this->assertEquals(999, $item->getId());
    }

    public function testGetSkuId(): void
    {
        $item = new CheckoutItem(skuId: 'SKU456', quantity: 1);

        $this->assertEquals('SKU456', $item->getSkuId());
    }

    public function testGetSkuIdWithInteger(): void
    {
        $item = new CheckoutItem(skuId: 789, quantity: 3);

        $this->assertEquals(789, $item->getSkuId());
    }

    public function testGetQuantity(): void
    {
        $item = new CheckoutItem(skuId: 'TEST', quantity: 10);

        $this->assertEquals(10, $item->getQuantity());
    }

    public function testIsSelectedDefaultTrue(): void
    {
        $item = new CheckoutItem(skuId: 'TEST', quantity: 1);

        $this->assertTrue($item->isSelected());
    }

    public function testIsSelectedFalse(): void
    {
        $item = new CheckoutItem(skuId: 'TEST', quantity: 1, selected: false);

        $this->assertFalse($item->isSelected());
    }

    public function testGetSkuReturnsNull(): void
    {
        $item = new CheckoutItem(skuId: 'TEST', quantity: 1);

        $this->assertNull($item->getSku());
    }

    public function testGetSkuReturnsSku(): void
    {
        $sku = $this->createMock(Sku::class);
        $item = new CheckoutItem(skuId: 'TEST', quantity: 1, sku: $sku);

        $this->assertSame($sku, $item->getSku());
    }

    public function testGetIdReturnsNull(): void
    {
        $item = new CheckoutItem(skuId: 'TEST', quantity: 1);

        $this->assertNull($item->getId());
    }

    public function testGetIdReturnsId(): void
    {
        $item = new CheckoutItem(skuId: 'TEST', quantity: 1, id: 42);

        $this->assertEquals(42, $item->getId());
    }

    public function testFromArrayWithMinimalData(): void
    {
        $data = ['skuId' => 123];
        $item = CheckoutItem::fromArray($data);

        $this->assertEquals(123, $item->getSkuId());
        $this->assertEquals(1, $item->getQuantity());
        $this->assertTrue($item->isSelected());
        $this->assertNull($item->getId());
    }

    public function testFromArrayWithAllData(): void
    {
        $data = [
            'id' => 999,
            'skuId' => 'ABC123',
            'quantity' => 5,
            'selected' => false,
        ];
        $item = CheckoutItem::fromArray($data);

        $this->assertEquals('ABC123', $item->getSkuId());
        $this->assertEquals(5, $item->getQuantity());
        $this->assertFalse($item->isSelected());
        $this->assertEquals(999, $item->getId());
    }

    public function testFromArrayThrowsExceptionWhenSkuIdMissing(): void
    {
        $data = ['quantity' => 2];

        $this->expectException(MissingRequiredFieldException::class);
        $this->expectExceptionMessage('缺少必要字段: skuId');

        CheckoutItem::fromArray($data);
    }

    public function testFromArrayThrowsExceptionWhenSkuIdIsNull(): void
    {
        $data = ['quantity' => 2];

        $this->expectException(MissingRequiredFieldException::class);
        $this->expectExceptionMessage('缺少必要字段: skuId');

        CheckoutItem::fromArray($data);
    }

    public function testFromArrayWithZeroQuantity(): void
    {
        $data = ['skuId' => 123, 'quantity' => 0];
        $item = CheckoutItem::fromArray($data);

        $this->assertEquals(0, $item->getQuantity());
    }

    public function testFromCartItemWithValidCartItem(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->expects($this->once())->method('getId')->willReturn('456');

        $cartItem = new class($sku) {
            private Sku $sku;

            public function __construct(Sku $sku)
            {
                $this->sku = $sku;
            }

            public function getSku(): Sku
            {
                return $this->sku;
            }

            public function getQuantity(): int
            {
                return 3;
            }

            public function isSelected(): bool
            {
                return false;
            }

            public function getId(): int
            {
                return 789;
            }
        };

        $item = CheckoutItem::fromCartItem($cartItem);

        $this->assertEquals(456, $item->getSkuId());
        $this->assertEquals(3, $item->getQuantity());
        $this->assertFalse($item->isSelected());
        $this->assertSame($sku, $item->getSku());
        $this->assertEquals(789, $item->getId());
    }

    public function testFromCartItemWithNullSku(): void
    {
        // Create a mock cart item using a simple object implementation
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

        $item = CheckoutItem::fromCartItem($cartItem);

        $this->assertEquals('0', $item->getSkuId());
        $this->assertEquals(1, $item->getQuantity());
        $this->assertTrue($item->isSelected());
        $this->assertNull($item->getSku());
        $this->assertNull($item->getId());
    }

    public function testFromCartItemWithQuantity(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->expects($this->once())->method('getId')->willReturn('123');

        $cartItem = new class($sku) {
            private Sku $sku;

            public function __construct(Sku $sku)
            {
                $this->sku = $sku;
            }

            public function getSku(): Sku
            {
                return $this->sku;
            }

            public function getQuantity(): int
            {
                return 1;
            }
        };

        $item = CheckoutItem::fromCartItem($cartItem);

        $this->assertEquals(1, $item->getQuantity());
    }

    public function testFromCartItemWithoutSelectedMethod(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->expects($this->once())->method('getId')->willReturn('123');

        $cartItem = new class($sku) {
            private Sku $sku;

            public function __construct(Sku $sku)
            {
                $this->sku = $sku;
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

        $item = CheckoutItem::fromCartItem($cartItem);

        $this->assertTrue($item->isSelected());
    }

    public function testFromCartItemWithoutIdMethod(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->expects($this->once())->method('getId')->willReturn('123');

        $cartItem = new class($sku) {
            private Sku $sku;

            public function __construct(Sku $sku)
            {
                $this->sku = $sku;
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

        $item = CheckoutItem::fromCartItem($cartItem);

        $this->assertNull($item->getId());
    }

    public function testFromCartItemThrowsExceptionWithInvalidCartItem(): void
    {
        $cartItem = new class {
            // Missing required methods
        };

        $this->expectException(InvalidCartItemException::class);

        CheckoutItem::fromCartItem($cartItem);
    }

    public function testFromCartItemThrowsExceptionWhenMissingGetSku(): void
    {
        $cartItem = new class {
            public function getQuantity(): int
            {
                return 1;
            }
        };

        $this->expectException(InvalidCartItemException::class);

        CheckoutItem::fromCartItem($cartItem);
    }

    public function testFromCartItemThrowsExceptionWhenMissingGetQuantity(): void
    {
        // Create a cart item that only has getSku method, missing getQuantity
        $cartItem = new class {
            public function getSku(): null
            {
                return null;
            }
            // Intentionally not implementing getQuantity to trigger the exception
        };

        $this->expectException(InvalidCartItemException::class);

        CheckoutItem::fromCartItem($cartItem);
    }

    public function testWithSku(): void
    {
        $sku1 = $this->createMock(Sku::class);
        $sku2 = $this->createMock(Sku::class);

        $originalItem = new CheckoutItem(skuId: 123, quantity: 2, sku: $sku1, id: 456);
        $newItem = $originalItem->withSku($sku2);

        // Original item should be unchanged
        $this->assertSame($sku1, $originalItem->getSku());

        // New item should have the new SKU but same other properties
        $this->assertSame($sku2, $newItem->getSku());
        $this->assertEquals(123, $newItem->getSkuId());
        $this->assertEquals(2, $newItem->getQuantity());
        $this->assertTrue($newItem->isSelected());
        $this->assertEquals(456, $newItem->getId());

        // Should be different instances
        $this->assertNotSame($originalItem, $newItem);
    }

    public function testWithSkuPreservesAllProperties(): void
    {
        $sku = $this->createMock(Sku::class);
        $originalItem = new CheckoutItem(skuId: 'ABC', quantity: 10, selected: false, id: 999);
        $newItem = $originalItem->withSku($sku);

        $this->assertEquals('ABC', $newItem->getSkuId());
        $this->assertEquals(10, $newItem->getQuantity());
        $this->assertFalse($newItem->isSelected());
        $this->assertEquals(999, $newItem->getId());
        $this->assertSame($sku, $newItem->getSku());
    }

    public function testToArray(): void
    {
        $sku = $this->createMock(Sku::class);
        $item = new CheckoutItem(skuId: 'TEST', quantity: 3, selected: false, sku: $sku, id: 789);

        $array = $item->toArray();

        $expected = [
            'id' => 789,
            'skuId' => 'TEST',
            'quantity' => 3,
            'selected' => false,
            'sku' => $sku,
        ];

        $this->assertEquals($expected, $array);
    }

    public function testToArrayWithNullValues(): void
    {
        $item = new CheckoutItem(skuId: 123, quantity: 1);

        $array = $item->toArray();

        $expected = [
            'id' => null,
            'skuId' => 123,
            'quantity' => 1,
            'selected' => true,
            'sku' => null,
        ];

        $this->assertEquals($expected, $array);
    }

    public function testToArrayWithStringSkuId(): void
    {
        $item = new CheckoutItem(skuId: 'SKU_STRING', quantity: 2, selected: true, id: 100);

        $array = $item->toArray();

        $this->assertEquals('SKU_STRING', $array['skuId']);
        $this->assertEquals(2, $array['quantity']);
        $this->assertTrue($array['selected']);
        $this->assertEquals(100, $array['id']);
        $this->assertNull($array['sku']);
    }

    public function testImmutability(): void
    {
        $sku1 = $this->createMock(Sku::class);
        $sku2 = $this->createMock(Sku::class);

        $original = new CheckoutItem(skuId: 123, quantity: 1, sku: $sku1);
        $modified = $original->withSku($sku2);

        // Objects should be different
        $this->assertNotSame($original, $modified);

        // Original should be unchanged
        $this->assertSame($sku1, $original->getSku());
        $this->assertEquals(123, $original->getSkuId());

        // Modified should have new values
        $this->assertSame($sku2, $modified->getSku());
        $this->assertEquals(123, $modified->getSkuId());
    }
}
