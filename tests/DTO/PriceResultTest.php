<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;

/**
 * @internal
 */
#[CoversClass(PriceResult::class)]
final class PriceResultTest extends TestCase
{
    public function testPriceResultCanBeInstantiated(): void
    {
        $result = new PriceResult('100.00', '80.00');

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertEquals('100.00', $result->getOriginalPrice());
        $this->assertEquals('80.00', $result->getFinalPrice());
        $this->assertEquals('0.00', $result->getDiscount());
        $this->assertEquals([], $result->getDetails());
        $this->assertEquals([], $result->getProducts());
    }

    public function testPriceResultWithAllParameters(): void
    {
        $details = ['promotion' => 'BLACK_FRIDAY', 'coupon' => 'SAVE20'];
        $result = new PriceResult(100.0, 80.0, 20.0, $details);

        $this->assertEquals(100.0, $result->getOriginalPrice());
        $this->assertEquals(80.0, $result->getFinalPrice());
        $this->assertEquals(20.0, $result->getDiscount());
        $this->assertEquals($details, $result->getDetails());
    }

    public function testGetOriginalPrice(): void
    {
        $result = new PriceResult(150.5, 120.0);

        $this->assertEquals(150.5, $result->getOriginalPrice());
    }

    public function testGetFinalPrice(): void
    {
        $result = new PriceResult(100.0, 85.75);

        $this->assertEquals(85.75, $result->getFinalPrice());
    }

    public function testGetDiscount(): void
    {
        $result = new PriceResult(100.0, 80.0, 15.5);

        $this->assertEquals(15.5, $result->getDiscount());
    }

    public function testGetDiscountWithDefaultValue(): void
    {
        $result = new PriceResult(100.0, 80.0);

        $this->assertEquals(0.0, $result->getDiscount());
    }

    public function testGetDetails(): void
    {
        $details = [
            'promotion_discount' => 10.0,
            'coupon_discount' => 5.0,
            'member_discount' => 3.0,
        ];
        $result = new PriceResult(100.0, 80.0, 18.0, $details);

        $this->assertEquals($details, $result->getDetails());
    }

    public function testGetDetail(): void
    {
        $details = [
            'promotion' => 'SPRING_SALE',
            'discount_rate' => 0.15,
            'category' => 'electronics',
        ];
        $result = new PriceResult(100.0, 85.0, 15.0, $details);

        $this->assertEquals('SPRING_SALE', $result->getDetail('promotion'));
        $this->assertEquals(0.15, $result->getDetail('discount_rate'));
        $this->assertEquals('electronics', $result->getDetail('category'));
    }

    public function testGetDetailWithNonExistentKey(): void
    {
        $result = new PriceResult(100.0, 80.0);

        $this->assertNull($result->getDetail('nonexistent'));
    }

    public function testGetDetailWithDefaultValue(): void
    {
        $result = new PriceResult(100.0, 80.0);

        $this->assertEquals('default_value', $result->getDetail('nonexistent', 'default_value'));
        $this->assertEquals(0, $result->getDetail('missing_number', 0));
        $this->assertFalse($result->getDetail('missing_bool', false));
    }

    public function testMerge(): void
    {
        $result1 = new PriceResult(100.0, 85.0, 15.0, ['type' => 'product']);
        $result2 = new PriceResult(50.0, 45.0, 5.0, ['category' => 'service']);

        $merged = $result1->merge($result2);

        $this->assertEquals(150.0, $merged->getOriginalPrice());
        $this->assertEquals(130.0, $merged->getFinalPrice());
        $this->assertEquals(20.0, $merged->getDiscount());

        $expectedDetails = ['type' => 'product', 'category' => 'service'];
        $this->assertEquals($expectedDetails, $merged->getDetails());
    }

    public function testMergeWithOverlappingKeys(): void
    {
        $result1 = new PriceResult(100.0, 85.0, 15.0, ['source' => 'first', 'unique1' => 'value1']);
        $result2 = new PriceResult(50.0, 45.0, 5.0, ['source' => 'second', 'unique2' => 'value2']);

        $merged = $result1->merge($result2);

        $expectedDetails = [
            'source' => 'second',  // Second result overwrites first
            'unique1' => 'value1',
            'unique2' => 'value2',
        ];
        $this->assertEquals($expectedDetails, $merged->getDetails());
    }

    public function testMergeWithZeroValues(): void
    {
        $result1 = new PriceResult(0.0, 0.0, 0.0);
        $result2 = new PriceResult(100.0, 80.0, 20.0);

        $merged = $result1->merge($result2);

        $this->assertEquals(100.0, $merged->getOriginalPrice());
        $this->assertEquals(80.0, $merged->getFinalPrice());
        $this->assertEquals(20.0, $merged->getDiscount());
    }

