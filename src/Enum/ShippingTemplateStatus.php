<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum ShippingTemplateStatus: string implements BadgeInterface, Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function isActive(): bool
    {
        return self::ACTIVE === $this;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => '启用',
            self::INACTIVE => '禁用',
        };
    }

    public function getBadge(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'danger',
        };
    }
}
