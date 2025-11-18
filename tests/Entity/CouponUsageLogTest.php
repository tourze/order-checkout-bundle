<?php

namespace Tourze\OrderCheckoutBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Entity\CouponUsageLog;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(CouponUsageLog::class)]
final class CouponUsageLogTest extends AbstractEntityTestCase
{
    protected function createEntity(): object
    {
        return new CouponUsageLog();
    }

    /**
     * 提供属性及其样本值的 Data Provider.
     *
     * @return iterable<array{0: string, 1: mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'couponCode' => ['couponCode', 'CODE'];
        yield 'userIdentifier' => ['userIdentifier', 'user'];
        yield 'orderId' => ['orderId', 1];
        yield 'orderNumber' => ['orderNumber', 'NO'];
        yield 'usageTime' => ['usageTime', new \DateTimeImmutable()];
        yield 'discountAmount' => ['discountAmount', '10.00'];
        yield 'couponType' => ['couponType', 'full_reduction'];
        yield 'metadata' => ['metadata', ['key' => 'value']];
    }
}
