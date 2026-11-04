<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Wallet = 'wallet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'نقدي',
            self::Card => 'بطاقة',
            self::BankTransfer => 'تحويل بنكي',
            self::Wallet => 'محفظة إلكترونية',
            self::Other => 'أخرى',
        };
    }
}
