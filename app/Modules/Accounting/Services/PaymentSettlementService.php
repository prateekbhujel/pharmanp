<?php

namespace App\Modules\Accounting\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Core\Services\DocumentNumberService;
use App\Core\Support\ApiResponse;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Accounting\DTOs\PaymentData;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentBillAllocation;
use App\Modules\Accounting\Repositories\Interfaces\PaymentRepositoryInterface;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentSettlementService
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly AccountTransactionPostingService $accounts,
        private readonly PayableService $payables,
        private readonly ReceivableService $receivables,
        private readonly PaymentRepositoryInterface $payments,
    ) {}

    public function table(TableQueryData $table, ?User $user = null): array
    {
        $page = $this->payments->paginate($table, $user);

        return [
            'data' => $page->getCollection()
                ->map(fn (Payment $payment) => $this->payload($payment))
                ->values(),
            'meta' => ApiResponse::paginationMeta($page),
            'lookups' => $this->payments->lookups(),
        ];
    }

    public function save(array $data, User $user): Payment
    {
        $dto = PaymentData::fromArray($data);

        return DB::transaction(function () use ($dto, $user) {
            $data = $dto->toArray();
            $payment = $this->payments->getForSettlement($dto->id, $user);

            if ($payment->exists) {
                $this->reverse($payment, $user);
                $this->payments->deleteAllocations((int) $payment->id);
                $this->accounts->replaceForSource($user, 'payment', $payment->id, now()->toDateString(), []);
            }

            $this->assertParty((string) $data['party_type'], (int) $data['party_id'], $user);
            $paymentMode = $this->payments->paymentMode((int) $data['payment_mode_id'], $user);

            $payment->fill([
                'tenant_id' => $payment->tenant_id ?: $user->tenant_id,
                'company_id' => $payment->company_id ?: $user->company_id,
                'store_id' => $payment->store_id ?: $user->store_id,
                'payment_no' => $payment->payment_no ?: $this->numbers->next('payment', 'payments', Carbon::parse($data['payment_date']), $user),
                'payment_date' => $data['payment_date'],
                'direction' => $data['direction'],
                'party_type' => $data['party_type'],
                'party_id' => (int) $data['party_id'],
                'payment_mode_id' => $paymentMode->id,
                'payment_mode' => $paymentMode->data ?: strtolower($paymentMode->name),
                'amount' => $this->money($this->cents($data['amount'])),
                'reference_no' => $data['reference_no'] ?? null,
                'notes' => $data['notes'] ?? null,
                'updated_by' => $user->id,
            ]);

            if (! $payment->exists) {
                $payment->created_by = $user->id;
            }

            $payment->save();
            $allocatedCents = $this->allocateBills($payment, collect($data['allocations'] ?? []), $user);
            $paymentCents = $this->cents($payment->amount);

            if ($allocatedCents > $paymentCents) {
                throw ValidationException::withMessages([
                    'allocations' => 'Allocated total cannot exceed payment amount.',
                ]);
            }

            $this->accounts->replaceForSource(
                $user,
                'payment',
                $payment->id,
                $payment->payment_date->toDateString(),
                $this->journalEntries($payment, $paymentMode),
            );

            return $payment->fresh(['customer', 'supplier', 'paymentModeOption:id,name,data', 'allocations']);
        });
    }

    public function delete(Payment $payment, User $user): void
    {
        DB::transaction(function () use ($payment, $user) {
            $payment->load('allocations');
            $payment = $this->payments->getForSettlement((int) $payment->id, $user);
            $this->reverse($payment, $user);
            $this->payments->deleteAllocations((int) $payment->id);
            $this->accounts->replaceForSource($user, 'payment', $payment->id, now()->toDateString(), []);
            $payment->delete();
        });
    }

    public function outstandingBills(string $partyType, int $partyId, ?User $user = null): Collection
    {
        if ($partyType === 'customer') {
            return $this->payments->outstandingCustomerBills($partyId, $user)
                ->map(fn (SalesInvoice $invoice) => [
                    'bill_id' => $invoice->id,
                    'bill_type' => 'sales_invoice',
                    'bill_number' => $invoice->invoice_no,
                    'bill_date' => $invoice->invoice_date?->format('M j, Y'),
                    'due_date' => $invoice->due_date?->toDateString(),
                    'net_amount' => (float) $invoice->grand_total,
                    'total_paid' => (float) $invoice->paid_amount,
                    'outstanding' => $this->billOutstanding($invoice),
                ])->values();
        }

        return $this->payments->outstandingSupplierBills($partyId, $user)
            ->map(fn (Purchase $purchase) => [
                'bill_id' => $purchase->id,
                'bill_type' => 'purchase',
                'bill_number' => $purchase->purchase_no,
                'bill_date' => $purchase->purchase_date?->format('M j, Y'),
                'due_date' => $purchase->due_date?->toDateString(),
                'net_amount' => (float) $purchase->grand_total,
                'total_paid' => (float) $purchase->paid_amount,
                'outstanding' => $this->billOutstanding($purchase),
            ])->values();
    }

    public function payloadForUser(Payment $payment, User $user, bool $includeAllocations = false): array
    {
        $payment = $this->payments->getForSettlement((int) $payment->id, $user);

        return $this->payload($payment, $includeAllocations);
    }

    public function payload(Payment $payment, bool $includeAllocations = false): array
    {
        $payment->loadMissing(['customer', 'supplier', 'paymentModeOption:id,name,data', 'allocations']);

        $payload = [
            'id' => $payment->id,
            'payment_no' => $payment->payment_no,
            'payment_date' => $payment->payment_date?->format('Y-m-d'),
            'payment_date_display' => $payment->payment_date?->format('M j, Y'),
            'direction' => $payment->direction,
            'direction_label' => $payment->direction === 'in' ? 'Payment In' : 'Payment Out',
            'party_type' => $payment->party_type,
            'party_name' => $payment->party_name,
            'party_id' => $payment->party_id,
            'payment_mode_id' => $payment->payment_mode_id,
            'payment_mode' => $payment->payment_mode_label,
            'payment_mode_data' => $payment->paymentModeOption?->data,
            'amount' => (float) $payment->amount,
            'reference_no' => $payment->reference_no,
            'notes' => $payment->notes,
            'linked_bills' => $payment->allocations->count(),
            'deleted_at' => $payment->deleted_at?->toISOString(),
            'print_url' => route('payments.print', $payment),
            'pdf_url' => route('payments.pdf', $payment),
        ];

        if ($includeAllocations) {
            $payload['allocations'] = $payment->allocations->map(function (PaymentBillAllocation $allocation) use ($payment) {
                $bill = $this->resolveBill($allocation->bill_type, $allocation->bill_id, (int) $payment->party_id, (string) $payment->party_type);

                return [
                    'bill_id' => $allocation->bill_id,
                    'bill_type' => $allocation->bill_type,
                    'bill_number' => $bill->invoice_no ?? $bill->purchase_no ?? '#'.$allocation->bill_id,
                    'bill_date' => ($bill->invoice_date ?? $bill->purchase_date)?->format('M j, Y'),
                    'due_date' => ($bill->due_date ?? null)?->toDateString(),
                    'net_amount' => (float) $bill->grand_total,
                    'total_paid' => (float) $bill->paid_amount,
                    'outstanding' => $this->money($this->cents($this->billOutstanding($bill)) + $this->cents($allocation->allocated_amount)),
                    'allocated_amount' => (float) $allocation->allocated_amount,
                ];
            })->values();
        }

        return $payload;
    }

    public function printPayload(Payment $payment, User $user): array
    {
        return [
            'payment' => $this->payloadForUser($payment, $user, true),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ];
    }

    private function allocateBills(Payment $payment, Collection $allocations, User $user): int
    {
        $allocatedCents = 0;

        foreach ($allocations->filter(fn (array $row) => (float) ($row['allocated_amount'] ?? 0) > 0) as $allocation) {
            $bill = $this->resolveBill($allocation['bill_type'], (int) $allocation['bill_id'], (int) $payment->party_id, (string) $payment->party_type, $user);
            $allocationCents = $this->cents($allocation['allocated_amount']);
            $outstandingCents = $this->cents($this->billOutstanding($bill));

            if ($allocationCents > $outstandingCents) {
                throw ValidationException::withMessages([
                    'allocations' => 'Allocated amount cannot exceed outstanding balance.',
                ]);
            }

            $this->payments->createAllocation([
                'payment_id' => $payment->id,
                'bill_id' => $bill->id,
                'bill_type' => $allocation['bill_type'],
                'allocated_amount' => $this->money($allocationCents),
            ]);

            $bill->forceFill([
                'paid_amount' => $this->money($this->cents($bill->paid_amount) + $allocationCents),
            ]);
            $bill->payment_status = $this->billPaymentStatus($bill);
            $bill->save();
            $this->adjustPartyBalanceForAllocation($payment, $bill, $allocationCents);

            $allocatedCents += $allocationCents;
        }

        return $allocatedCents;
    }

    private function reverse(Payment $payment, User $user): void
    {
        foreach ($payment->allocations as $allocation) {
            $bill = $this->resolveBill($allocation->bill_type, $allocation->bill_id, (int) $payment->party_id, (string) $payment->party_type, $user);
            $amountCents = $this->cents($allocation->allocated_amount);

            $bill->forceFill([
                'paid_amount' => $this->money(max(0, $this->cents($bill->paid_amount) - $amountCents)),
            ]);
            $bill->payment_status = $this->billPaymentStatus($bill);
            $bill->save();
            $this->adjustPartyBalanceForAllocation($payment, $bill, $amountCents, reverse: true);
        }
    }

    private function adjustPartyBalanceForAllocation(Payment $payment, Model $bill, int $amountCents, bool $reverse = false): void
    {
        $delta = $this->money($reverse ? $amountCents : -$amountCents);

        if ($payment->party_type === 'customer' && $bill instanceof SalesInvoice && $bill->customer_id) {
            $this->receivables->adjustCustomerBalance((int) $bill->customer_id, $delta);
        }

        if ($payment->party_type === 'supplier' && $bill instanceof Purchase && $bill->supplier_id) {
            $this->payables->adjustSupplierBalance((int) $bill->supplier_id, $delta);
        }
    }

    private function resolveBill(string $billType, int $billId, int $partyId, string $partyType, ?User $user = null): Model
    {
        return $this->payments->resolveBill($billType, $billId, $partyId, $partyType, $user);
    }

    private function assertParty(string $partyType, int $partyId, User $user): void
    {
        if (! $this->payments->partyExists($partyType, $partyId, $user)) {
            throw ValidationException::withMessages(['party_id' => 'Selected party does not exist.']);
        }
    }

    private function billOutstanding(Model $bill): float
    {
        return $this->money(max(0, $this->cents($bill->grand_total) - $this->cents($bill->paid_amount)));
    }

    private function billPaymentStatus(Model $bill): string
    {
        $paid = $this->cents($bill->paid_amount);
        $total = $this->cents($bill->grand_total);

        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'partial';
    }

    private function journalEntries(Payment $payment, DropdownOption $paymentMode): array
    {
        $cashAccount = strtolower((string) ($paymentMode->data ?: $payment->payment_mode)) === 'cash' ? 'cash' : 'bank';

        if ($payment->direction === 'in') {
            return [
                ['account_type' => $cashAccount, 'debit' => (float) $payment->amount, 'credit' => 0, 'notes' => 'Payment received '.$payment->payment_no],
                ['account_type' => 'receivable', 'party_type' => 'customer', 'party_id' => $payment->party_id, 'debit' => 0, 'credit' => (float) $payment->amount, 'notes' => 'Receivable settled '.$payment->payment_no],
            ];
        }

        return [
            ['account_type' => 'payable', 'party_type' => 'supplier', 'party_id' => $payment->party_id, 'debit' => (float) $payment->amount, 'credit' => 0, 'notes' => 'Payable settled '.$payment->payment_no],
            ['account_type' => $cashAccount, 'debit' => 0, 'credit' => (float) $payment->amount, 'notes' => 'Payment made '.$payment->payment_no],
        ];
    }

    private function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function money(int $cents): float
    {
        return $cents / 100;
    }
}
