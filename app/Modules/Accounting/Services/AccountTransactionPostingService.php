<?php

namespace App\Modules\Accounting\Services;

use App\Models\User;
use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Support\AccountCatalog;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountTransactionPostingService
{
    public function replaceForSource(
        User $user,
        string $sourceType,
        int $sourceId,
        string $transactionDate,
        array $entries,
    ): void {
        DB::transaction(function () use ($user, $sourceType, $sourceId, $transactionDate, $entries) {
            AccountTransaction::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->delete();

            foreach ($entries as $entry) {
                $this->assertEntry($entry);

                AccountTransaction::query()->create([
                    'tenant_id' => $user->tenant_id,
                    'company_id' => $user->company_id,
                    'transaction_date' => $transactionDate,
                    'account_type' => $entry['account_type'],
                    'party_type' => $entry['party_type'] ?? null,
                    'party_id' => $entry['party_id'] ?? null,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'debit' => round((float) ($entry['debit'] ?? 0), 2),
                    'credit' => round((float) ($entry['credit'] ?? 0), 2),
                    'notes' => $entry['notes'] ?? null,
                    'created_by' => $user->id,
                ]);
            }
        });
    }

    private function assertEntry(array $entry): void
    {
        if (! in_array($entry['account_type'] ?? null, AccountCatalog::keys(), true)) {
            throw ValidationException::withMessages([
                'account_type' => 'Unsupported account type for transaction posting.',
            ]);
        }

        $partyType = $entry['party_type'] ?? null;
        $partyId = $entry['party_id'] ?? null;

        if (! $partyType || ! $partyId) {
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
