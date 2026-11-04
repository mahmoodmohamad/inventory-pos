<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;

/**
 * بيولّد باركود داخلي EAN-13 (بادئة 200 = استخدام داخلي حسب GS1)
 * وبيضمن إنه مش مكرر جوه المحل الحالي (الـ ShopScope بيتطبق تلقائي).
 */
final class BarcodeGenerator
{
    private const PREFIX = '200';

    public function internalEan13(): string
    {
        do {
            $base = self::PREFIX.str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
            $code = $base.$this->checkDigit($base);
        } while ($this->exists($code));

        return $code;
    }

    /** Check digit لـ EAN-13 من أول 12 رقم. */
    public function checkDigit(string $base12): int
    {
        $sum = 0;

        foreach (str_split($base12) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }

    public function isValidEan13(string $code): bool
    {
        return preg_match('/^\d{13}$/', $code) === 1
            && $this->checkDigit(substr($code, 0, 12)) === (int) $code[12];
    }

    private function exists(string $code): bool
    {
        // withTrashed لأن unique(shop_id, barcode) بيشمل المحذوف soft-delete
        return Product::query()->withTrashed()->where('barcode', $code)->exists();
    }
}
