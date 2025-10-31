<?php

declare(strict_types=1);

namespace Tourze\OrderCheckoutBundle\Enum;

use Tourze\EnumExtra\BadgeInterface;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum ChargeType: string implements BadgeInterface, Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case WEIGHT = 'weight';
    case QUANTITY = 'quantity';
    case VOLUME = 'volume';

    public function getLabel(): string
    {
        return match ($this) {
            self::WEIGHT => '按重量',
            self::QUANTITY => '按件数',
            self::VOLUME => '按体积',
        };
    }

    public function getUnit(): string
    {
        return match ($this) {
            self::WEIGHT => 'kg',
            self::QUANTITY => '件',
            self::VOLUME => 'm³',
        };
    }

    public function getBadge(): string
    {
        return match ($this) {
            self::WEIGHT => 'primary',
            self::QUANTITY => 'success',
            self::VOLUME => 'info',
        };
    }
}
