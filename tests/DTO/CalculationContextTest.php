<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\OrderCartBundle\Entity\CartItem;
use Tourze\OrderCheckoutBundle\DTO\CalculationContext;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;

/**
 * @internal
 */
#[CoversClass(CalculationContext::class)]
final class CalculationContextTest extends TestCase
{
    private UserInterface $user;

    /** @var CheckoutItem[] */
    private array $items;

    /** @var string[] */
    private array $appliedCoupons;

    /** @var array<string, mixed> */
    private array $metadata;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createMock(UserInterface::class);
        $this->items = [
            new CheckoutItem('SKU001', 2, true),
            new CheckoutItem('SKU002', 1, true),
        ];
        $this->appliedCoupons = ['COUPON1', 'COUPON2'];
        $this->metadata = [
            'region' => 'beijing',
            'calculate_time' => new \DateTimeImmutable('2024-01-15 10:30:00'),
            'custom_field' => 'test_value',
        ];
    }

    public function testCalculationContextCanBeInstantiated(): void
    {
        $context = new CalculationContext($this->user, $this->items);

        $this->assertInstanceOf(CalculationContext::class, $context);
        $this->assertSame($this->user, $context->getUser());
        $this->assertSame($this->items, $context->getItems());
        $this->assertEquals([], $context->getAppliedCoupons());
        $this->assertEquals([], $context->getMetadata());
    }

    public function testCalculationContextWithAllParameters(): void
    {
        $context = new CalculationContext(
            $this->user,
            $this->items,
            $this->appliedCoupons,
            $this->metadata
        );

        $this->assertSame($this->user, $context->getUser());
        $this->assertSame($this->items, $context->getItems());
        $this->assertSame($this->appliedCoupons, $context->getAppliedCoupons());
        $this->assertSame($this->metadata, $context->getMetadata());
    }

    public function testGetUser(): void
    {
        $context = new CalculationContext($this->user, $this->items);

        $this->assertSame($this->user, $context->getUser());
    }

    public function testGetItems(): void
    {
        $context = new CalculationContext($this->user, $this->items);

        $this->assertSame($this->items, $context->getItems());
        $this->assertCount(2, $context->getItems());
    }

    public function testGetAppliedCoupons(): void
    {
        $context = new CalculationContext($this->user, $this->items, $this->appliedCoupons);

        $this->assertSame($this->appliedCoupons, $context->getAppliedCoupons());
        $this->assertEquals(['COUPON1', 'COUPON2'], $context->getAppliedCoupons());
    }

    public function testGetMetadata(): void
    {
        $context = new CalculationContext($this->user, $this->items, [], $this->metadata);

        $this->assertSame($this->metadata, $context->getMetadata());
    }

    public function testGetMetadataValue(): void
    {
        $context = new CalculationContext($this->user, $this->items, [], $this->metadata);

        $this->assertEquals('beijing', $context->getMetadataValue('region'));
        $this->assertEquals('test_value', $context->getMetadataValue('custom_field'));
        $this->assertNull($context->getMetadataValue('nonexistent'));
        $this->assertEquals('default', $context->getMetadataValue('nonexistent', 'default'));
    }

    public function testGetRegion(): void
    {
        $context = new CalculationContext($this->user, $this->items, [], $this->metadata);

        $this->assertEquals('beijing', $context->getRegion());
    }

    public function testGetRegionWithoutMetadata(): void
    {
        $context = new CalculationContext($this->user, $this->items);

        $this->assertNull($context->getRegion());
    }

    public function testGetCalculateTime(): void
    {
        $expectedTime = new \DateTimeImmutable('2024-01-15 10:30:00');
        $metadata = ['calculate_time' => $expectedTime];
        $context = new CalculationContext($this->user, $this->items, [], $metadata);

        $this->assertSame($expectedTime, $context->getCalculateTime());
    }

    public function testGetCalculateTimeWithoutMetadata(): void
    {
        $context = new CalculationContext($this->user, $this->items);

        $calculateTime = $context->getCalculateTime();
        $this->assertInstanceOf(\DateTimeInterface::class, $calculateTime);
        $this->assertInstanceOf(\DateTimeImmutable::class, $calculateTime);
    }

    public function testWithMetadata(): void
    {
        $context = new CalculationContext($this->user, $this->items, $this->appliedCoupons, ['existing' => 'value']);
        $newMetadata = ['new_field' => 'new_value', 'existing' => 'updated_value'];

        $newContext = $context->withMetadata($newMetadata);

        $this->assertNotSame($context, $newContext);
        $this->assertSame($this->user, $newContext->getUser());
        $this->assertSame($this->items, $newContext->getItems());
        $this->assertSame($this->appliedCoupons, $newContext->getAppliedCoupons());

        $expectedMetadata = [
            'existing' => 'updated_value',
            'new_field' => 'new_value',
        ];
        $this->assertEquals($expectedMetadata, $newContext->getMetadata());
    }

    public function testWithMetadataPreservesOriginalContext(): void
    {
        $originalMetadata = ['original' => 'value'];
        $context = new CalculationContext($this->user, $this->items, $this->appliedCoupons, $originalMetadata);

        $newContext = $context->withMetadata(['new' => 'value']);

        $this->assertEquals($originalMetadata, $context->getMetadata());
        $this->assertEquals(['original' => 'value', 'new' => 'value'], $newContext->getMetadata());
    }

    public function testWithCoupons(): void
    {
        $context = new CalculationContext($this->user, $this->items, ['EXISTING1', 'EXISTING2']);
        $newCoupons = ['NEW1', 'NEW2'];

        $newContext = $context->withCoupons($newCoupons);

        $this->assertNotSame($context, $newContext);
        $this->assertSame($this->user, $newContext->getUser());
        $this->assertSame($this->items, $newContext->getItems());

        $expectedCoupons = ['EXISTING1', 'EXISTING2', 'NEW1', 'NEW2'];
        $this->assertEquals($expectedCoupons, $newContext->getAppliedCoupons());
    }

    public function testWithCouponsRemovesDuplicates(): void
    {
        $context = new CalculationContext($this->user, $this->items, ['COUPON1', 'COUPON2']);
        $newCoupons = ['COUPON2', 'COUPON3', 'COUPON1'];

        $newContext = $context->withCoupons($newCoupons);

        $actualCoupons = $newContext->getAppliedCoupons();
        $this->assertCount(3, $actualCoupons);
        $this->assertContains('COUPON1', $actualCoupons);
        $this->assertContains('COUPON2', $actualCoupons);
        $this->assertContains('COUPON3', $actualCoupons);
    }

    public function testWithCouponsPreservesOriginalContext(): void
    {
        $originalCoupons = ['ORIGINAL1', 'ORIGINAL2'];
        $context = new CalculationContext($this->user, $this->items, $originalCoupons);

        $newContext = $context->withCoupons(['NEW1']);

        $this->assertEquals($originalCoupons, $context->getAppliedCoupons());
        $this->assertEquals(['ORIGINAL1', 'ORIGINAL2', 'NEW1'], $newContext->getAppliedCoupons());
    }

    public function testWithEmptyCoupons(): void
    {
        $context = new CalculationContext($this->user, $this->items, ['EXISTING']);

        $newContext = $context->withCoupons([]);

        $this->assertEquals(['EXISTING'], $newContext->getAppliedCoupons());
    }

    public function testImmutableBehavior(): void
    {
        $context = new CalculationContext($this->user, $this->items, ['COUPON1'], ['meta' => 'value']);

        $contextWithMetadata = $context->withMetadata(['new_meta' => 'new_value']);
        $contextWithCoupons = $context->withCoupons(['COUPON2']);

        // Original context should be unchanged
        $this->assertEquals(['COUPON1'], $context->getAppliedCoupons());
        $this->assertEquals(['meta' => 'value'], $context->getMetadata());

        // New contexts should have their respective changes
        $this->assertEquals(['COUPON1'], $contextWithMetadata->getAppliedCoupons());
        $this->assertEquals(['meta' => 'value', 'new_meta' => 'new_value'], $contextWithMetadata->getMetadata());

        $this->assertEquals(['COUPON1', 'COUPON2'], $contextWithCoupons->getAppliedCoupons());
        $this->assertEquals(['meta' => 'value'], $contextWithCoupons->getMetadata());
    }
}
