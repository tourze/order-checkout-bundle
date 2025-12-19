<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Param\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Param\Checkout\CalculateShippingFeeParam;

/**
 * @internal
 */
#[CoversClass(CalculateShippingFeeParam::class)]
final class CalculateShippingFeeParamTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $param = new CalculateShippingFeeParam();

        self::assertInstanceOf(CalculateShippingFeeParam::class, $param);
    }

    public function testAddressIdProperty(): void
    {
        $param = new CalculateShippingFeeParam();
        $param->addressId = 'addr_12345';

        self::assertSame('addr_12345', $param->addressId);
    }

    public function testItemsProperty(): void
    {
        $items = [
            [
                'productId' => 'prod_100',
                'quantity' => 2,
                'weight' => '1.5',
                'price' => '99.99',
                'shippingTemplateId' => 'tpl_1',
            ],
            [
                'productId' => 'prod_101',
                'quantity' => 1,
                'weight' => '0.8',
            ],
        ];

        $param = new CalculateShippingFeeParam();
        $param->addressId = 'addr_123';
        $param->items = $items;

        self::assertSame('addr_123', $param->addressId);
        self::assertSame($items, $param->items);
        self::assertCount(2, $param->items);
    }

    public function testItemsWithMinimalRequiredFields(): void
    {
        $items = [
            [
                'productId' => 'prod_100',
                'quantity' => 1,
                'weight' => '1.0',
            ],
        ];

        $param = new CalculateShippingFeeParam();
        $param->items = $items;

        self::assertSame($items, $param->items);
        self::assertArrayHasKey('productId', $param->items[0]);
        self::assertArrayHasKey('quantity', $param->items[0]);
        self::assertArrayHasKey('weight', $param->items[0]);
    }

    public function testItemsWithOptionalFields(): void
    {
        $items = [
            [
                'productId' => 'prod_100',
                'quantity' => 3,
                'weight' => '2.5',
                'price' => '199.99',
                'shippingTemplateId' => 'tpl_2',
            ],
        ];

        $param = new CalculateShippingFeeParam();
        $param->items = $items;

        self::assertSame('prod_100', $param->items[0]['productId']);
        self::assertSame(3, $param->items[0]['quantity']);
        self::assertSame('2.5', $param->items[0]['weight']);
        self::assertSame('199.99', $param->items[0]['price']);
        self::assertSame('tpl_2', $param->items[0]['shippingTemplateId']);
    }
}
