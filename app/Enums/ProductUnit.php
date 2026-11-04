<?php

// app/Enums/ProductUnit.php

namespace App\Enums;

enum ProductUnit: string
{
    case Piece = 'piece';
    case Set = 'set';
    case Pair = 'pair';
    case Liter = 'liter';
    case Box = 'box';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'قطعة',
            self::Set => 'طقم',
            self::Pair => 'زوج',
            self::Liter => 'لتر',
            self::Box => 'علبة',
        };
    }
}
