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
        $priceResult = new PriceResult('100.00', '90.00', '10.00');
        $shippingResult = new ShippingResult(10.0, false);
        $stockValidation = StockValidationResult::success();
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
        $priceResult = new PriceResult('100.00', '100.00', '0.00');
        $shippingResult = new ShippingResult(10.0, false);

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
        $priceResult = new PriceResult('120.00', '100.00', '20.00');

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
        $priceResult = new PriceResult('120.00', '100.00', '20.00');

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
        $stockValidation = StockValidationResult::success();

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
        $stockValidation = StockValidationResult::failure(['库存不足']);

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
        $stockValidation = StockValidationResult::success();

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
        $stockValidation = StockValidationResult::failure(['库存不足']);

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
        $priceResult = new PriceResult('100.00', '90.00', '10.00');
        $shippingResult = new ShippingResult(10.0, false);
        $stockValidation = StockValidationResult::success();

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
