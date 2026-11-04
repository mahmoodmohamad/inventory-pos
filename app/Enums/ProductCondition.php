<?php

// app/Enums/ProductCondition.php

namespace App\Enums;

enum ProductCondition: string
{
    case Original = 'original';
    case Commercial = 'commercial';
    case Imported = 'imported';
    case Used = 'used';

    public function label(): string
    {
        return match ($this) {
            self::Original => 'أصلي',
            self::Commercial => 'تجاري',
            self::Imported => 'مستورد',
            self::Used => 'مستعمل',
        };
    }
}
