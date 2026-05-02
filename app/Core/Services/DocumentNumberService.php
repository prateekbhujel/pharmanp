<?php

namespace App\Core\Services;

use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentNumberService
{
    public const SETTING_KEY = 'document_numbering';

    public static function defaults(): array
    {
        return [
            'purchase_order' => [
                'label' => 'Purchase Order',
                'prefix' => 'PO',
                'date_format' => 'Ymd',
                'separator' => '-',
                'padding' => 5,
            ],
            'purchase' => [
                'label' => 'Purchase Bill',
                'prefix' => 'PUR',
                'date_format' => 'Ymd',
                'separator' => '-',
                'padding' => 5,
            ],
            'sales_invoice' => [
                'label' => 'Sales Invoice',
                'prefix' => 'SI',
                'date_format' => 'Ymd',
                'separator' => '-',
                'padding' => 5,
            ],
            'voucher' => [
                'label' => 'Accounting Voucher',
                'prefix' => 'VCH',
                'date_format' => 'Ymd',
                'separator' => '-',
                'padding' => 5,
            ],
            'payment' => [
                'label' => 'Payment Receipt',
                'prefix' => 'PAY',
                'date_format' => 'Ymd',
                'separator' => '-',
                'padding' => 5,
            ],
            'supplier' => [
                'label' => 'Supplier Code',
                'prefix' => 'SUP',
                'date_format' => 'none',
                'separator' => '-',
                'padding' => 5,
            ],
            'customer' => [
                'label' => 'Customer Code',
                'prefix' => 'CUS',
                'date_format' => 'none',
                'separator' => '-',
                'padding' => 5,
            ],
            'product' => [
                'label' => 'Product Code',
                'prefix' => 'ITM',
                'date_format' => 'none',
                'separator' => '-',
                'padding' => 5,
            ],
            'employee' => [
                'label' => 'Employee Code',
                'prefix' => 'EMP',
                'date_format' => 'none',
                'separator' => '-',
                'padding' => 5,
            ],
        ];
    }

    public static function mergedSettings(): array
    {
        $saved = Setting::getValue(self::SETTING_KEY, []);

        return collect(self::defaults())
            ->mapWithKeys(fn (array $config, string $key) => [
                $key => [
                    ...$config,
                    ...(is_array($saved[$key] ?? null) ? $saved[$key] : []),
                ],
            ])
            ->all();
    }

    public function next(string $type, string $table, ?CarbonInterface $date = null): string
    {
        $config = self::mergedSettings()[$type] ?? self::defaults()[$type] ?? null;

        if (! $config) {
            throw new \InvalidArgumentException("Unknown document number type [{$type}].");
        }

        $nextId = ((int) DB::table($table)->lockForUpdate()->max('id')) + 1;

        return $this->format($config, $nextId, $date);
    }

    public function preview(string $type, int $sequence = 1, ?CarbonInterface $date = null): string
    {
        $config = self::mergedSettings()[$type] ?? self::defaults()[$type] ?? null;

        if (! $config) {
            throw new \InvalidArgumentException("Unknown document number type [{$type}].");
        }

        return $this->format($config, $sequence, $date);
    }

    private function format(array $config, int $sequence, ?CarbonInterface $date = null): string
    {
        $date ??= now();
        $separator = $this->cleanSeparator($config['separator'] ?? '-');
        $parts = array_filter([
            $this->cleanToken($config['prefix'] ?? ''),
            $this->dateToken((string) ($config['date_format'] ?? 'Ymd'), $date),
            str_pad((string) $sequence, max(1, min(12, (int) ($config['padding'] ?? 5))), '0', STR_PAD_LEFT),
        ], fn (?string $part) => $part !== null && $part !== '');

        return implode($separator, $parts);
    }

    private function dateToken(string $format, CarbonInterface $date): ?string
    {
        return match ($format) {
            'none' => null,
            'Y' => $date->format('Y'),
            'Ym' => $date->format('Ym'),
            default => $date->format('Ymd'),
        };
    }

    private function cleanToken(string $value): string
    {
        return Str::of($value)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->limit(12, '')
            ->toString();
    }

    private function cleanSeparator(string $value): string
    {
        return in_array($value, ['-', '/', '.', ''], true) ? $value : '-';
    }
}
