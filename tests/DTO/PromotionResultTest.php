<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\DTO\PromotionResult;

/**
 * @internal
 */
#[CoversClass(PromotionResult::class)]
final class PromotionResultTest extends TestCase
{
    public function testPromotionResultCanBeInstantiated(): void
    {
        $result = new PromotionResult();

        $this->assertInstanceOf(PromotionResult::class, $result);
        $this->assertEquals([], $result->getPromotions());
        $this->assertEquals(0.0, $result->getDiscount());
        $this->assertEquals([], $result->getDetails());
        $this->assertFalse($result->hasPromotions());
    }

    public function testPromotionResultWithParameters(): void
    {
        $promotions = [
            'BLACK_FRIDAY' => ['discount' => 20.0, 'type' => 'percentage'],
            'FLASH_SALE' => ['discount' => 10.0, 'type' => 'fixed'],
        ];
        $discount = 30.0;
        $details = ['applied_at' => '2024-01-15', 'user_segment' => 'premium'];

        $result = new PromotionResult($promotions, $discount, $details);

        $this->assertEquals($promotions, $result->getPromotions());
        $this->assertEquals(30.0, $result->getDiscount());
        $this->assertEquals($details, $result->getDetails());
        $this->assertTrue($result->hasPromotions());
    }

    public function testGetPromotions(): void
    {
        $promotions = [
            'SUMMER_SALE' => ['discount' => 15.0],
            'MEMBER_BONUS' => ['discount' => 5.0],
        ];
        $result = new PromotionResult($promotions);

        $this->assertEquals($promotions, $result->getPromotions());
    }

    public function testGetPromotionsWithEmptyArray(): void
    {
        $result = new PromotionResult();

        $this->assertEquals([], $result->getPromotions());
        $this->assertIsArray($result->getPromotions());
    }

    public function testGetDiscount(): void
    {
        $result = new PromotionResult([], 25.5);

        $this->assertEquals(25.5, $result->getDiscount());
    }

    public function testGetDiscountWithDefaultValue(): void
    {
        $result = new PromotionResult();

        $this->assertEquals(0.0, $result->getDiscount());
    }

    public function testGetDetails(): void
    {
        $details = [
            'calculation_method' => 'stacked',
            'priority' => 1,
            'expires_at' => '2024-12-31',
        ];
        $result = new PromotionResult([], 0.0, $details);

        $this->assertEquals($details, $result->getDetails());
    }

    public function testGetDetailsWithDefaultValue(): void
    {
        $result = new PromotionResult();

        $this->assertEquals([], $result->getDetails());
        $this->assertIsArray($result->getDetails());
    }

    public function testHasPromotions(): void
    {
        $promotionsEmpty = new PromotionResult();
        $this->assertFalse($promotionsEmpty->hasPromotions());

        $promotionsWithData = new PromotionResult(['PROMO1' => ['discount' => 10.0]]);
        $this->assertTrue($promotionsWithData->hasPromotions());
    }

    public function testHasPromotionsWithEmptyPromotionArray(): void
    {
        $result = new PromotionResult([]);

        $this->assertFalse($result->hasPromotions());
    }

    public function testHasPromotionsWithNullValues(): void
    {
        // Test with promotions array containing null values
        $promotions = ['PROMO1' => null, 'PROMO2' => ['discount' => 5.0]];
        $result = new PromotionResult($promotions);

        $this->assertTrue($result->hasPromotions());
    }

    public function testMerge(): void
    {
        $promotions1 = ['PROMO1' => ['discount' => 10.0]];
        $details1 = ['source' => 'system'];
        $result1 = new PromotionResult($promotions1, 10.0, $details1);

        $promotions2 = ['PROMO2' => ['discount' => 5.0]];
        $details2 = ['source' => 'manual', 'priority' => 'high'];
        $result2 = new PromotionResult($promotions2, 5.0, $details2);

        $merged = $result1->merge($result2);

        $expectedPromotions = [
            'PROMO1' => ['discount' => 10.0],
            'PROMO2' => ['discount' => 5.0],
        ];
        $expectedDetails = ['source' => 'manual', 'priority' => 'high'];

        $this->assertEquals($expectedPromotions, $merged->getPromotions());
        $this->assertEquals(15.0, $merged->getDiscount());
        $this->assertEquals($expectedDetails, $merged->getDetails());
    }

