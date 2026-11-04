<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    /**
     * تقريب half-up (للموجب والسالب) من غير floats.
     *
     * @param  numeric-string  $value
     * @param  int<0, max>  $scale
     * @return numeric-string
     */
    public static function round(string $value, int $scale = 2): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';
        assert(is_numeric($half));

        $signedHalf = bccomp($value, '0', 10) >= 0 ? $half : '-'.$half;
        assert(is_numeric($signedHalf));

        return bcadd($value, $signedHalf, $scale);
    }

    /**
     * يحوّل أي قيمة (string/int/float/null) لرقم نصي صالح لدوال bcmath.
     *
     * @return numeric-string
     */
    public static function norm(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('Invalid numeric value.');
        }

        $result = trim((string) $value);

        // نمنع الصيغ العلمية (1e3) لأن bcmath بيرفضها
        if (preg_match('/^-?\d+(\.\d+)?$/', $result) !== 1) {
            throw new InvalidArgumentException('Invalid numeric value.');
        }

        assert(is_numeric($result));

        return $result;
    }

    /** @param int<0, max> $scale */
    public static function cmp(mixed $a, mixed $b, int $scale = 2): int
    {
        return bccomp(self::norm($a), self::norm($b), $scale);
    }

    /**
     * @param  int<0, max>  $scale
     * @return numeric-string
     */
    public static function add(mixed $a, mixed $b, int $scale = 2): string
    {
        return bcadd(self::norm($a), self::norm($b), $scale);
    }

    /**
     * @param  int<0, max>  $scale
     * @return numeric-string
     */
    public static function sub(mixed $a, mixed $b, int $scale = 2): string
    {
        return bcsub(self::norm($a), self::norm($b), $scale);
    }

    /**
     * @param  int<0, max>  $scale
     * @return numeric-string
     */
    public static function mul(mixed $a, mixed $b, int $scale = 2): string
    {
        return bcmul(self::norm($a), self::norm($b), $scale);
    }
}
