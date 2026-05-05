<?php

namespace App\Modules\Accounting\Services;

use App\Core\DTOs\TableQueryData;
use App\Core\Services\DocumentNumberService;
use App\Models\User;
use App\Modules\Accounting\DTOs\VoucherData;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Repositories\Interfaces\VoucherRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly VoucherRepositoryInterface $vouchers,
    ) {}

    public function table(TableQueryData $table, ?User $user = null)
    {
        return $this->vouchers->paginate($table, $user);
    }

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
            $this->vouchers->deleteTransactions($voucher);
            $this->vouchers->deleteEntries($voucher);
            $this->vouchers->delete($voucher);
        });
    }

    private function persist(array $data, User $user, ?Voucher $voucher = null): Voucher
    {
        $dto = VoucherData::fromArray($data);

        return DB::transaction(function () use ($dto, $user, $voucher) {
            $data = $dto->toArray();
            $debit = collect($data['entries'])->where('entry_type', 'debit')->sum(fn ($entry) => (float) $entry['amount']);
            $credit = collect($data['entries'])->where('entry_type', 'credit')->sum(fn ($entry) => (float) $entry['amount']);

            if (round($debit, 2) !== round($credit, 2)) {
                throw ValidationException::withMessages(['entries' => 'Voucher debit and credit totals must match.']);
            }

            if ($voucher) {
                $this->vouchers->deleteTransactions($voucher);
                $this->vouchers->deleteEntries($voucher);
                $voucher = $this->vouchers->update($voucher, [
                    'voucher_date' => $data['voucher_date'],
                    'voucher_type' => $data['voucher_type'],
                    'total_amount' => round($debit, 2),
                    'notes' => $data['notes'] ?? null,
                    'updated_by' => $user->id,
                ]);
            } else {
                $voucher = $this->vouchers->create([
                    'tenant_id' => $user->tenant_id,
                    'company_id' => $user->company_id,
                    'voucher_no' => $this->nextNumber($data['voucher_date'], $user),
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

                $voucherEntry = $this->vouchers->createEntry($voucher, [
                    'line_no' => $index + 1,
                    'account_type' => $entry['account_type'],
                    'party_type' => $entry['party_type'] ?? null,
                    'party_id' => $entry['party_id'] ?? null,
                    'entry_type' => $entry['entry_type'],
                    'amount' => $entry['amount'],
                    'notes' => $entry['notes'] ?? null,
                ]);

                $this->vouchers->createTransaction([
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

            return $this->vouchers->fresh($voucher);
        });
    }

    private function nextNumber(string $voucherDate, User $user): string
    {
        return $this->numbers->next('voucher', 'vouchers', Carbon::parse($voucherDate), $user);
    }

    private function assertParty(?string $partyType, ?int $partyId): void
    {
        if (! $this->vouchers->partyExists($partyType, $partyId)) {
            throw ValidationException::withMessages([
                'party_id' => 'Selected party does not exist.',
            ]);
        }
    }
}
