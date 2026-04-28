<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentBillAllocation;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController
{
    // Return paginated payments with filters.
    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()
            ->with(['customer', 'supplier', 'allocations', 'paymentModeOption:id,name,data'])
            ->when($request->boolean('deleted'), fn ($builder) => $builder->onlyTrashed())
            ->latest('payment_date')
            ->latest('id');

        if ($request->filled('search')) {
            $keyword = $request->input('search');
            $query->where(function ($builder) use ($keyword) {
                $builder->where('payment_no', 'like', '%' . $keyword . '%')
                    ->orWhere('reference_no', 'like', '%' . $keyword . '%')
                    ->orWhere('notes', 'like', '%' . $keyword . '%')
                    ->orWhere('payment_mode', 'like', '%' . $keyword . '%');
            });
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        }

        if ($request->filled('party_type')) {
            $query->where('party_type', $request->input('party_type'));
        }

        if ($request->filled('from')) {
            $query->where('payment_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('payment_date', '<=', $request->input('to'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'payment_no' => $payment->payment_no,
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'payment_date_display' => $payment->payment_date->format('M j, Y'),
                'direction' => $payment->direction,
                'direction_label' => $payment->direction === 'in' ? 'Payment In' : 'Payment Out',
                'party_type' => $payment->party_type,
                'party_name' => $payment->party_name,
                'party_id' => $payment->party_id,
                'payment_mode_id' => $payment->payment_mode_id,
                'payment_mode' => $payment->payment_mode_label,
                'payment_mode_data' => $payment->paymentModeOption?->data,
                'amount' => round((float) $payment->amount, 2),
                'reference_no' => $payment->reference_no,
                'notes' => $payment->notes,
                'linked_bills' => $payment->allocations->count(),
                'deleted_at' => $payment->deleted_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'lookups' => [
                'payment_modes' => DropdownOption::query()->forAlias('payment_mode')->active()->orderBy('name')->get(['id', 'name', 'data']),
            ],
        ]);
    }

    // Create or update a payment with bill allocations and accounting entries.
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:payments,id'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'party_type' => ['required', Rule::in(['customer', 'supplier'])],
            'party_id' => ['required', 'integer'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_mode_id' => ['required', 'integer', Rule::exists('dropdown_options', 'id')->where(fn ($query) => $query->where('alias', 'payment_mode'))],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.bill_id' => ['nullable', 'integer'],
            'allocations.*.bill_type' => ['nullable', Rule::in(['sales_invoice', 'purchase'])],
            'allocations.*.allocated_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        // Validate party exists.
        if ($validated['party_type'] === 'customer') {
            Customer::query()->findOrFail($validated['party_id']);
        } else {
            Supplier::query()->findOrFail($validated['party_id']);
        }

        $paymentMode = DropdownOption::query()
            ->forAlias('payment_mode')
            ->active()
            ->findOrFail($validated['payment_mode_id']);

        $payment = DB::transaction(function () use ($validated, $paymentMode, $request) {
            $existingPayment = ! empty($validated['id'])
                ? Payment::query()->with('allocations')->findOrFail($validated['id'])
                : null;

            if ($existingPayment) {
                $this->reversePaymentEffects($existingPayment);
                PaymentBillAllocation::query()->where('payment_id', $existingPayment->id)->delete();
                AccountTransaction::query()
                    ->where('source_type', 'Payment')
                    ->where('source_id', $existingPayment->id)
                    ->delete();

                $existingPayment->update([
                    'direction' => $validated['direction'],
                    'party_type' => $validated['party_type'],
                    'party_id' => $validated['party_id'],
                    'payment_date' => $validated['payment_date'],
                    'amount' => round((float) $validated['amount'], 2),
                    'payment_mode_id' => $paymentMode->id,
                    'payment_mode' => $paymentMode->data ?: strtolower($paymentMode->name),
                    'reference_no' => $validated['reference_no'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'updated_by' => $request->user()->id,
                ]);

                $payment = $existingPayment;
            } else {
                $nextNo = 'PAY-' . str_pad((string) (Payment::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT);

                $payment = Payment::query()->create([
                    'tenant_id' => $request->user()->tenant_id ?? null,
                    'company_id' => $request->user()->company_id ?? null,
                    'payment_no' => $nextNo,
                    'direction' => $validated['direction'],
                    'party_type' => $validated['party_type'],
                    'party_id' => $validated['party_id'],
                    'payment_date' => $validated['payment_date'],
                    'amount' => round((float) $validated['amount'], 2),
                    'payment_mode_id' => $paymentMode->id,
                    'payment_mode' => $paymentMode->data ?: strtolower($paymentMode->name),
                    'reference_no' => $validated['reference_no'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]);
            }

            // Process bill allocations — use bcmath to avoid float drift.
            $allocations = collect($validated['allocations'] ?? [])
                ->filter(fn (array $a) => (float) ($a['allocated_amount'] ?? 0) > 0)
                ->values();

            $allocatedTotal = '0.00';

            foreach ($allocations as $allocation) {
                // bcmath operates on strings; scale = 2 decimal places.
                $allocatedAmount = number_format((float) $allocation['allocated_amount'], 2, '.', '');
                $bill        = $this->resolveBill($allocation['bill_type'], (int) $allocation['bill_id'], (int) $validated['party_id'], $validated['party_type']);
                $outstanding = number_format($this->billOutstanding($bill, $allocation['bill_type']), 2, '.', '');

                if (bccomp($allocatedAmount, $outstanding, 2) > 0) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Allocated amount cannot exceed outstanding balance.',
                    ]);
                }

                PaymentBillAllocation::query()->create([
                    'payment_id'       => $payment->id,
                    'bill_id'          => $bill->id,
                    'bill_type'        => $allocation['bill_type'],
                    'allocated_amount' => $allocatedAmount,
                ]);

                $newPaid = bcadd(
                    number_format((float) $bill->paid_amount, 2, '.', ''),
                    $allocatedAmount,
                    2
                );
                $bill->paid_amount    = $newPaid;
                $bill->payment_status = $this->resolveBillPaymentStatus($bill, $allocation['bill_type']);
                $bill->save();

                $allocatedTotal = bcadd($allocatedTotal, $allocatedAmount, 2);
            }

            $paymentAmount = number_format((float) $payment->amount, 2, '.', '');
            if (bccomp($allocatedTotal, $paymentAmount, 2) > 0) {
                throw ValidationException::withMessages([
                    'allocations' => 'Allocated total cannot exceed payment amount.',
                ]);
            }

            // Post accounting entries.
            $modeType = strtolower((string) ($paymentMode->data ?: $payment->payment_mode));
            $cashAccount = $modeType === 'cash' ? 'cash' : 'bank';
            $userId = $request->user()->id;

            if ($payment->direction === 'in') {
                $this->postEntry($payment, 'debit', $cashAccount, 'Payment received.', $userId);
                $this->postEntry($payment, 'credit', 'receivable', 'Customer receipt adjusted.', $userId);
            } else {
                $this->postEntry($payment, 'debit', 'payable', 'Supplier payment adjusted.', $userId);
                $this->postEntry($payment, 'credit', $cashAccount, 'Money paid out.', $userId);
            }

            return $payment->fresh(['customer', 'supplier', 'allocations']);
        });

        return response()->json([
            'message' => 'Payment saved successfully.',
            'data' => ['id' => $payment->id, 'payment_no' => $payment->payment_no],
        ]);
    }

    // Return outstanding bills for a selected party.
    public function outstandingBills(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'party_id' => ['required', 'integer'],
            'party_type' => ['required', Rule::in(['customer', 'supplier'])],
        ]);

        if ($validated['party_type'] === 'customer') {
            $bills = SalesInvoice::query()
                ->where('customer_id', $validated['party_id'])
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->latest('invoice_date')
                ->get()
                ->map(fn (SalesInvoice $invoice) => [
                    'bill_id' => $invoice->id,
                    'bill_type' => 'sales_invoice',
                    'bill_number' => $invoice->invoice_no,
                    'bill_date' => $invoice->invoice_date->format('M j, Y'),
                    'net_amount' => round((float) $invoice->grand_total, 2),
                    'total_paid' => round((float) $invoice->paid_amount, 2),
                    'outstanding' => round(max(0, (float) $invoice->grand_total - (float) $invoice->paid_amount), 2),
                ])
                ->values();

            return response()->json(['data' => $bills]);
        }

        $bills = Purchase::query()
            ->where('supplier_id', $validated['party_id'])
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->latest('purchase_date')
            ->get()
            ->map(fn (Purchase $purchase) => [
                'bill_id' => $purchase->id,
                'bill_type' => 'purchase',
                'bill_number' => $purchase->purchase_no,
                'bill_date' => $purchase->purchase_date->format('M j, Y'),
                'net_amount' => round((float) $purchase->grand_total, 2),
                'total_paid' => round((float) $purchase->paid_amount, 2),
                'outstanding' => round(max(0, (float) $purchase->grand_total - (float) $purchase->paid_amount), 2),
            ])
            ->values();

        return response()->json(['data' => $bills]);
    }

    public function destroy(Payment $payment): JsonResponse
    {
        DB::transaction(function () use ($payment) {
            $payment->load('allocations');
            $this->reversePaymentEffects($payment);

            PaymentBillAllocation::query()->where('payment_id', $payment->id)->delete();
            AccountTransaction::query()
                ->where('source_type', 'Payment')
                ->where('source_id', $payment->id)
                ->delete();

            $payment->delete();
        });

        return response()->json(['message' => 'Payment deleted successfully.']);
    }

    private function postEntry(Payment $payment, string $side, string $accountType, string $notes, int $userId): void
    {
        AccountTransaction::query()->create([
            'tenant_id' => $payment->tenant_id,
            'company_id' => $payment->company_id,
            'transaction_date' => $payment->payment_date,
            'source_type' => 'Payment',
            'source_id' => $payment->id,
            'account_type' => $accountType,
            'party_type' => $payment->party_type,
            'party_id' => $payment->party_id,
            'debit' => $side === 'debit' ? $payment->amount : 0,
            'credit' => $side === 'credit' ? $payment->amount : 0,
            'notes' => $notes,
            'created_by' => $userId,
        ]);
    }

    private function reversePaymentEffects(Payment $payment): void
    {
        foreach ($payment->allocations as $allocation) {
            $bill = $this->resolveBill($allocation->bill_type, $allocation->bill_id, $payment->party_id, $payment->party_type);
            // bcmath subtraction — floor at 0.00 so we never go negative.
            $newPaid = bcsub(
                number_format((float) $bill->paid_amount, 2, '.', ''),
                number_format((float) $allocation->allocated_amount, 2, '.', ''),
                2
            );
            $bill->paid_amount    = number_format(max(0, (float) $newPaid), 2, '.', '');
            $bill->payment_status = $this->resolveBillPaymentStatus($bill, $allocation->bill_type);
            $bill->save();
        }
    }

    private function resolveBill(string $billType, int $billId, int $partyId, string $partyType)
    {
        if ($billType === 'sales_invoice' && $partyType === 'customer') {
            return SalesInvoice::query()->where('customer_id', $partyId)->findOrFail($billId);
        }

        if ($billType === 'purchase' && $partyType === 'supplier') {
            return Purchase::query()->where('supplier_id', $partyId)->findOrFail($billId);
        }

        throw ValidationException::withMessages([
            'allocations' => 'Selected bill does not belong to the chosen party.',
        ]);
    }

    private function billOutstanding($bill, string $billType): float
    {
        $total = (float) $bill->grand_total;

        return round(max(0, $total - (float) $bill->paid_amount), 2);
    }

    private function resolveBillPaymentStatus($bill, string $billType): string
    {
        $total = (float) $bill->grand_total;
        $paid = (float) $bill->paid_amount;

        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'partial';
    }
}
