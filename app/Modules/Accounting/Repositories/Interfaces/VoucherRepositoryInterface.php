<?php

namespace App\Modules\Accounting\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Models\VoucherEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VoucherRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function create(array $data): Voucher;

    public function update(Voucher $voucher, array $data): Voucher;

    public function createEntry(Voucher $voucher, array $data): VoucherEntry;

    public function createTransaction(array $data): AccountTransaction;

    public function deleteEntries(Voucher $voucher): void;

    public function deleteTransactions(Voucher $voucher): void;

    public function delete(Voucher $voucher): void;

    public function fresh(Voucher $voucher): Voucher;

    public function partyExists(?string $partyType, ?int $partyId): bool;
}
