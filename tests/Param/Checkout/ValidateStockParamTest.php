<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Param\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\OrderCheckoutBundle\Param\Checkout\ValidateStockParam;

/**
 * @internal
 */
#[CoversClass(ValidateStockParam::class)]
final class ValidateStockParamTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $param = new ValidateStockParam();

        self::assertInstanceOf(ValidateStockParam::class, $param);
    }
}
