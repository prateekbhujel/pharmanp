<?php

namespace App\Core\Services;

use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'purchase_return' => [
                'label' => 'Purchase Return',
                'prefix' => 'PRN',
                'date_format' => 'Ymd',
                'separator' => '-',
                'padding' => 5,
            ],
            'sales_return' => [
                'label' => 'Sales Return',
                'prefix' => 'SR',
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

    public function next(string $type, string $table, ?CarbonInterface $date = null, ?User $user = null): string
    {
        $config = self::mergedSettings()[$type] ?? self::defaults()[$type] ?? null;

        if (! $config) {
            throw new \InvalidArgumentException("Unknown document number type [{$type}].");
        }

        if (! Schema::hasTable('document_sequences')) {
            return $this->legacyNext($config, $table, $date);
        }

        $nextId = DB::transaction(function () use ($type, $table, $config, $date, $user): int {
            $datePart = $this->dateToken((string) ($config['date_format'] ?? 'Ymd'), $date ?? now()) ?? '';
            $scopeKey = $this->scopeKey($user);
            $query = $this->sequenceQuery($scopeKey, $type, $datePart);
            $row = $query->lockForUpdate()->first();

            if (! $row) {
                $this->createSequenceRow($scopeKey, $type, $datePart, $table, $user);
                $row = $this->sequenceQuery($scopeKey, $type, $datePart)->lockForUpdate()->first();
            }

            $next = ((int) $row->last_sequence) + 1;

            $this->sequenceQuery($scopeKey, $type, $datePart)->update([
                'last_sequence' => $next,
                'updated_at' => now(),
            ]);

            return $next;
        });

        return $this->format($config, $nextId, $date);
    }

    private function legacyNext(array $config, string $table, ?CarbonInterface $date): string
    {
        $nextId = ((int) DB::table($table)->lockForUpdate()->max('id')) + 1;

        return $this->format($config, $nextId, $date);
    }

    private function createSequenceRow(string $scopeKey, string $type, string $datePart, string $table, ?User $user): void
    {
        try {
            DB::table('document_sequences')->insert([
                'scope_key' => $scopeKey,
                'type' => $type,
                'date_part' => $datePart,
                'tenant_id' => null,
                'company_id' => null,
                'last_sequence' => (int) DB::table($table)->max('id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException) {
            // Another request initialized the sequence first; the caller locks and increments it next.
        }
    }

    private function sequenceQuery(string $scopeKey, string $type, string $datePart): Builder
    {
        return DB::table('document_sequences')
            ->where('scope_key', $scopeKey)
            ->where('type', $type)
            ->where('date_part', $datePart);
    }

    private function scopeKey(?User $user): string
    {
        return 'global';
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
