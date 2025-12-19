<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\OrderCheckoutBundle\Entity\CouponAllocationDetail;
use Tourze\OrderCheckoutBundle\Repository\CouponAllocationDetailRepository;
use Tourze\OrderCheckoutBundle\Service\CouponAllocationService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(CouponAllocationService::class)]
#[RunTestsInSeparateProcesses]
final class CouponAllocationServiceTest extends AbstractIntegrationTestCase
{
    private CouponAllocationService $service;

    private CouponAllocationDetailRepository $repository;

    protected function onSetUp(): void
    {
        $this->service = self::getService(CouponAllocationService::class);
        $this->repository = self::getService(CouponAllocationDetailRepository::class);
    }

    public function testCouponAllocationServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CouponAllocationService::class, $this->service);
    }

    public function testGetOrderProductDiscountMapWithEmptyOrder(): void
    {
        $discountMap = $this->service->getOrderProductDiscountMap(999);

        $this->assertIsArray($discountMap);
        $this->assertEmpty($discountMap);
    }

    public function testGetOrderProductDiscountMapWithOrderProductId(): void
    {
        // 创建测试数据 - 使用 orderProductId
        $allocation1 = new CouponAllocationDetail();
        $allocation1->setCouponCode('COUPON001');
        $allocation1->setOrderId(1);
        $allocation1->setOrderProductId(101);
        $allocation1->setSkuId('SKU001');
        $allocation1->setAllocatedAmount('50.00');
        $allocation1->setAllocationRule('proportional');
        $this->persistAndFlush($allocation1);

        $allocation2 = new CouponAllocationDetail();
        $allocation2->setCouponCode('COUPON002');
        $allocation2->setOrderId(1);
        $allocation2->setOrderProductId(101);
        $allocation2->setSkuId('SKU001');
        $allocation2->setAllocatedAmount('30.00');
        $allocation2->setAllocationRule('proportional');
        $this->persistAndFlush($allocation2);

        $allocation3 = new CouponAllocationDetail();
        $allocation3->setCouponCode('COUPON003');
        $allocation3->setOrderId(1);
        $allocation3->setOrderProductId(102);
        $allocation3->setSkuId('SKU002');
        $allocation3->setAllocatedAmount('20.00');
        $allocation3->setAllocationRule('proportional');
        $this->persistAndFlush($allocation3);

        $discountMap = $this->service->getOrderProductDiscountMap(1);

        $this->assertIsArray($discountMap);
        $this->assertCount(2, $discountMap);
        $this->assertEquals(80.0, $discountMap[101]); // 50 + 30
        $this->assertEquals(20.0, $discountMap[102]);
    }

    public function testGetOrderProductDiscountMapWithSkuIdOnly(): void
    {
        // 创建测试数据 - 不使用 orderProductId，只有 skuId
        $allocation1 = new CouponAllocationDetail();
        $allocation1->setCouponCode('COUPON004');
        $allocation1->setOrderId(2);
        $allocation1->setOrderProductId(null);
        $allocation1->setSkuId('3');
        $allocation1->setAllocatedAmount('15.50');
        $allocation1->setAllocationRule('fixed');
        $this->persistAndFlush($allocation1);

        $allocation2 = new CouponAllocationDetail();
        $allocation2->setCouponCode('COUPON005');
        $allocation2->setOrderId(2);
        $allocation2->setOrderProductId(null);
        $allocation2->setSkuId('3');
        $allocation2->setAllocatedAmount('10.50');
        $allocation2->setAllocationRule('fixed');
        $this->persistAndFlush($allocation2);

        $discountMap = $this->service->getOrderProductDiscountMap(2);

        $this->assertIsArray($discountMap);
        $this->assertCount(1, $discountMap);
        $this->assertArrayHasKey('sku_3', $discountMap);
        $this->assertEquals(26.0, $discountMap['sku_3']); // 15.5 + 10.5
    }

    public function testGetOrderProductDiscountMapWithMixedKeys(): void
    {
        // 创建测试数据 - 混合使用 orderProductId 和 skuId
        $allocation1 = new CouponAllocationDetail();
        $allocation1->setCouponCode('COUPON006');
        $allocation1->setOrderId(3);
        $allocation1->setOrderProductId(201);
        $allocation1->setSkuId('4');
        $allocation1->setAllocatedAmount('25.00');
        $allocation1->setAllocationRule('proportional');
        $this->persistAndFlush($allocation1);

        $allocation2 = new CouponAllocationDetail();
        $allocation2->setCouponCode('COUPON007');
        $allocation2->setOrderId(3);
        $allocation2->setOrderProductId(null);
        $allocation2->setSkuId('5');
        $allocation2->setAllocatedAmount('35.00');
        $allocation2->setAllocationRule('fixed');
        $this->persistAndFlush($allocation2);

        $discountMap = $this->service->getOrderProductDiscountMap(3);

        $this->assertIsArray($discountMap);
        $this->assertCount(2, $discountMap);
        $this->assertEquals(25.0, $discountMap[201]);
        $this->assertArrayHasKey('sku_5', $discountMap);
        $this->assertEquals(35.0, $discountMap['sku_5']);
    }

    public function testGetProductDiscountAmountWithOrderProductId(): void
    {
        // 创建测试数据
        $allocation = new CouponAllocationDetail();
        $allocation->setCouponCode('COUPON008');
        $allocation->setOrderId(4);
        $allocation->setOrderProductId(301);
        $allocation->setSkuId('SKU006');
        $allocation->setAllocatedAmount('45.00');
        $allocation->setAllocationRule('proportional');
        $this->persistAndFlush($allocation);

        $discount = $this->service->getProductDiscountAmount(4, 301, 1006);

        $this->assertEquals(45.0, $discount);
    }

    public function testGetProductDiscountAmountWithSkuIdFallback(): void
    {
        // 创建测试数据 - 使用 skuId 作为备选
        $allocation = new CouponAllocationDetail();
        $allocation->setCouponCode('COUPON009');
        $allocation->setOrderId(5);
        $allocation->setOrderProductId(null);
        $allocation->setSkuId('7');
        $allocation->setAllocatedAmount('55.00');
        $allocation->setAllocationRule('fixed');
        $this->persistAndFlush($allocation);

        // 当 orderProductId 找不到时，应该通过 skuId 查找
        $discount = $this->service->getProductDiscountAmount(5, 999, 7);

        $this->assertEquals(55.0, $discount);
    }

    public function testGetProductDiscountAmountWithNoMatch(): void
    {
        // 创建测试数据
        $allocation = new CouponAllocationDetail();
        $allocation->setCouponCode('COUPON010');
        $allocation->setOrderId(6);
        $allocation->setOrderProductId(401);
        $allocation->setSkuId('SKU008');
        $allocation->setAllocatedAmount('65.00');
        $allocation->setAllocationRule('proportional');
        $this->persistAndFlush($allocation);

        // 查询不存在的订单商品
        $discount = $this->service->getProductDiscountAmount(6, 999, null);

        $this->assertEquals(0.0, $discount);
    }

    public function testGetProductDiscountAmountWithZeroAmount(): void
    {
        // 创建测试数据 - 分摊金额为 0
        $allocation = new CouponAllocationDetail();
        $allocation->setCouponCode('COUPON011');
        $allocation->setOrderId(7);
        $allocation->setOrderProductId(501);
        $allocation->setSkuId('SKU009');
        $allocation->setAllocatedAmount('0.00');
        $allocation->setAllocationRule('proportional');
        $this->persistAndFlush($allocation);

        $discount = $this->service->getProductDiscountAmount(7, 501, 9);

        $this->assertEquals(0.0, $discount);
    }

    public function testGetProductDiscountAmountWithMultipleAllocations(): void
    {
        // 创建测试数据 - 同一商品多个优惠券分摊
        $allocation1 = new CouponAllocationDetail();
        $allocation1->setCouponCode('COUPON012');
        $allocation1->setOrderId(8);
        $allocation1->setOrderProductId(601);
        $allocation1->setSkuId('SKU010');
        $allocation1->setAllocatedAmount('20.00');
        $allocation1->setAllocationRule('proportional');
        $this->persistAndFlush($allocation1);

        $allocation2 = new CouponAllocationDetail();
        $allocation2->setCouponCode('COUPON013');
        $allocation2->setOrderId(8);
        $allocation2->setOrderProductId(601);
        $allocation2->setSkuId('SKU010');
        $allocation2->setAllocatedAmount('15.00');
        $allocation2->setAllocationRule('proportional');
        $this->persistAndFlush($allocation2);

        $allocation3 = new CouponAllocationDetail();
        $allocation3->setCouponCode('COUPON014');
        $allocation3->setOrderId(8);
        $allocation3->setOrderProductId(601);
        $allocation3->setSkuId('SKU010');
        $allocation3->setAllocatedAmount('10.00');
        $allocation3->setAllocationRule('proportional');
        $this->persistAndFlush($allocation3);

        $discount = $this->service->getProductDiscountAmount(8, 601, 10);

        $this->assertEquals(45.0, $discount); // 20 + 15 + 10
    }

    public function testGetProductDiscountAmountPrioritizesOrderProductIdOverSkuId(): void
    {
        // 创建测试数据 - 同时存在 orderProductId 和 skuId 的记录
        $allocation1 = new CouponAllocationDetail();
        $allocation1->setCouponCode('COUPON015');
        $allocation1->setOrderId(9);
        $allocation1->setOrderProductId(701);
        $allocation1->setSkuId('11');
        $allocation1->setAllocatedAmount('100.00');
        $allocation1->setAllocationRule('proportional');
        $this->persistAndFlush($allocation1);

        $allocation2 = new CouponAllocationDetail();
        $allocation2->setCouponCode('COUPON016');
        $allocation2->setOrderId(9);
        $allocation2->setOrderProductId(null);
        $allocation2->setSkuId('11');
        $allocation2->setAllocatedAmount('50.00');
        $allocation2->setAllocationRule('fixed');
        $this->persistAndFlush($allocation2);

        // 当提供 orderProductId 时，应该优先使用它
        $discount = $this->service->getProductDiscountAmount(9, 701, 11);

        // 应该返回 orderProductId=701 的金额，而不是 skuId 的金额
        $this->assertEquals(100.0, $discount);
    }

    public function testGetProductDiscountAmountWithDecimalAmounts(): void
    {
        // 创建测试数据 - 测试小数金额的累加
        $allocation1 = new CouponAllocationDetail();
        $allocation1->setCouponCode('COUPON017');
        $allocation1->setOrderId(10);
        $allocation1->setOrderProductId(801);
        $allocation1->setSkuId('SKU012');
        $allocation1->setAllocatedAmount('12.34');
        $allocation1->setAllocationRule('proportional');
        $this->persistAndFlush($allocation1);

        $allocation2 = new CouponAllocationDetail();
        $allocation2->setCouponCode('COUPON018');
        $allocation2->setOrderId(10);
        $allocation2->setOrderProductId(801);
        $allocation2->setSkuId('SKU012');
        $allocation2->setAllocatedAmount('23.45');
        $allocation2->setAllocationRule('proportional');
        $this->persistAndFlush($allocation2);

        $allocation3 = new CouponAllocationDetail();
        $allocation3->setCouponCode('COUPON019');
        $allocation3->setOrderId(10);
        $allocation3->setOrderProductId(801);
        $allocation3->setSkuId('SKU012');
        $allocation3->setAllocatedAmount('34.56');
        $allocation3->setAllocationRule('proportional');
        $this->persistAndFlush($allocation3);

        $discount = $this->service->getProductDiscountAmount(10, 801, 12);

        $this->assertEquals(70.35, $discount); // 12.34 + 23.45 + 34.56
    }
}
