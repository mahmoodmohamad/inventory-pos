<?php

namespace App\Enums;

enum PriceType: string
{
    case Retail = 'retail';
    case Wholesale = 'wholesale';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'قطاعي',
            self::Wholesale => 'جملة',
        };
    }
}