    public function testMergeDoesNotModifyOriginal(): void
    {
        $result1 = new PriceResult(100.0, 85.0, 15.0, ['type' => 'product']);
        $result2 = new PriceResult(50.0, 45.0, 5.0, ['category' => 'service']);

        $merged = $result1->merge($result2);

        // Original results should be unchanged
        $this->assertEquals(100.0, $result1->getOriginalPrice());
        $this->assertEquals(['type' => 'product'], $result1->getDetails());

        $this->assertEquals(50.0, $result2->getOriginalPrice());
        $this->assertEquals(['category' => 'service'], $result2->getDetails());
    }

    public function testToArray(): void
    {
        $details = ['promotion' => 'SUMMER_SALE', 'member_level' => 'gold'];
        $result = new PriceResult('200.00', '160.00', '40.00', $details);

        $array = $result->toArray();

        $expected = [
            'original_price' => '200.00',
            'final_price' => '160.00',
            'discount' => '40.00',
            'details' => $details,
            'products' => [],
        ];

        $this->assertEquals($expected, $array);
    }

    public function testToArrayWithMinimalData(): void
    {
        $result = new PriceResult('100.00', '100.00');

        $array = $result->toArray();

        $expected = [
            'original_price' => '100.00',
            'final_price' => '100.00',
            'discount' => '0.00',
            'details' => [],
            'products' => [],
        ];

        $this->assertEquals($expected, $array);
    }

    public function testEmpty(): void
    {
        $result = PriceResult::empty();

        $this->assertInstanceOf(PriceResult::class, $result);
        $this->assertEquals('0.00', $result->getOriginalPrice());
        $this->assertEquals('0.00', $result->getFinalPrice());
        $this->assertEquals('0.00', $result->getDiscount());
        $this->assertEquals([], $result->getDetails());
        $this->assertEquals([], $result->getProducts());
    }

    public function testEmptyToArray(): void
    {
        $result = PriceResult::empty();

        $array = $result->toArray();

        $expected = [
            'original_price' => '0.00',
            'final_price' => '0.00',
            'discount' => '0.00',
            'details' => [],
            'products' => [],
        ];

        $this->assertEquals($expected, $array);
    }

    public function testNegativeValues(): void
    {
        // Test with negative discount (price increase)
        $result = new PriceResult(100.0, 110.0, -10.0);

        $this->assertEquals(100.0, $result->getOriginalPrice());
        $this->assertEquals(110.0, $result->getFinalPrice());
        $this->assertEquals(-10.0, $result->getDiscount());
    }

    public function testFloatPrecision(): void
    {
        $result = new PriceResult(99.99, 85.99, 14.00);

        $this->assertEquals('99.99', $result->getOriginalPrice());
        $this->assertEquals('85.99', $result->getFinalPrice());
        $this->assertEquals('14.00', $result->getDiscount());
    }

    public function testGetProducts(): void
    {
        $products = [
            [
                'skuId' => '123',
                'spuId' => '456',
                'quantity' => 2,
                'payablePrice' => '199.98',
                'unitPrice' => '99.99',
                'mainThumb' => 'https://example.com/thumb.jpg',
                'productName' => 'Test Product - Red',
                'specifications' => '颜色红色',
            ],
        ];

        $result = new PriceResult('199.98', '199.98', '0.00', [], $products);

        $this->assertSame($products, $result->getProducts());
    }

    public function testToArrayIncludesProducts(): void
    {
        $products = [
            ['skuId' => '123', 'quantity' => 1, 'payablePrice' => '99.99'],
        ];

        $result = new PriceResult('99.99', '99.99', '0.00', [], $products);
        $array = $result->toArray();

        $this->assertArrayHasKey('products', $array);
        $this->assertSame($products, $array['products']);
    }

    public function testMergeIncludesProducts(): void
    {
        $products1 = [
            ['skuId' => '123', 'quantity' => 1, 'payablePrice' => '99.99'],
        ];
        $products2 = [
            ['skuId' => '456', 'quantity' => 2, 'payablePrice' => '199.98'],
        ];

        $result1 = new PriceResult('99.99', '99.99', '0.00', [], $products1);
        $result2 = new PriceResult('199.98', '199.98', '0.00', [], $products2);

        $merged = $result1->merge($result2);
        $mergedProducts = $merged->getProducts();

        $this->assertCount(2, $mergedProducts);
        $this->assertSame('123', $mergedProducts[0]['skuId']);
        $this->assertSame('456', $mergedProducts[1]['skuId']);
    }

    public function testEmptyReturnsEmptyProducts(): void
    {
        $result = PriceResult::empty();

        $this->assertSame([], $result->getProducts());
    }

    public function testCreateWithProducts(): void
    {
        $products = [
            ['skuId' => '123', 'quantity' => 1],
        ];

        $result = PriceResult::create('100.00', '90.00', '10.00', [], $products);

        $this->assertSame($products, $result->getProducts());
    }
}
