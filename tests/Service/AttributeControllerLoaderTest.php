<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouteCollection;
use Tourze\OrderCheckoutBundle\Service\AttributeControllerLoader;

/**
 * @internal
 * @phpstan-ignore-next-line test.shouldExtendKernelTestCase
 */
#[CoversClass(AttributeControllerLoader::class)]
final class AttributeControllerLoaderTest extends TestCase
{

    public function testLoadReturnsRouteCollection(): void
    {
        $loader = new AttributeControllerLoader();
        $result = $loader->load('resource');

        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    public function testSupportsReturnsFalse(): void
    {
        $loader = new AttributeControllerLoader();
        $this->assertFalse($loader->supports('resource'));
        $this->assertFalse($loader->supports('resource', 'type'));
    }

    public function testAutoloadReturnsRouteCollection(): void
    {
        $loader = new AttributeControllerLoader();
        $result = $loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    public function testLoadAndAutoloadReturnSameCollection(): void
    {
        $loader = new AttributeControllerLoader();
        $loadResult = $loader->load('resource');
        $autoloadResult = $loader->autoload();

        $this->assertSame($loadResult, $autoloadResult);
    }
}
