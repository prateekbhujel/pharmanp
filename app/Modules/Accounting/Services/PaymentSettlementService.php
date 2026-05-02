<?php

namespace App\Modules\Accounting\Services;

use App\Core\Services\DocumentNumberService;
use App\Models\User;
use App\Modules\Accounting\Contracts\AccountTransactionPostingServiceInterface;
use App\Modules\Accounting\Contracts\PayableServiceInterface;
use App\Modules\Accounting\Contracts\PaymentSettlementServiceInterface;
use App\Modules\Accounting\Contracts\ReceivableServiceInterface;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentBillAllocation;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentSettlementService implements PaymentSettlementServiceInterface
{
    public function __construct(
        private readonly DocumentNumberService $numbers,
        private readonly AccountTransactionPostingServiceInterface $accounts,
        private readonly PayableServiceInterface $payables,
        private readonly ReceivableServiceInterface $receivables,
    ) {}

    public function save(array $data, User $user): Payment
    {
        return DB::transaction(function () use ($data, $user) {
            $payment = ! empty($data['id'])
                ? Payment::query()->with('allocations')->lockForUpdate()->findOrFail($data['id'])
                : new Payment();

            if ($payment->exists) {
                $this->reverse($payment);
                PaymentBillAllocation::query()->where('payment_id', $payment->id)->delete();
                $this->accounts->replaceForSource($user, 'payment', $payment->id, now()->toDateString(), []);
            }

            $this->assertParty($data['party_type'], (int) $data['party_id'], $user);
            $paymentMode = DropdownOption::query()
                ->forAlias('payment_mode')
                ->active()
                ->findOrFail($data['payment_mode_id']);

            $payment->fill([
                'tenant_id' => $payment->tenant_id ?: $user->tenant_id,
                'company_id' => $payment->company_id ?: $user->company_id,
                'store_id' => $payment->store_id ?: $user->store_id,
                'payment_no' => $payment->payment_no ?: $this->numbers->next('payment', 'payments'),
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
            $allocatedCents = $this->allocateBills($payment, collect($data['allocations'] ?? []));
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
            $this->reverse($payment);
            PaymentBillAllocation::query()->where('payment_id', $payment->id)->delete();
            $this->accounts->replaceForSource($user, 'payment', $payment->id, now()->toDateString(), []);
            $payment->delete();
        });
    }

    public function outstandingBills(string $partyType, int $partyId, ?User $user = null): Collection
    {
        if ($partyType === 'customer') {
            return SalesInvoice::query()
                ->where('customer_id', $partyId)
                ->when($user?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->latest('invoice_date')
                ->get()
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

        return Purchase::query()
            ->where('supplier_id', $partyId)
            ->when($user?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->latest('purchase_date')
            ->get()
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

    private function allocateBills(Payment $payment, Collection $allocations): int
    {
        $allocatedCents = 0;

        foreach ($allocations->filter(fn (array $row) => (float) ($row['allocated_amount'] ?? 0) > 0) as $allocation) {
            $bill = $this->resolveBill($allocation['bill_type'], (int) $allocation['bill_id'], (int) $payment->party_id, (string) $payment->party_type);
            $allocationCents = $this->cents($allocation['allocated_amount']);
            $outstandingCents = $this->cents($this->billOutstanding($bill));

            if ($allocationCents > $outstandingCents) {
                throw ValidationException::withMessages([
                    'allocations' => 'Allocated amount cannot exceed outstanding balance.',
                ]);
            }

            PaymentBillAllocation::query()->create([
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

    private function reverse(Payment $payment): void
    {
        foreach ($payment->allocations as $allocation) {
            $bill = $this->resolveBill($allocation->bill_type, $allocation->bill_id, (int) $payment->party_id, (string) $payment->party_type);
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

    private function resolveBill(string $billType, int $billId, int $partyId, string $partyType): Model
    {
        if ($billType === 'sales_invoice' && $partyType === 'customer') {
            return SalesInvoice::query()->lockForUpdate()->where('customer_id', $partyId)->findOrFail($billId);
        }

        if ($billType === 'purchase' && $partyType === 'supplier') {
            return Purchase::query()->lockForUpdate()->where('supplier_id', $partyId)->findOrFail($billId);
        }

        throw ValidationException::withMessages([
            'allocations' => 'Selected bill does not belong to the chosen party.',
        ]);
    }

    private function assertParty(string $partyType, int $partyId, User $user): void
    {
        $query = $partyType === 'customer' ? Customer::query() : Supplier::query();
        $exists = $query
            ->when($user->tenant_id, fn ($builder, $tenantId) => $builder->where('tenant_id', $tenantId))
            ->whereKey($partyId)
            ->exists();

        if (! $exists) {
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
