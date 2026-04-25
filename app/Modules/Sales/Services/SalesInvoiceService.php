<?php

namespace App\Modules\Sales\Services;

use App\Models\User;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Party\Models\Customer;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
{
    public function __construct(
        private readonly StockMovementService $stock,
    ) {}

    public function create(array $data, User $user): SalesInvoice
    {
        return DB::transaction(function () use ($data, $user) {
            [$subtotal, $discountTotal, $grandTotal] = $this->totals($data['items']);
            $paidAmount = (float) ($data['paid_amount'] ?? 0);

            if ($paidAmount > $grandTotal) {
                throw ValidationException::withMessages(['paid_amount' => 'Paid amount cannot be greater than invoice total.']);
            }

            $invoice = SalesInvoice::query()->create([
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'store_id' => $user->store_id,
                'customer_id' => $data['customer_id'] ?? null,
                'medical_representative_id' => $data['medical_representative_id'] ?? null,
                'invoice_no' => $this->nextNumber(),
                'invoice_date' => $data['invoice_date'],
                'sale_type' => $data['sale_type'],
                'status' => 'confirmed',
                'payment_status' => $this->paymentStatus($grandTotal, $paidAmount),
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
                Customer::query()
                    ->whereKey($data['customer_id'])
                    ->increment('current_balance', round($grandTotal - $paidAmount, 2));
            }

            return $invoice->fresh(['customer', 'items.product', 'items.batch']);
        });
    }

    private function postItem(SalesInvoice $invoice, array $item, User $user): void
    {
        $product = Product::query()->findOrFail($item['product_id']);
        $quantity = (float) $item['quantity'];
        $batch = $this->resolveBatch($product->id, $quantity, $item['batch_id'] ?? null);
        $unitPrice = (float) $item['unit_price'];
        $discountPercent = (float) ($item['discount_percent'] ?? 0);
        $gross = $quantity * $unitPrice;
        $discount = round($gross * $discountPercent / 100, 2);
        $lineTotal = round($gross - $discount, 2);

        $invoice->items()->create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'quantity' => $quantity,
            'mrp' => $batch->mrp ?: $product->mrp,
            'unit_price' => $unitPrice,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discount,
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
            'quantity_out' => $quantity,
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
        $query = Batch::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('quantity_available', '>=', $quantity)
            ->whereNull('deleted_at')
            ->orderByRaw('expires_at IS NULL')
            ->orderBy('expires_at')
            ->orderBy('id');

        if ($batchId) {
            $query->whereKey($batchId);
        }

        $batch = $query->lockForUpdate()->first();

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
        $nextId = ((int) DB::table('sales_invoices')->lockForUpdate()->max('id')) + 1;

        return 'SI-'.now()->format('Ymd').'-'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }
}
