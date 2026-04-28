<?php

namespace App\Modules\Accounting\Services;

use App\Core\Services\DocumentNumberService;
use App\Models\User;
use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
    ) {}

    public function create(array $data, User $user): Voucher
    {
        return $this->persist($data, $user);
    }

    public function update(Voucher $voucher, array $data, User $user): Voucher
    {
        return $this->persist($data, $user, $voucher);
    }

    public function delete(Voucher $voucher): void
    {
        DB::transaction(function () use ($voucher) {
            AccountTransaction::query()
                ->where('source_type', 'voucher')
                ->where('source_id', $voucher->id)
                ->delete();

            $voucher->entries()->delete();
            $voucher->delete();
        });
    }

    private function persist(array $data, User $user, ?Voucher $voucher = null): Voucher
    {
        return DB::transaction(function () use ($data, $user, $voucher) {
            $debit = collect($data['entries'])->where('entry_type', 'debit')->sum(fn ($entry) => (float) $entry['amount']);
            $credit = collect($data['entries'])->where('entry_type', 'credit')->sum(fn ($entry) => (float) $entry['amount']);

            if (round($debit, 2) !== round($credit, 2)) {
                throw ValidationException::withMessages(['entries' => 'Voucher debit and credit totals must match.']);
            }

            if ($voucher) {
                AccountTransaction::query()
                    ->where('source_type', 'voucher')
                    ->where('source_id', $voucher->id)
                    ->delete();

                $voucher->entries()->delete();
                $voucher->update([
                    'voucher_date' => $data['voucher_date'],
                    'voucher_type' => $data['voucher_type'],
                    'total_amount' => round($debit, 2),
                    'notes' => $data['notes'] ?? null,
                    'updated_by' => $user->id,
                ]);
            } else {
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
            }

            foreach (array_values($data['entries']) as $index => $entry) {
                $this->assertParty($entry['party_type'] ?? null, $entry['party_id'] ?? null);

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
        return $this->numbers->next('voucher', 'vouchers');
    }

    private function assertParty(?string $partyType, ?int $partyId): void
    {
        if (! $partyType || ! $partyId || $partyType === 'other') {
            return;
        }

        $exists = match ($partyType) {
            'supplier' => Supplier::query()->whereKey($partyId)->exists(),
            'customer' => Customer::query()->whereKey($partyId)->exists(),
            default => true,
        };

        if (! $exists) {
            throw ValidationException::withMessages([
                'party_id' => 'Selected party does not exist.',
            ]);
        }
    }
}
