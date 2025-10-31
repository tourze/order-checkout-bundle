<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCartBundle\Entity\CartItem;
use Tourze\OrderCheckoutBundle\DTO\CheckoutResult;
use Tourze\OrderCheckoutBundle\DTO\PriceResult;
use Tourze\OrderCheckoutBundle\DTO\ShippingResult;
use Tourze\OrderCheckoutBundle\DTO\StockValidationResult;

/**
 * @internal
 */
#[CoversClass(CheckoutResult::class)]
final class CheckoutResultTest extends TestCase
{
    public function testCheckoutResultCanBeInstantiated(): void
    {
        $result = new CheckoutResult();
        $this->assertInstanceOf(CheckoutResult::class, $result);
    }

    public function testCheckoutResultWithParameters(): void
    {
        $items = [$this->createMock(CartItem::class)];
        $priceResult = $this->createMock(PriceResult::class);
        $shippingResult = $this->createMock(ShippingResult::class);
        $stockValidation = $this->createMock(StockValidationResult::class);
        $appliedCoupons = ['COUPON1'];

        $result = new CheckoutResult(
            $items,
            $priceResult,
            $shippingResult,
            $stockValidation,
            $appliedCoupons
        );

        $this->assertSame($items, $result->getItems());
        $this->assertSame($priceResult, $result->getPriceResult());
        $this->assertSame($shippingResult, $result->getShippingResult());
        $this->assertSame($stockValidation, $result->getStockValidation());
        $this->assertSame($appliedCoupons, $result->getAppliedCoupons());
    }

    public function testGetFinalTotalWithNoResults(): void
    {
        $result = new CheckoutResult();
        $this->assertEquals(0.0, $result->getFinalTotal());
    }

    public function testGetFinalTotalWithResults(): void
    {
        $priceResult = $this->createMock(PriceResult::class);
        $priceResult->method('getFinalPrice')->willReturn('100.00');

        $shippingResult = $this->createMock(ShippingResult::class);
        $shippingResult->method('getShippingFee')->willReturn(10.0);

        $result = new CheckoutResult(
            [],
            $priceResult,
            $shippingResult
        );

        $this->assertEquals(110.0, $result->getFinalTotal());
    }

    public function testGetOriginalTotalWithNoPriceResult(): void
    {
        $result = new CheckoutResult();
        $this->assertEquals(0.0, $result->getOriginalTotal());
    }

    public function testGetOriginalTotalWithPriceResult(): void
    {
        $priceResult = $this->createMock(PriceResult::class);
        $priceResult->method('getOriginalPrice')->willReturn('120.00');

        $result = new CheckoutResult(
            [],
            $priceResult
        );

        $this->assertEquals(120.0, $result->getOriginalTotal());
    }

    public function testGetTotalDiscountWithNoPriceResult(): void
    {
        $result = new CheckoutResult();
        $this->assertEquals(0.0, $result->getTotalDiscount());
    }

    public function testGetTotalDiscountWithPriceResult(): void
    {
        $priceResult = $this->createMock(PriceResult::class);
        $priceResult->method('getDiscount')->willReturn('20.00');

        $result = new CheckoutResult(
            [],
            $priceResult
        );

        $this->assertEquals(20.0, $result->getTotalDiscount());
    }

    public function testHasStockIssuesWithNoValidation(): void
    {
        $result = new CheckoutResult();
        $this->assertFalse($result->hasStockIssues());
    }

    public function testHasStockIssuesWithValidStock(): void
    {
        $stockValidation = $this->createMock(StockValidationResult::class);
        $stockValidation->method('isValid')->willReturn(true);

        $result = new CheckoutResult(
            [],
            null,
            null,
            $stockValidation
        );

        $this->assertFalse($result->hasStockIssues());
    }

    public function testHasStockIssuesWithInvalidStock(): void
    {
        $stockValidation = $this->createMock(StockValidationResult::class);
        $stockValidation->method('isValid')->willReturn(false);

        $result = new CheckoutResult(
            [],
            null,
            null,
            $stockValidation
        );

        $this->assertTrue($result->hasStockIssues());
    }

    public function testCanCheckoutWithEmptyItems(): void
    {
        $result = new CheckoutResult();
        $this->assertFalse($result->canCheckout());
    }

    public function testCanCheckoutWithItemsAndValidStock(): void
    {
        $items = [$this->createMock(CartItem::class)];
        $stockValidation = $this->createMock(StockValidationResult::class);
        $stockValidation->method('isValid')->willReturn(true);

        $result = new CheckoutResult(
            $items,
            null,
            null,
            $stockValidation
        );

        $this->assertTrue($result->canCheckout());
    }

    public function testCanCheckoutWithItemsAndInvalidStock(): void
    {
        $items = [$this->createMock(CartItem::class)];
        $stockValidation = $this->createMock(StockValidationResult::class);
        $stockValidation->method('isValid')->willReturn(false);

        $result = new CheckoutResult(
            $items,
            null,
            null,
            $stockValidation
        );

        $this->assertFalse($result->canCheckout());
    }

    public function testToArray(): void
    {
        $cartItem = $this->createMock(CartItem::class);
        $cartItem->method('getId')->willReturn('1');
        $cartItem->method('getQuantity')->willReturn(2);

        $priceResult = $this->createMock(PriceResult::class);
        $priceResult->method('toArray')->willReturn(['subtotal' => 100.0]);

        $shippingResult = $this->createMock(ShippingResult::class);
        $shippingResult->method('toArray')->willReturn(['fee' => 10.0]);
        $shippingResult->method('getShippingFee')->willReturn(10.0);
        $shippingResult->method('isFreeShipping')->willReturn(false);

        $stockValidation = $this->createMock(StockValidationResult::class);
        $stockValidation->method('toArray')->willReturn(['valid' => true]);
        $stockValidation->method('isValid')->willReturn(true);

        $result = new CheckoutResult(
            [$cartItem],
            $priceResult,
            $shippingResult,
            $stockValidation,
            ['COUPON1']
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('items', $array);
        $this->assertArrayHasKey('summary', $array);
        $this->assertArrayHasKey('price_details', $array);
        $this->assertArrayHasKey('shipping_details', $array);
        $this->assertArrayHasKey('stock_validation', $array);
        $this->assertArrayHasKey('applied_coupons', $array);
        $this->assertArrayHasKey('can_checkout', $array);

        $this->assertSame([$cartItem], $array['items']);
        $this->assertEquals(['COUPON1'], $array['applied_coupons']);
        $this->assertTrue($array['can_checkout']);
    }

    public function testEmpty(): void
    {
        $result = CheckoutResult::empty();

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertEquals(0.0, $result->getFinalTotal());
        $this->assertEquals(0.0, $result->getOriginalTotal());
        $this->assertEquals(0.0, $result->getTotalDiscount());
        $this->assertFalse($result->hasStockIssues());
        $this->assertFalse($result->canCheckout());
    }
}
