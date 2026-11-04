<?php

namespace App\Enums;

enum LedgerSource: string
{
    case Invoice = 'invoice';
    case Payment = 'payment';
    case Return = 'return';
    case Cancellation = 'cancellation';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'فاتورة',
            self::Payment => 'دفعة',
            self::Return => 'مرتجع',
            self::Cancellation => 'إلغاء فاتورة',
            self::Adjustment => 'تسوية',
        };
    }
}
