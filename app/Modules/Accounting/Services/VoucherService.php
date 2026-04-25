<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function create(array $data, User $user): Voucher
    {
        return DB::transaction(function () use ($data, $user) {
            $debit = collect($data['entries'])->where('entry_type', 'debit')->sum(fn ($entry) => (float) $entry['amount']);
            $credit = collect($data['entries'])->where('entry_type', 'credit')->sum(fn ($entry) => (float) $entry['amount']);

            if (round($debit, 2) !== round($credit, 2)) {
                throw ValidationException::withMessages(['entries' => 'Voucher debit and credit totals must match.']);
            }

            $voucher = Voucher::query()->create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'voucher_no' => $this->nextNumber(),
                'voucher_date' => $data['voucher_date'],
                'voucher_type' => $data['voucher_type'],
                'total_amount' => round($debit, 2),
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach (array_values($data['entries']) as $index => $entry) {
                $voucherEntry = $voucher->entries()->create([
                    'line_no' => $index + 1,
                    'account_type' => $entry['account_type'],
                    'party_type' => $entry['party_type'] ?? null,
                    'party_id' => $entry['party_id'] ?? null,
                    'entry_type' => $entry['entry_type'],
                    'amount' => $entry['amount'],
                    'notes' => $entry['notes'] ?? null,
                ]);

                AccountTransaction::query()->create([
                    'tenant_id' => $user->tenant_id,
                    'company_id' => $user->company_id,
                    'transaction_date' => $data['voucher_date'],
                    'account_type' => $entry['account_type'],
                    'party_type' => $entry['party_type'] ?? null,
                    'party_id' => $entry['party_id'] ?? null,
                    'source_type' => 'voucher',
                    'source_id' => $voucher->id,
                    'debit' => $entry['entry_type'] === 'debit' ? $entry['amount'] : 0,
                    'credit' => $entry['entry_type'] === 'credit' ? $entry['amount'] : 0,
                    'notes' => $voucherEntry->notes,
                    'created_by' => $user->id,
                ]);
            }

            return $voucher->fresh('entries');
        });
    }

    private function nextNumber(): string
    {
        $nextId = ((int) DB::table('vouchers')->lockForUpdate()->max('id')) + 1;

        return 'VCH-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }
}
