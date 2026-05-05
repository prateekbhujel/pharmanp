<?php

namespace App\Core\Utils;

/**
 * Enterprise-grade high-precision math utility for accounting.
 * Bypasses PHP float precision issues by using BCMath.
 */
class Math
{
    public static function add($a, $b, int $scale = 4): string
    {
        return bcadd(static::format($a), static::format($b), $scale);
    }

    public static function sub($a, $b, int $scale = 4): string
    {
        return bcsub(static::format($a), static::format($b), $scale);
    }

    public static function mul($a, $b, int $scale = 4): string
    {
        return bcmul(static::format($a), static::format($b), $scale);
    }

    public static function div($a, $b, int $scale = 4): string
    {
        if (static::format($b) == 0) {
            return '0';
        }
        return bcdiv(static::format($a), static::format($b), $scale);
    }

    public static function round($value, int $precision = 2): string
    {
        $value = static::format($value);
        if (str_contains($value, '.')) {
            $parts = explode('.', $value);
            $decimal = substr($parts[1], 0, $precision + 1);
            if (strlen($decimal) > $precision) {
                // Simple round half up logic for bcmath
                $lastDigit = (int) substr($decimal, -1);
                $rounded = substr($decimal, 0, $precision);
                if ($lastDigit >= 5) {
                    $rounded = bcadd('0.' . $rounded, '0.' . str_repeat('0', $precision - 1) . '1', $precision);
                    return bcadd($parts[0], $rounded, $precision);
                }
            }
        }
        return number_format((float)$value, $precision, '.', '');
    }

    private static function format($value): string
    {
        return (string)($value ?? 0);
    }
}
