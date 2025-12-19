<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service\Coupon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Tourze\OrderCheckoutBundle\DTO\CheckoutItem;
use Tourze\OrderCheckoutBundle\Exception\CheckoutException;
use Tourze\OrderCheckoutBundle\Service\Coupon\CouponExtraItemBuilder;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;
use Tourze\ProductCoreBundle\Entity\Sku;
use Tourze\ProductCoreBundle\Service\SkuServiceInterface;

/**
 * @internal
 *
 * CouponExtraItemBuilder 服务的集成测试。
 */
#[CoversClass(CouponExtraItemBuilder::class)]
#[RunTestsInSeparateProcesses]
final class CouponExtraItemBuilderTest extends AbstractIntegrationTestCase
{
    private CouponExtraItemBuilder $builder;
    private SkuServiceInterface&MockObject $skuService;

    protected function onSetUp(): void
    {
        $this->skuService = $this->createMock(SkuServiceInterface::class);
        self::getContainer()->set(SkuServiceInterface::class, $this->skuService);

        $this->builder = self::getService(CouponExtraItemBuilder::class);
    }

    public function testBuilderCanBeCreated(): void
    {
        $this->assertInstanceOf(CouponExtraItemBuilder::class, $this->builder);
    }

    public function testLoadSkusForExtrasWithNoItems(): void
    {
        $skuMap = $this->builder->loadSkusForExtras([], []);

        $this->assertIsArray($skuMap);
        $this->assertEmpty($skuMap);
    }

    public function testLoadSkusForExtrasWithGiftItems(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('123');

        $this->skuService->method('findByIds')
            ->with(['123'])
            ->willReturn([$sku]);

        $rawGifts = [
            ['sku_id' => 123, 'quantity' => 2],
        ];

        $skuMap = $this->builder->loadSkusForExtras($rawGifts, []);

        $this->assertIsArray($skuMap);
        $this->assertCount(1, $skuMap);
        $this->assertArrayHasKey(123, $skuMap);
        $this->assertSame($sku, $skuMap[123]);
    }

    public function testLoadSkusForExtrasWithRedeemItems(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('456');

        $this->skuService->method('findByIds')
            ->with(['456'])
            ->willReturn([$sku]);

        $rawRedeems = [
            ['sku_id' => 456, 'quantity' => 1],
        ];

        $skuMap = $this->builder->loadSkusForExtras([], $rawRedeems);

        $this->assertIsArray($skuMap);
        $this->assertCount(1, $skuMap);
        $this->assertArrayHasKey(456, $skuMap);
        $this->assertSame($sku, $skuMap[456]);
    }

    public function testLoadSkusForExtrasWithMixedItems(): void
    {
        $sku1 = $this->createMock(Sku::class);
        $sku1->method('getId')->willReturn('111');

        $sku2 = $this->createMock(Sku::class);
        $sku2->method('getId')->willReturn('222');

        $this->skuService->method('findByIds')
            ->with(['111', '222'])
            ->willReturn([$sku1, $sku2]);

        $rawGifts = [['sku_id' => 111, 'quantity' => 2]];
        $rawRedeems = [['sku_id' => 222, 'quantity' => 1]];

        $skuMap = $this->builder->loadSkusForExtras($rawGifts, $rawRedeems);

        $this->assertIsArray($skuMap);
        $this->assertCount(2, $skuMap);
    }

    public function testBuildGiftExtras(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('123');

        $skuMap = [123 => $sku];
        $rawGifts = [
            ['sku_id' => 123, 'quantity' => 2],
        ];

        $extras = $this->builder->buildGiftExtras($rawGifts, $skuMap);

        $this->assertIsArray($extras);
        $this->assertCount(1, $extras);
        $this->assertEquals('coupon_gift', $extras[0]['type']);
        $this->assertEquals('0.00', $extras[0]['unit_price']);
        $this->assertEquals('0.00', $extras[0]['total_price']);
        $this->assertInstanceOf(CheckoutItem::class, $extras[0]['item']);
    }

    public function testBuildGiftExtrasThrowsExceptionWhenSkuNotFound(): void
    {
        $rawGifts = [
            ['sku_id' => 999, 'quantity' => 1],
        ];

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('优惠券赠品 999 不存在或已下架');

        $originalErrorLog = ini_get('error_log');
        ini_set('error_log', '/dev/null');

        try {
            $this->builder->buildGiftExtras($rawGifts, []);
        } finally {
            ini_set('error_log', $originalErrorLog !== false ? $originalErrorLog : '');
        }
    }

    public function testBuildGiftExtrasSkipsInvalidItems(): void
    {
        $extras = $this->builder->buildGiftExtras([
            'not-an-array',
            ['sku_id' => 0, 'quantity' => 1],
            ['sku_id' => 123, 'quantity' => 0],
        ], []);

        $this->assertIsArray($extras);
        $this->assertEmpty($extras);
    }

    public function testBuildRedeemExtras(): void
    {
        $sku = $this->createMock(Sku::class);
        $sku->method('getId')->willReturn('456');

        $skuMap = [456 => $sku];
        $rawRedeems = [
            ['sku_id' => 456, 'quantity' => 1, 'unit_price' => '99.99'],
        ];

        $extras = $this->builder->buildRedeemExtras($rawRedeems, $skuMap);

        $this->assertIsArray($extras);
        $this->assertCount(1, $extras);
        $this->assertEquals('coupon_redeem', $extras[0]['type']);
        $this->assertEquals('0.00', $extras[0]['unit_price']);
        $this->assertEquals('0.00', $extras[0]['total_price']);
        $this->assertEquals('99.99', $extras[0]['reference_unit_price']);
        $this->assertInstanceOf(CheckoutItem::class, $extras[0]['item']);
    }

    public function testBuildRedeemExtrasThrowsExceptionWhenSkuNotFound(): void
    {
        $rawRedeems = [
            ['sku_id' => 999, 'quantity' => 1],
        ];

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('兑换券商品 999 不存在或已下架');

        $originalErrorLog = ini_get('error_log');
        ini_set('error_log', '/dev/null');

        try {
            $this->builder->buildRedeemExtras($rawRedeems, []);
        } finally {
            ini_set('error_log', $originalErrorLog !== false ? $originalErrorLog : '');
        }
    }

    public function testBuildRedeemExtrasSkipsInvalidItems(): void
    {
        $extras = $this->builder->buildRedeemExtras([
            'not-an-array',
            ['sku_id' => 0, 'quantity' => 1],
            ['sku_id' => 123, 'quantity' => 0],
        ], []);

        $this->assertIsArray($extras);
        $this->assertEmpty($extras);
    }

    public function testNormalizePriceWithStringNumeric(): void
    {
        $this->assertEquals('123.45', $this->builder->normalizePrice('123.45'));
        $this->assertEquals('100.00', $this->builder->normalizePrice('100'));
        $this->assertEquals('0.99', $this->builder->normalizePrice('0.99'));
    }

    public function testNormalizePriceWithNumeric(): void
    {
        $this->assertEquals('123.45', $this->builder->normalizePrice(123.45));
        $this->assertEquals('100.00', $this->builder->normalizePrice(100));
        $this->assertEquals('0.99', $this->builder->normalizePrice(0.99));
    }

    public function testNormalizePriceWithInvalidData(): void
    {
        $this->assertEquals('0.00', $this->builder->normalizePrice('invalid'));
        $this->assertEquals('0.00', $this->builder->normalizePrice(null));
        $this->assertEquals('0.00', $this->builder->normalizePrice([]));
    }
}
