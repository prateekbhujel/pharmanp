<?php

namespace App\Modules\Sales\Services;

use App\Core\Services\DocumentNumberService;
use App\Models\User;
use App\Modules\Accounting\Services\AccountTransactionPostingService;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Sales\DTOs\SalesInvoiceData;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Repositories\Interfaces\SalesInvoiceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
{
    public function __construct(
        private readonly StockMovementService $stock,
        private readonly AccountTransactionPostingService $accounts,
        private readonly DocumentNumberService $numbers,
        private readonly SalesInvoiceRepositoryInterface $invoices,
    ) {}

    public function create(array $data, User $user): SalesInvoice
    {
        $dto = SalesInvoiceData::fromArray($data);

        return DB::transaction(function () use ($dto, $user) {
            $data = $dto->toArray();
            [$subtotal, $discountTotal, $grandTotal] = $this->totals($data['items']);
            $paidAmount = (float) ($data['paid_amount'] ?? 0);

            if ($paidAmount > $grandTotal) {
                throw ValidationException::withMessages(['paid_amount' => 'Paid amount cannot be greater than invoice total.']);
            }

            $invoice = $this->invoices->createInvoice([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'store_id' => $user->store_id,
                'branch_id' => $user->branch_id,
                'customer_id' => $data['customer_id'] ?? null,
                'medical_representative_id' => $data['medical_representative_id'] ?? null,
                'invoice_no' => $this->nextNumber(),
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'sale_type' => $data['sale_type'],
                'status' => 'confirmed',
                'payment_status' => $this->paymentStatus($grandTotal, $paidAmount),
                'payment_mode_id' => $data['payment_mode_id'] ?? null,
                'payment_type' => $data['payment_type'] ?? null,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            foreach ($data['items'] as $item) {
                $this->postItem($invoice, $item, $user);
            }

            if (! empty($data['customer_id'])) {
                $this->invoices->incrementCustomerBalance((int) $data['customer_id'], round($grandTotal - $paidAmount, 2));
            }

            $this->accounts->replaceForSource(
                $user,
                'sales_invoice',
                $invoice->id,
                $invoice->invoice_date->toDateString(),
                $this->journalEntries($invoice, $paidAmount),
            );

            return $this->invoices->fresh($invoice);
        });
    }

    public function updatePayment(SalesInvoice $invoice, array $data, User $user): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $data, $user) {
            $invoice = $this->invoices->invoiceForUpdate((int) $invoice->id);
            $paidAmount = round((float) $data['paid_amount'], 2);

            if ($paidAmount > (float) $invoice->grand_total) {
                throw ValidationException::withMessages(['paid_amount' => 'Paid amount cannot be greater than invoice total.']);
            }

            $oldDue = round((float) $invoice->grand_total - (float) $invoice->paid_amount, 2);
            $newDue = round((float) $invoice->grand_total - $paidAmount, 2);

            $this->invoices->saveInvoice($invoice, [
                'paid_amount' => $paidAmount,
                'payment_status' => $this->paymentStatus((float) $invoice->grand_total, $paidAmount),
                'updated_by' => $user->id,
            ]);

            if ($invoice->customer_id) {
                $this->invoices->incrementCustomerBalance((int) $invoice->customer_id, round($newDue - $oldDue, 2));
            }

            $cashAccount = ($data['cash_account'] ?? 'cash') === 'bank' ? 'bank' : 'cash';
            $this->accounts->replaceForSource(
                $user,
                'sales_invoice',
                $invoice->id,
                $invoice->invoice_date->toDateString(),
                $this->journalEntries($invoice, $paidAmount, $cashAccount),
            );

            return $this->invoices->fresh($invoice, includeReturns: true);
        });
    }

    private function postItem(SalesInvoice $invoice, array $item, User $user): void
    {
        $product = $this->invoices->product((int) $item['product_id']);
        $quantity = (float) $item['quantity'];
        $freeQuantity = (float) ($item['free_quantity'] ?? 0);
        $issueQuantity = $quantity + $freeQuantity;
        $batch = $this->resolveBatch($product->id, $issueQuantity, $item['batch_id'] ?? null);
        $unitPrice = (float) $item['unit_price'];
        $mrp = (float) ($item['mrp'] ?? $batch->mrp ?: $product->mrp);
        $ccRate = (float) ($item['cc_rate'] ?? $product->cc_rate ?? 0);
        $discountPercent = (float) ($item['discount_percent'] ?? 0);
        $gross = $quantity * $unitPrice;
        $discount = round($gross * $discountPercent / 100, 2);
        $freeGoodsValue = round($freeQuantity * ($mrp * $ccRate / 100), 2);
        $lineTotal = round($gross - $discount, 2);

        $this->invoices->createItem($invoice, [
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity' => $quantity,
            'free_quantity' => $freeQuantity,
            'mrp' => $mrp,
            'unit_price' => $unitPrice,
            'cc_rate' => $ccRate,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discount,
            'free_goods_value' => $freeGoodsValue,
            'line_total' => $lineTotal,
        ]);

        $this->stock->record([
            'tenant_id' => $invoice->tenant_id,
            'company_id' => $invoice->company_id,
            'store_id' => $invoice->store_id,
            'movement_date' => $invoice->invoice_date->toDateString(),
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'movement_type' => 'sales_issue',
            'quantity_in' => 0,
            'quantity_out' => $issueQuantity,
            'source_type' => 'sales_invoice',
            'source_id' => $invoice->id,
            'reference_type' => 'batch',
            'reference_id' => $batch->id,
            'notes' => 'Invoice '.$invoice->invoice_no,
            'created_by' => $user->id,
        ]);
    }

    private function resolveBatch(int $productId, float $quantity, ?int $batchId): Batch
    {
        $batch = $this->invoices->availableBatch($productId, $quantity, $batchId);

        if (! $batch) {
            throw ValidationException::withMessages(['items' => 'No batch has enough available stock for one of the selected products.']);
        }

        return $batch;
    }

    private function totals(array $items): array
    {
        $subtotal = 0;
        $discountTotal = 0;

        foreach ($items as $item) {
            $gross = (float) $item['quantity'] * (float) $item['unit_price'];
            $discount = round($gross * (float) ($item['discount_percent'] ?? 0) / 100, 2);
            $subtotal += $gross;
            $discountTotal += $discount;
        }

        return [round($subtotal, 2), round($discountTotal, 2), round($subtotal - $discountTotal, 2)];
    }

    private function paymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid >= $total ? 'paid' : 'partial';
    }

    private function nextNumber(): string
    {
        return $this->numbers->next('sales_invoice', 'sales_invoices');
    }

    private function journalEntries(SalesInvoice $invoice, float $paidAmount, string $cashAccount = 'cash'): array
    {
        $entries = [];

        if ($paidAmount > 0) {
            $entries[] = [
                'account_type' => $cashAccount,
                'debit' => $paidAmount,
                'credit' => 0,
                'notes' => 'Collected on '.$invoice->invoice_no,
            ];
        }

        $outstanding = round((float) $invoice->grand_total - $paidAmount, 2);

        if ($outstanding > 0) {
            $entries[] = [
                'account_type' => 'receivable',
                'party_type' => $invoice->customer_id ? 'customer' : null,
                'party_id' => $invoice->customer_id,
                'debit' => $outstanding,
                'credit' => 0,
                'notes' => 'Outstanding on '.$invoice->invoice_no,
            ];
        }

        $entries[] = [
            'account_type' => 'sales',
            'debit' => 0,
            'credit' => (float) $invoice->grand_total,
            'notes' => 'Sales '.$invoice->invoice_no,
        ];

        return $entries;
    }
}
