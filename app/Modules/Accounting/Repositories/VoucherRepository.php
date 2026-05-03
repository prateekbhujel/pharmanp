<?php

namespace App\Modules\Accounting\Repositories;

use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Models\VoucherEntry;
use App\Modules\Accounting\Repositories\Interfaces\VoucherRepositoryInterface;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;

class VoucherRepository implements VoucherRepositoryInterface
{
    public function create(array $data): Voucher
    {
        return Voucher::query()->create($data);
    }

    public function update(Voucher $voucher, array $data): Voucher
    {
        $voucher->update($data);

        return $voucher;
    }

    public function createEntry(Voucher $voucher, array $data): VoucherEntry
    {
        return $voucher->entries()->create($data);
    }

    public function createTransaction(array $data): AccountTransaction
    {
        return AccountTransaction::query()->create($data);
    }

    public function deleteEntries(Voucher $voucher): void
    {
        $voucher->entries()->delete();
    }

    public function deleteTransactions(Voucher $voucher): void
    {
        AccountTransaction::query()
            ->where('source_type', 'voucher')
            ->where('source_id', $voucher->id)
            ->delete();
    }

    public function delete(Voucher $voucher): void
    {
        $voucher->delete();
    }

    public function fresh(Voucher $voucher): Voucher
    {
        return $voucher->fresh('entries');
    }

    public function partyExists(?string $partyType, ?int $partyId): bool
    {
        if (! $partyType || ! $partyId || $partyType === 'other') {
            return true;
        }

        return match ($partyType) {
            'supplier' => Supplier::query()->whereKey($partyId)->exists(),
            'customer' => Customer::query()->whereKey($partyId)->exists(),
            default => true,
        };
    }
}
