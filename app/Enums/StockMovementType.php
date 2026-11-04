<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case SaleReturn = 'sale_return';
    case PurchaseReturn = 'purchase_return';
    case Adjustment = 'adjustment';
    case Opening = 'opening';

    /** الحركات اللي بتدخل في حساب المتوسط المرجّح */
    public function entersAverage(): bool
    {
        return match ($this) {
            self::Purchase, self::SaleReturn, self::Opening => true,
            default => false,
        };
    }

    /** الحركات اللي بتحدّث آخر سعر شراء */
    public function updatesLastCost(): bool
    {
        return $this === self::Purchase || $this === self::Opening;
    }

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'شراء',
            self::Sale => 'بيع',
            self::SaleReturn => 'مرتجع بيع',
            self::PurchaseReturn => 'مرتجع شراء',
            self::Adjustment => 'تسوية',
            self::Opening => 'رصيد افتتاحي',
        };
    }
}