    public function testMergeWithOverlappingPromotions(): void
    {
        $promotions1 = ['PROMO1' => ['discount' => 10.0], 'COMMON' => ['discount' => 5.0]];
        $result1 = new PromotionResult($promotions1, 15.0);

        $promotions2 = ['PROMO2' => ['discount' => 8.0], 'COMMON' => ['discount' => 7.0]];
        $result2 = new PromotionResult($promotions2, 15.0);

        $merged = $result1->merge($result2);

        $expectedPromotions = [
            'PROMO1' => ['discount' => 10.0],
            'COMMON' => ['discount' => 7.0], // Second result overwrites first
            'PROMO2' => ['discount' => 8.0],
        ];

        $this->assertEquals($expectedPromotions, $merged->getPromotions());
        $this->assertEquals(30.0, $merged->getDiscount());
    }

    public function testMergeWithEmptyResults(): void
    {
        $result1 = new PromotionResult(['PROMO1' => ['discount' => 10.0]], 10.0);
        $result2 = new PromotionResult();

        $merged = $result1->merge($result2);

        $this->assertEquals(['PROMO1' => ['discount' => 10.0]], $merged->getPromotions());
        $this->assertEquals(10.0, $merged->getDiscount());
    }

    public function testMergeDoesNotModifyOriginal(): void
    {
        $promotions1 = ['PROMO1' => ['discount' => 10.0]];
        $result1 = new PromotionResult($promotions1, 10.0);

        $promotions2 = ['PROMO2' => ['discount' => 5.0]];
        $result2 = new PromotionResult($promotions2, 5.0);

        $merged = $result1->merge($result2);

        // Original results should be unchanged
        $this->assertEquals($promotions1, $result1->getPromotions());
        $this->assertEquals(10.0, $result1->getDiscount());

        $this->assertEquals($promotions2, $result2->getPromotions());
        $this->assertEquals(5.0, $result2->getDiscount());
    }

    public function testEmpty(): void
    {
        $result = PromotionResult::empty();

        $this->assertInstanceOf(PromotionResult::class, $result);
        $this->assertEquals([], $result->getPromotions());
        $this->assertEquals(0.0, $result->getDiscount());
        $this->assertEquals([], $result->getDetails());
        $this->assertFalse($result->hasPromotions());
    }

    public function testToArray(): void
    {
        $promotions = [
            'SPRING_SALE' => ['discount' => 15.0, 'type' => 'percentage'],
            'LOYALTY_BONUS' => ['discount' => 10.0, 'type' => 'fixed'],
        ];
        $discount = 25.0;
        $details = ['campaign_id' => 'CAMP2024', 'user_tier' => 'gold'];

        $result = new PromotionResult($promotions, $discount, $details);

        $array = $result->toArray();

        $expected = [
            'promotions' => $promotions,
            'discount' => 25.0,
            'details' => $details,
        ];

        $this->assertEquals($expected, $array);
    }

    public function testToArrayWithEmptyData(): void
    {
        $result = new PromotionResult();

        $array = $result->toArray();

        $expected = [
            'promotions' => [],
            'discount' => 0.0,
            'details' => [],
        ];

        $this->assertEquals($expected, $array);
    }

    public function testEmptyToArray(): void
    {
        $result = PromotionResult::empty();

        $array = $result->toArray();

        $expected = [
            'promotions' => [],
            'discount' => 0.0,
            'details' => [],
        ];

        $this->assertEquals($expected, $array);
    }

    public function testNegativeDiscount(): void
    {
        // Test with negative discount (penalty or surcharge)
        $result = new PromotionResult(['PENALTY' => ['amount' => 5.0]], -5.0);

        $this->assertEquals(-5.0, $result->getDiscount());
        $this->assertTrue($result->hasPromotions());
    }

    public function testComplexPromotionStructure(): void
    {
        $promotions = [
            'TIERED_DISCOUNT' => [
                'tiers' => [
                    ['min' => 100, 'discount' => 10],
                    ['min' => 200, 'discount' => 20],
                ],
                'applied_tier' => 1,
                'discount' => 20.0,
            ],
            'BUNDLE_OFFER' => [
                'bundle_id' => 'BUNDLE_001',
                'items' => ['ITEM_A', 'ITEM_B'],
                'discount' => 15.0,
            ],
        ];

        $result = new PromotionResult($promotions, 35.0);

        $this->assertEquals($promotions, $result->getPromotions());
        $this->assertEquals(35.0, $result->getDiscount());
        $this->assertTrue($result->hasPromotions());
    }
}
