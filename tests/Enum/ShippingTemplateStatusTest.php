<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Enum\ShippingTemplateStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(ShippingTemplateStatus::class)]
final class ShippingTemplateStatusTest extends AbstractEnumTestCase
{
    public function testIsActive(): void
    {
        $this->assertTrue(ShippingTemplateStatus::ACTIVE->isActive());
        $this->assertFalse(ShippingTemplateStatus::INACTIVE->isActive());
    }

    public function testToArray(): void
    {
        // 测试 ACTIVE 枚举的 toArray 方法
        $activeArray = ShippingTemplateStatus::ACTIVE->toArray();
        $this->assertIsArray($activeArray);
        $this->assertArrayHasKey('value', $activeArray);
        $this->assertArrayHasKey('label', $activeArray);
        $this->assertSame('active', $activeArray['value']);
        $this->assertSame('启用', $activeArray['label']);

        // 测试 INACTIVE 枚举的 toArray 方法
        $inactiveArray = ShippingTemplateStatus::INACTIVE->toArray();
        $this->assertIsArray($inactiveArray);
        $this->assertSame('inactive', $inactiveArray['value']);
        $this->assertSame('禁用', $inactiveArray['label']);
    }
}
