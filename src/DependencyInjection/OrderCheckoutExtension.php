<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class OrderCheckoutExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }
}
