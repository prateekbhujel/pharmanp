<?php

namespace App\Modules\Accounting\Support;

use App\Core\Support\MoneyAmount;

class AccountCatalog
{
    public static function all(): array
    {
        return [
            ['key' => 'cash', 'code' => '1100', 'name' => 'Cash in Hand', 'group' => 'Assets', 'nature' => 'debit'],
            ['key' => 'bank', 'code' => '1200', 'name' => 'Bank Account', 'group' => 'Assets', 'nature' => 'debit'],
            ['key' => 'receivable', 'code' => '1300', 'name' => 'Accounts Receivable', 'group' => 'Assets', 'nature' => 'debit'],
            ['key' => 'inventory', 'code' => '1400', 'name' => 'Inventory Stock', 'group' => 'Assets', 'nature' => 'debit'],
            ['key' => 'payable', 'code' => '2100', 'name' => 'Accounts Payable', 'group' => 'Liabilities', 'nature' => 'credit'],
            ['key' => 'capital', 'code' => '3100', 'name' => 'Capital', 'group' => 'Equity', 'nature' => 'credit'],
            ['key' => 'sales', 'code' => '4100', 'name' => 'Sales Income', 'group' => 'Income', 'nature' => 'credit'],
            ['key' => 'other_income', 'code' => '4200', 'name' => 'Other Income', 'group' => 'Income', 'nature' => 'credit'],
            ['key' => 'expense', 'code' => '5100', 'name' => 'Operating Expense', 'group' => 'Expenses', 'nature' => 'debit'],
            ['key' => 'purchase_return', 'code' => '5200', 'name' => 'Purchase Return / Adjustment', 'group' => 'Expenses', 'nature' => 'debit'],
        ];
    }

    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    public static function options(): array
    {
        return array_map(fn (array $account) => [
            'value' => $account['key'],
            'label' => $account['code'].' - '.$account['name'],
            'group' => $account['group'],
            'nature' => $account['nature'],
            'code' => $account['code'],
            'name' => $account['name'],
        ], self::all());
    }

    public static function labels(): array
    {
        return collect(self::all())->mapWithKeys(fn (array $account) => [$account['key'] => $account['name']])->all();
    }

    public static function grouped(): array
    {
        return collect(self::all())->groupBy('group')->map(fn ($rows) => array_values($rows->all()))->all();
    }

    public static function find(string $key): ?array
    {
        foreach (self::all() as $account) {
            if ($account['key'] === $key) {
                return $account;
            }
        }

        return null;
    }

    public static function closingBalance(mixed $debit, mixed $credit, string $nature): array
    {
        if ($nature === 'debit') {
            $netCents = MoneyAmount::cents($debit) - MoneyAmount::cents($credit);

            return [
                'amount' => MoneyAmount::fromCents(abs($netCents)),
                'side' => $netCents >= 0 ? 'Dr' : 'Cr',
            ];
        }

        $netCents = MoneyAmount::cents($credit) - MoneyAmount::cents($debit);

        return [
            'amount' => MoneyAmount::fromCents(abs($netCents)),
            'side' => $netCents >= 0 ? 'Cr' : 'Dr',
        ];
    }
}
