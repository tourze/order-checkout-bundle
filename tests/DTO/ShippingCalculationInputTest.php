<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationInput;
use Tourze\OrderCheckoutBundle\DTO\ShippingCalculationItem;

/**
 * @internal
 */
#[CoversClass(ShippingCalculationInput::class)]
final class ShippingCalculationInputTest extends TestCase
{
    public function testGetTotalWeight(): void
    {
        $items = [
            new ShippingCalculationItem('product1', 2, '1.500', '10.00'),
            new ShippingCalculationItem('product2', 1, '0.800', '15.00'),
        ];

        $input = new ShippingCalculationInput('address1', $items);

        $this->assertSame('3.800', $input->getTotalWeight());
    }

    public function testGetTotalWeightWithEmptyItems(): void
    {
        $input = new ShippingCalculationInput('address1', []);

        $this->assertSame('0.000', $input->getTotalWeight());
    }

    public function testGetTotalQuantity(): void
    {
        $items = [
            new ShippingCalculationItem('product1', 2, '1.500', '10.00'),
            new ShippingCalculationItem('product2', 3, '0.800', '15.00'),
        ];

        $input = new ShippingCalculationInput('address1', $items);

        $this->assertSame(5, $input->getTotalQuantity());
    }

    public function testGetTotalQuantityWithEmptyItems(): void
    {
        $input = new ShippingCalculationInput('address1', []);

        $this->assertSame(0, $input->getTotalQuantity());
    }

    public function testGetTotalValue(): void
    {
        $items = [
            new ShippingCalculationItem('product1', 2, '1.500', '10.00'),
            new ShippingCalculationItem('product2', 3, '0.800', '15.00'),
        ];

        $input = new ShippingCalculationInput('address1', $items);

        $this->assertSame('65.00', $input->getTotalValue());
    }

    public function testGetTotalValueWithEmptyItems(): void
    {
        $input = new ShippingCalculationInput('address1', []);

        $this->assertSame('0.00', $input->getTotalValue());
    }

    public function testHasItems(): void
    {
        $input = new ShippingCalculationInput('address1', []);
        $this->assertFalse($input->hasItems());

        $items = [new ShippingCalculationItem('product1', 1, '1.000')];
        $input = new ShippingCalculationInput('address1', $items);
        $this->assertTrue($input->hasItems());
    }

    public function testGetProductIds(): void
    {
        $items = [
            new ShippingCalculationItem('product1', 2, '1.500'),
            new ShippingCalculationItem('product2', 1, '0.800'),
            new ShippingCalculationItem('product3', 3, '0.500'),
        ];

        $input = new ShippingCalculationInput('address1', $items);

        $this->assertSame(['product1', 'product2', 'product3'], $input->getProductIds());
    }

    public function testGetItemByProductId(): void
    {
        $item1 = new ShippingCalculationItem('product1', 2, '1.500');
        $item2 = new ShippingCalculationItem('product2', 1, '0.800');

        $input = new ShippingCalculationInput('address1', [$item1, $item2]);

        $this->assertSame($item1, $input->getItemByProductId('product1'));
        $this->assertSame($item2, $input->getItemByProductId('product2'));
        $this->assertNull($input->getItemByProductId('product3'));
    }
}
