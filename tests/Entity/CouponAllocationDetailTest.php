<?php

namespace Tourze\OrderCheckoutBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Entity\CouponAllocationDetail;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(CouponAllocationDetail::class)]
final class CouponAllocationDetailTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new CouponAllocationDetail();
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return iterable<array{0: string, 1: mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'couponCode' => ['couponCode', 'CODE'];
        yield 'orderId' => ['orderId', 1];
        yield 'orderProductId' => ['orderProductId', 2];
        yield 'skuId' => ['skuId', 'SKU1'];
        yield 'allocatedAmount' => ['allocatedAmount', '5.00'];
        yield 'allocationRule' => ['allocationRule', 'proportional'];
    }
}
