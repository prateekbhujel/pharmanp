<?php

namespace App\Modules\Sales\Services;

use App\Core\DTOs\TableQueryData;
use App\Core\Security\TenantRecordScope;
use App\Core\Services\DocumentNumberService;
use App\Models\User;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Repositories\Interfaces\SalesInvoiceRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesInvoiceService
{
    public function __construct(
        private readonly SalesInvoiceRepositoryInterface $invoices,
        private readonly TenantRecordScope $records,
        private readonly DocumentNumberService $numbers,
        private readonly SalesInvoiceTotalsCalculator $calculator,
        private readonly SalesInvoicePostingService $poster,
        private readonly SalesInvoicePaymentService $payments,
        private readonly SalesInvoicePrintData $printData,
    ) {}

    public function table(TableQueryData $table, ?User $user = null)
    {
        return $this->invoices->paginate($table, $user);
    }

    public function create(array $data, User $user): SalesInvoice
    {
        return DB::transaction(function () use ($data, $user) {
            [$subtotal, $discountTotal, $grandTotal] = $this->calculator->calculate($data['items']);
            $paidAmount = (string) ($data['paid_amount'] ?? 0);

            $invoice = $this->invoices->createInvoice([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'store_id' => $user->store_id,
                'branch_id' => $user->branch_id,
                'customer_id' => $data['customer_id'] ?? null,
                'medical_representative_id' => $data['medical_representative_id'] ?? null,
                'invoice_no' => $this->numbers->next('sales_invoice', 'sales_invoices', Carbon::parse($data['invoice_date']), $user),
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'sale_type' => $data['sale_type'],
                'status' => 'confirmed',
                'payment_status' => $this->paymentStatus($grandTotal, $paidAmount),
                'payment_mode_id' => $data['payment_mode_id'] ?? null,
                'payment_type' => $data['payment_type'] ?? null,
                'subtotal' => (float) $subtotal,
                'discount_total' => (float) $discountTotal,
                'grand_total' => (float) $grandTotal,
                'paid_amount' => (float) $paidAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->poster->post($invoice, $data['items'], $paidAmount, $user);

            return $this->invoices->fresh($invoice);
        });
    }

    public function updatePayment(SalesInvoice $invoice, array $data, User $user): SalesInvoice
    {
        return $this->payments->update($invoice, $data, $user);
    }

    public function assertAccessible(SalesInvoice $invoice, User $user): void
    {
        abort_unless($this->records->canAccess($user, $invoice), 404);
    }

    public function printPayload(SalesInvoice $invoice): array
    {
        return $this->printData->get($invoice);
    }

    public function cashAccountForPaymentMode(?int $paymentModeId): string
    {
        if (! $paymentModeId) {
            return 'cash';
        }

        $mode = $this->invoices->paymentMode($paymentModeId);

        return strtolower((string) ($mode?->data ?: $mode?->name)) === 'cash' ? 'cash' : 'bank';
    }

    private function paymentStatus(string $total, string $paid): string
    {
        $paid = (float) $paid;
        $total = (float) $total;

        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'partial';
    }
}
