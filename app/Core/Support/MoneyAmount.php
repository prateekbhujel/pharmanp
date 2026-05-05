<?php

namespace App\Core\Support;

final class MoneyAmount
{
    public static function cents(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $normalized = str_replace(',', '', trim((string) $value));
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = preg_replace('/\D/', '', $whole) ?: '0';
        $fraction = preg_replace('/\D/', '', $fraction);
        $thirdDecimal = (int) ($fraction[2] ?? 0);
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        $cents = ((int) $whole * 100) + (int) $fraction;

        if ($thirdDecimal >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function decimal(mixed $value): string
    {
        return self::fromCents(self::cents($value));
    }

    public static function add(mixed $left, mixed $right): string
    {
        return self::fromCents(self::cents($left) + self::cents($right));
    }

    public static function subtract(mixed $left, mixed $right): string
    {
        return self::fromCents(self::cents($left) - self::cents($right));
    }

    public static function absoluteDifference(mixed $left, mixed $right): string
    {
        return self::fromCents(abs(self::cents($left) - self::cents($right)));
    }

    public static function negative(mixed $value): string
    {
        return self::fromCents(-1 * self::cents($value));
    }
}
