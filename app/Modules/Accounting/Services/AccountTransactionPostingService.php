<?php

namespace App\Modules\Accounting\Services;

use App\Core\Support\MoneyAmount;
use App\Models\User;
use App\Modules\Accounting\Repositories\Interfaces\AccountTransactionRepositoryInterface;
use App\Modules\Accounting\Support\AccountCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountTransactionPostingService
{
    public function __construct(
        private readonly AccountTransactionRepositoryInterface $transactions,
    ) {}

    public function replaceForSource(
        User $user,
        string $sourceType,
        int $sourceId,
        string $transactionDate,
        array $entries,
    ): void {
        DB::transaction(function () use ($user, $sourceType, $sourceId, $transactionDate, $entries) {
            $this->transactions->deleteBySource($sourceType, $sourceId);

            foreach ($entries as $entry) {
                $this->assertEntry($entry);

                $this->transactions->create([
                    'tenant_id' => $user->tenant_id,
                    'company_id' => $user->company_id,
                    'store_id' => $user->store_id,
                    'transaction_date' => $transactionDate,
                    'account_type' => $entry['account_type'],
                    'party_type' => $entry['party_type'] ?? null,
                    'party_id' => $entry['party_id'] ?? null,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'debit' => MoneyAmount::decimal($entry['debit'] ?? 0),
                    'credit' => MoneyAmount::decimal($entry['credit'] ?? 0),
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

        if (! $this->transactions->partyExists($partyType, (int) $partyId)) {
            throw ValidationException::withMessages([
                'party_id' => 'Selected party does not exist.',
            ]);
        }
    }
}
