<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\Enum\ChargeType;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(ChargeType::class)]
final class ChargeTypeTest extends AbstractEnumTestCase
{
    public function testGetUnit(): void
    {
        $this->assertSame('kg', ChargeType::WEIGHT->getUnit());
        $this->assertSame('件', ChargeType::QUANTITY->getUnit());
        $this->assertSame('m³', ChargeType::VOLUME->getUnit());
    }

    public function testToArray(): void
    {
        // 测试 WEIGHT 枚举的 toArray 方法
        $weightArray = ChargeType::WEIGHT->toArray();
        $this->assertIsArray($weightArray);
        $this->assertArrayHasKey('value', $weightArray);
        $this->assertArrayHasKey('label', $weightArray);
        $this->assertSame('weight', $weightArray['value']);
        $this->assertSame('按重量', $weightArray['label']);

        // 测试 QUANTITY 枚举的 toArray 方法
        $quantityArray = ChargeType::QUANTITY->toArray();
        $this->assertIsArray($quantityArray);
        $this->assertSame('quantity', $quantityArray['value']);
        $this->assertSame('按件数', $quantityArray['label']);

        // 测试 VOLUME 枚举的 toArray 方法
        $volumeArray = ChargeType::VOLUME->toArray();
        $this->assertIsArray($volumeArray);
        $this->assertSame('volume', $volumeArray['value']);
        $this->assertSame('按体积', $volumeArray['label']);
    }
}
