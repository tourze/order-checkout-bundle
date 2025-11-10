<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\RecommendedCoupon;

#[CoversClass(RecommendedCoupon::class)]
class RecommendedCouponTest extends TestCase
{
    public function testConstructorInitializesAllProperties(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'TEST123',
            name: 'Test Coupon',
            type: 'full_reduction',
            expectedDiscount: '10.00',
            description: 'Test Description',
            validFrom: '2024-01-01',
            validTo: '2024-12-31',
            conditions: ['min_amount' => 100],
            metadata: ['key' => 'value'],
            giftItems: [['skuId' => 1, 'gtin' => null, 'quantity' => 1, 'name' => 'Gift']],
            redeemItems: [['skuId' => 2, 'quantity' => 1, 'unitPrice' => '5.00', 'name' => 'Redeem', 'subtotal' => '5.00']]
        );

        $this->assertSame('TEST123', $coupon->getCode());
        $this->assertSame('Test Coupon', $coupon->getName());
        $this->assertSame('full_reduction', $coupon->getType());
        $this->assertSame('10.00', $coupon->getExpectedDiscount());
        $this->assertSame('Test Description', $coupon->getDescription());
        $this->assertSame('2024-01-01', $coupon->getValidFrom());
        $this->assertSame('2024-12-31', $coupon->getValidTo());
        $this->assertSame(['min_amount' => 100], $coupon->getConditions());
        $this->assertSame(['key' => 'value'], $coupon->getMetadata());
        $this->assertCount(1, $coupon->getGiftItems());
        $this->assertCount(1, $coupon->getRedeemItems());
    }

    public function testConstructorWithMinimalParameters(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'SIMPLE',
            name: 'Simple Coupon',
            type: 'full_reduction',
            expectedDiscount: '5.00',
            description: 'Simple Description'
        );

        $this->assertSame('SIMPLE', $coupon->getCode());
        $this->assertNull($coupon->getValidFrom());
        $this->assertNull($coupon->getValidTo());
        $this->assertSame([], $coupon->getConditions());
        $this->assertSame([], $coupon->getMetadata());
        $this->assertSame([], $coupon->getGiftItems());
        $this->assertSame([], $coupon->getRedeemItems());
    }

    public function testHasGiftsReturnsTrueWithGiftItems(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'GIFT123',
            name: 'Gift Coupon',
            type: 'buy_gift',
            expectedDiscount: '0.00',
            description: 'Buy and get gifts',
            giftItems: [['skuId' => 1, 'gtin' => null, 'quantity' => 1, 'name' => 'Gift']]
        );

        $this->assertTrue($coupon->hasGifts());
    }

    public function testHasGiftsReturnsTrueWithRedeemItems(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'REDEEM123',
            name: 'Redeem Coupon',
            type: 'redeem',
            expectedDiscount: '0.00',
            description: 'Redeem items',
            redeemItems: [['skuId' => 2, 'quantity' => 1, 'unitPrice' => '5.00', 'name' => 'Item', 'subtotal' => '5.00']]
        );

        $this->assertTrue($coupon->hasGifts());
    }

    public function testHasGiftsReturnsFalseWithoutItems(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'NOGIFT',
            name: 'No Gift Coupon',
            type: 'full_reduction',
            expectedDiscount: '10.00',
            description: 'No gifts'
        );

        $this->assertFalse($coupon->hasGifts());
    }

    public function testToArray(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'ARRAY123',
            name: 'Array Test',
            type: 'full_reduction',
            expectedDiscount: '15.50',
            description: 'Test to array',
            validFrom: '2024-01-01',
            validTo: '2024-12-31',
            conditions: ['min_amount' => 50],
            metadata: ['source' => 'test']
        );

        $array = $coupon->toArray();

        $this->assertIsArray($array);
        $this->assertSame('ARRAY123', $array['code']);
        $this->assertSame('Array Test', $array['name']);
        $this->assertSame('full_reduction', $array['type']);
        $this->assertSame(15.50, $array['expectedDiscount']);
        $this->assertSame('Test to array', $array['description']);
        $this->assertSame('2024-01-01', $array['validFrom']);
        $this->assertSame('2024-12-31', $array['validTo']);
        $this->assertSame(['min_amount' => 50], $array['conditions']);
        $this->assertSame(['source' => 'test'], $array['metadata']);
        $this->assertSame([], $array['giftItems']);
        $this->assertSame([], $array['redeemItems']);
        $this->assertFalse($array['hasGifts']);
    }

    public function testFormatApiData(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'API123',
            name: 'API Test',
            type: 'buy_gift',
            expectedDiscount: '0.00',
            description: 'Test API format'
        );

        $apiData = $coupon->formatApiData();

        $this->assertIsArray($apiData);
        $this->assertArrayHasKey('thirdType', $apiData);
        $this->assertSame(10, $apiData['thirdType']); // buy_gift = 10
        $this->assertArrayNotHasKey('metadata', $apiData);
    }

    public function testGetDiscountLabelForFullReduction(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'REDUCE',
            name: 'Reduce',
            type: 'full_reduction',
            expectedDiscount: '20.00',
            description: 'Reduction'
        );

        $this->assertSame('立减¥20.00', $coupon->getDiscountLabel());
    }

    public function testGetDiscountLabelForBuyGift(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'GIFT',
            name: 'Gift',
            type: 'buy_gift',
            expectedDiscount: '10.00',
            description: 'Buy gift'
        );

        $this->assertSame('买赠优惠', $coupon->getDiscountLabel());
    }

    public function testGetDiscountLabelForFullGift(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'FULLGIFT',
            name: 'Full Gift',
            type: 'full_gift',
            expectedDiscount: '5.00',
            description: 'Full gift'
        );

        $this->assertSame('满赠优惠', $coupon->getDiscountLabel());
    }

    public function testGetDiscountLabelForRedeem(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'REDEEM',
            name: 'Redeem',
            type: 'redeem',
            expectedDiscount: '15.00',
            description: 'Redeem'
        );

        $this->assertSame('兑换优惠', $coupon->getDiscountLabel());
    }

    public function testGetDiscountLabelForZeroDiscount(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'ZERO',
            name: 'Zero',
            type: 'full_reduction',
            expectedDiscount: '0.00',
            description: 'Zero discount'
        );

        $this->assertSame('', $coupon->getDiscountLabel());
    }

    public function testGetDiscountLabelForUnknownType(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'UNKNOWN',
            name: 'Unknown',
            type: 'unknown_type',
            expectedDiscount: '10.00',
            description: 'Unknown type'
        );

        $this->assertSame('优惠¥10.00', $coupon->getDiscountLabel());
    }

    public function testGetThirdTypeForAllTypes(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'TYPE',
            name: 'Type Test',
            type: 'full_reduction',
            expectedDiscount: '10.00',
            description: 'Type test'
        );

        $this->assertSame(0, $coupon->getThirdType('full_reduction'));
        $this->assertSame(10, $coupon->getThirdType('buy_gift'));
        $this->assertSame(9, $coupon->getThirdType('full_gift'));
        $this->assertSame(6, $coupon->getThirdType('redeem'));
    }

    public function testGetConditionDescriptionWithMinAmount(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'MIN',
            name: 'Min Amount',
            type: 'full_reduction',
            expectedDiscount: '10.00',
            description: 'Min amount test',
            conditions: ['min_amount' => 100]
        );

        $description = $coupon->getConditionDescription();
        $this->assertStringContainsString('满¥100.00可用', $description);
    }

    public function testGetConditionDescriptionWithMaxAmount(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'MAX',
            name: 'Max Amount',
            type: 'full_reduction',
            expectedDiscount: '10.00',
            description: 'Max amount test',
            conditions: ['max_amount' => 50]
        );

        $description = $coupon->getConditionDescription();
        $this->assertStringContainsString('最多优惠¥50.00', $description);
    }

    public function testGetConditionDescriptionWithApplicableProducts(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'PRODUCTS',
            name: 'Specific Products',
            type: 'full_reduction',
            expectedDiscount: '10.00',
            description: 'Products test',
            conditions: [
                'applicable_products' => [
                    'type' => 'specific',
                    'product_ids' => [1, 2, 3],
                ],
            ]
        );

        $description = $coupon->getConditionDescription();
        $this->assertStringContainsString('限指定商品', $description);
    }

    public function testGetConditionDescriptionEmpty(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'EMPTY',
            name: 'Empty Conditions',
            type: 'full_reduction',
            expectedDiscount: '10.00',
            description: 'Empty conditions'
        );

        $this->assertSame('', $coupon->getConditionDescription());
    }

    public function testIsBestDiscountReturnsTrueForHighestDiscount(): void
    {
        $coupon1 = new RecommendedCoupon(
            code: 'BEST',
            name: 'Best',
            type: 'full_reduction',
            expectedDiscount: '50.00',
            description: 'Best discount'
        );

        $coupon2 = new RecommendedCoupon(
            code: 'SECOND',
            name: 'Second',
            type: 'full_reduction',
            expectedDiscount: '30.00',
            description: 'Second best'
        );

        $this->assertTrue($coupon1->isBestDiscount([$coupon1, $coupon2]));
    }

    public function testIsBestDiscountReturnsFalseForLowerDiscount(): void
    {
        $coupon1 = new RecommendedCoupon(
            code: 'LOWER',
            name: 'Lower',
            type: 'full_reduction',
            expectedDiscount: '20.00',
            description: 'Lower discount'
        );

        $coupon2 = new RecommendedCoupon(
            code: 'HIGHER',
            name: 'Higher',
            type: 'full_reduction',
            expectedDiscount: '40.00',
            description: 'Higher discount'
        );

        $this->assertFalse($coupon1->isBestDiscount([$coupon1, $coupon2]));
    }

    public function testIsBestDiscountReturnsTrueForEmptyArray(): void
    {
        $coupon = new RecommendedCoupon(
            code: 'ONLY',
            name: 'Only One',
            type: 'full_reduction',
            expectedDiscount: '10.00',
            description: 'Only coupon'
        );

        $this->assertTrue($coupon->isBestDiscount([]));
    }

    public function testIsBestDiscountWithEqualDiscounts(): void
    {
        $coupon1 = new RecommendedCoupon(
            code: 'EQUAL1',
            name: 'Equal 1',
            type: 'full_reduction',
            expectedDiscount: '25.00',
            description: 'Equal discount 1'
        );

        $coupon2 = new RecommendedCoupon(
            code: 'EQUAL2',
            name: 'Equal 2',
            type: 'full_reduction',
            expectedDiscount: '25.00',
            description: 'Equal discount 2'
        );

        $this->assertTrue($coupon1->isBestDiscount([$coupon1, $coupon2]));
        $this->assertTrue($coupon2->isBestDiscount([$coupon1, $coupon2]));
    }
}
