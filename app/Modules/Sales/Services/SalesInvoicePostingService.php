<?php

namespace App\Modules\Sales\Services;

use App\Core\Utils\Math;
use App\Models\User;
use App\Modules\Accounting\Services\AccountTransactionPostingService;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Repositories\Interfaces\SalesInvoiceRepositoryInterface;
use Illuminate\Validation\ValidationException;

class SalesInvoicePostingService
{
    public function __construct(
        private readonly StockMovementService $stock,
        private readonly AccountTransactionPostingService $accounts,
        private readonly SalesInvoiceRepositoryInterface $invoices,
        private readonly SalesInvoiceJournalFactory $journals,
    ) {}

    /**
     * Post items, record stock movements, update customer balance, and post journal entries.
     */
    public function post(SalesInvoice $invoice, array $items, string $paidAmount, User $user): void
    {
        foreach ($items as $item) {
            $this->postItem($invoice, $item, $user);
        }

        if (! empty($invoice->customer_id)) {
            $dueAmount = Math::sub((string) $invoice->grand_total, $paidAmount);
            if (Math::sub($dueAmount, '0') > 0) {
                $this->invoices->incrementCustomerBalance((int) $invoice->customer_id, (float) $dueAmount, $user, $invoice);
            }
        }

        $cashAccount = $this->cashAccountForPaymentMode($invoice->payment_mode_id);

        $this->accounts->replaceForSource(
            $user,
            'sales_invoice',
            $invoice->id,
            $invoice->invoice_date->toDateString(),
            $this->journals->make($invoice, $paidAmount, $cashAccount),
        );
    }

    private function postItem(SalesInvoice $invoice, array $item, User $user): void
    {
        $product = $this->invoices->product((int) $item['product_id'], $user);
        $quantity = (string) $item['quantity'];
        $freeQuantity = (string) ($item['free_quantity'] ?? 0);
        $issueQuantity = Math::add($quantity, $freeQuantity);

        $batch = $this->invoices->availableBatch($product->id, (float) $issueQuantity, $item['batch_id'] ?? null, $user, $invoice);

        if (! $batch) {
            throw ValidationException::withMessages([
                'items' => ["No batch has enough available stock for product [{$product->name}]."],
            ]);
        }

        $unitPrice = (string) $item['unit_price'];
        $mrp = (string) ($item['mrp'] ?? $batch->mrp ?: $product->mrp);
        $ccRate = (string) ($item['cc_rate'] ?? $product->cc_rate ?? 0);
        $discountPercent = (string) ($item['discount_percent'] ?? 0);

        $gross = Math::mul($quantity, $unitPrice);
        $discount = Math::round(Math::div(Math::mul($gross, $discountPercent), '100'), 2);
        $freeGoodsValue = Math::round(Math::mul($freeQuantity, Math::div(Math::mul($mrp, $ccRate), '100')), 2);
        $lineTotal = Math::sub($gross, $discount);

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
            'quantity_out' => (float) $issueQuantity,
            'source_type' => 'sales_invoice',
            'source_id' => $invoice->id,
            'reference_type' => 'batch',
            'reference_id' => $batch->id,
            'notes' => 'Invoice '.$invoice->invoice_no,
            'created_by' => $user->id,
        ]);
    }

    private function cashAccountForPaymentMode(?int $paymentModeId): string
    {
        if (! $paymentModeId) {
            return 'cash';
        }
        $mode = $this->invoices->paymentMode($paymentModeId);

        return strtolower((string) ($mode?->data ?: $mode?->name)) === 'cash' ? 'cash' : 'bank';
    }
}
