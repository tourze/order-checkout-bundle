<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\OrderCheckoutBundle\DependencyInjection\OrderCheckoutExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(OrderCheckoutExtension::class)]
final class OrderCheckoutExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
}
