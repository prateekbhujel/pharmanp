<?php

namespace App\Modules\Sales\Services;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\ApiResponse;
use App\Models\User;
use App\Modules\Accounting\Services\AccountTransactionPostingService;
use App\Modules\Accounting\Services\ReceivableService;
use App\Modules\Inventory\Services\StockMovementService;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Models\SalesReturnItem;
use App\Modules\Sales\Repositories\Interfaces\SalesReturnRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    public function __construct(
        private readonly SalesReturnRepositoryInterface $returns,
        private readonly StockMovementService $stock,
        private readonly AccountTransactionPostingService $accounts,
        private readonly ReceivableService $receivables,
    ) {}

    public function table(TableQueryData $table, ?User $user = null): array
    {
        $page = $this->returns->paginate($table, $user);

        return [
            'data' => $page->getCollection()->map(fn (SalesReturn $return) => $this->summaryPayload($return))->values(),
            'meta' => ApiResponse::paginationMeta($page),
        ];
    }

    public function create(array $data, User $user): SalesReturn
    {
        return DB::transaction(function () use ($data, $user) {
            $invoice = $this->returns->invoice($data['sales_invoice_id'] ?? null);
            $customerId = $invoice?->customer_id ?? ($data['customer_id'] ?? null);

            if (! $customerId) {
                throw ValidationException::withMessages(['customer_id' => 'Customer is required for manual sales return.']);
            }

            $salesReturn = $this->returns->save(new SalesReturn, [
                'tenant_id' => $user->tenant_id,
                'company_id' => $user->company_id,
                'store_id' => $user->store_id,
                'sales_invoice_id' => $invoice?->id,
                'customer_id' => $customerId,
                'return_no' => $this->returns->nextReturnNo(),
                'return_type' => $data['return_type'] ?? 'regular',
                'return_date' => $data['return_date'],
                'total_amount' => 0,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $totalAmount = $this->postReturnItems($salesReturn, $data['items'], $user);
            $this->returns->save($salesReturn, ['total_amount' => round($totalAmount, 2)]);
            $this->reduceReceivable((int) $customerId, $totalAmount);
            $this->postAccounting($salesReturn, (int) $customerId, $totalAmount, $user);

            return $this->returns->fresh($salesReturn);
        });
    }

    public function update(SalesReturn $salesReturn, array $data, User $user): SalesReturn
    {
        return DB::transaction(function () use ($salesReturn, $data, $user) {
            $salesReturn->load('items');
            $oldCustomerId = (int) $salesReturn->customer_id;
            $oldAmount = (float) $salesReturn->total_amount;
            $this->reverseReturnEffects($salesReturn, $user);
            $this->restoreReceivable($oldCustomerId, $oldAmount);
            $this->returns->deleteItems($salesReturn);
            $this->accounts->replaceForSource($user, 'sales_return', $salesReturn->id, now()->toDateString(), []);

            $invoice = $this->returns->invoice($data['sales_invoice_id'] ?? null);
            $customerId = $invoice?->customer_id ?? ($data['customer_id'] ?? null);

            if (! $customerId) {
                throw ValidationException::withMessages(['customer_id' => 'Customer is required for manual sales return.']);
            }

            $this->returns->save($salesReturn, [
                'sales_invoice_id' => $invoice?->id,
                'customer_id' => $customerId,
                'return_type' => $data['return_type'] ?? 'regular',
                'return_date' => $data['return_date'],
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'updated_by' => $user->id,
            ]);

            $totalAmount = $this->postReturnItems($salesReturn, $data['items'], $user);
            $this->returns->save($salesReturn, ['total_amount' => round($totalAmount, 2)]);
            $this->reduceReceivable((int) $customerId, $totalAmount);
            $this->postAccounting($salesReturn, (int) $customerId, $totalAmount, $user);

            return $this->returns->fresh($salesReturn);
        });
    }

    public function delete(SalesReturn $salesReturn, User $user): void
    {
        DB::transaction(function () use ($salesReturn, $user): void {
            $salesReturn->load('items');
            $this->reverseReturnEffects($salesReturn, $user);
            $this->restoreReceivable((int) $salesReturn->customer_id, (float) $salesReturn->total_amount);
            $this->accounts->replaceForSource($user, 'sales_return', $salesReturn->id, now()->toDateString(), []);
            $this->returns->deleteItems($salesReturn);
            $this->returns->delete($salesReturn);
        });
    }

    public function invoiceOptions(array $filters, ?User $user = null): array
    {
        return [
            'data' => $this->returns->invoiceOptions($filters, $user)->map(fn (SalesInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date->format('M j, Y'),
                'customer_name' => $invoice->customer?->name ?? '-',
                'grand_total' => round((float) $invoice->grand_total, 2),
            ])->values(),
        ];
    }

    public function invoiceItems(SalesInvoice $invoice): array
    {
        $invoice->loadMissing('items.product');

        return [
            'data' => $invoice->items->map(fn (SalesInvoiceItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? '-',
                'batch_id' => $item->batch_id,
                'quantity' => round((float) $item->quantity, 3),
                'unit_price' => round((float) $item->unit_price, 2),
                'line_total' => round((float) $item->line_total, 2),
            ])->values(),
        ];
    }

    public function payload(SalesReturn $salesReturn): array
    {
        $salesReturn->loadMissing(['invoice', 'customer', 'items.product', 'items.batch']);

        return [
            'id' => $salesReturn->id,
            'sales_invoice_id' => $salesReturn->sales_invoice_id,
            'customer_id' => $salesReturn->customer_id,
            'return_no' => $salesReturn->return_no,
            'return_type' => $salesReturn->return_type,
            'return_date' => $salesReturn->return_date?->toDateString(),
            'total_amount' => round((float) $salesReturn->total_amount, 2),
            'reason' => $salesReturn->reason,
            'notes' => $salesReturn->notes,
            'status' => $salesReturn->status,
            'deleted_at' => $salesReturn->deleted_at?->toISOString(),
            'invoice' => $salesReturn->invoice ? [
                'id' => $salesReturn->invoice->id,
                'invoice_no' => $salesReturn->invoice->invoice_no,
            ] : null,
            'customer' => $salesReturn->customer ? [
                'id' => $salesReturn->customer->id,
                'name' => $salesReturn->customer->name,
            ] : null,
            'items' => $salesReturn->items->map(fn (SalesReturnItem $item) => [
                'id' => $item->id,
                'sales_invoice_item_id' => $item->sales_invoice_item_id,
                'product_id' => $item->product_id,
                'batch_id' => $item->batch_id,
                'quantity' => (float) $item->quantity,
                'return_qty' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'rate' => (float) $item->unit_price,
                'net_rate' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'product' => $item->product ? [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                ] : null,
                'batch' => $item->batch ? [
                    'id' => $item->batch->id,
                    'batch_no' => $item->batch->batch_no,
                    'quantity_available' => (float) $item->batch->quantity_available,
                    'expires_at' => $item->batch->expires_at?->toDateString(),
                ] : null,
            ])->values(),
        ];
    }

    private function summaryPayload(SalesReturn $return): array
    {
        return [
            'id' => $return->id,
            'sales_invoice_id' => $return->sales_invoice_id,
            'customer_id' => $return->customer_id,
            'return_no' => $return->return_no,
            'return_type' => $return->return_type,
            'return_date' => $return->return_date->format('Y-m-d'),
            'return_date_display' => $return->return_date->format('M j, Y'),
            'invoice_no' => $return->invoice?->invoice_no ?? '-',
            'customer_name' => $return->customer?->name ?? '-',
            'total_amount' => round((float) $return->total_amount, 2),
            'reason' => $return->reason,
            'status' => $return->status,
            'items_count' => $return->items->count(),
            'deleted_at' => $return->deleted_at?->toISOString(),
        ];
    }

    private function postReturnItems(SalesReturn $salesReturn, array $items, User $user): float
    {
        $totalAmount = 0.0;

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineTotal = round($quantity * $unitPrice, 2);

            $this->returns->createItem($salesReturn, [
                'sales_invoice_item_id' => $item['sales_invoice_item_id'] ?? null,
                'product_id' => $item['product_id'],
                'batch_id' => $item['batch_id'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);

            $totalAmount += $lineTotal;
            $this->recordStock($salesReturn, $item, $quantity, 'sales_return_in', 0, $quantity, 'Sales return '.$salesReturn->return_no, $user);
        }

        return round($totalAmount, 2);
    }

    private function reverseReturnEffects(SalesReturn $salesReturn, User $user): void
    {
        foreach ($salesReturn->items as $item) {
            $this->recordStock(
                $salesReturn,
                ['product_id' => $item->product_id, 'batch_id' => $item->batch_id],
                (float) $item->quantity,
                'sales_return_reverse',
                (float) $item->quantity,
                0,
                'Reverse sales return '.$salesReturn->return_no,
                $user,
            );
        }
    }

    private function recordStock(SalesReturn $salesReturn, array $item, float $quantity, string $type, float $out, float $in, string $notes, User $user): void
    {
        $this->stock->record([
            'tenant_id' => $salesReturn->tenant_id,
            'company_id' => $salesReturn->company_id,
            'store_id' => $salesReturn->store_id,
            'movement_date' => $salesReturn->return_date?->toDateString() ?: now()->toDateString(),
            'product_id' => $item['product_id'],
            'batch_id' => $item['batch_id'] ?? null,
            'movement_type' => $type,
            'quantity_in' => $in,
            'quantity_out' => $out,
            'source_type' => 'sales_return',
            'source_id' => $salesReturn->id,
            'reference_type' => 'sales_return',
            'reference_id' => $salesReturn->id,
            'notes' => $notes,
            'created_by' => $user->id,
        ]);
    }

    private function postAccounting(SalesReturn $salesReturn, int $customerId, float $totalAmount, User $user): void
    {
        $this->accounts->replaceForSource(
            $user,
            'sales_return',
            $salesReturn->id,
            $salesReturn->return_date?->toDateString() ?: now()->toDateString(),
            [
                [
                    'account_type' => 'sales',
                    'debit' => $totalAmount,
                    'credit' => 0,
                    'notes' => 'Sales return '.$salesReturn->return_no,
                ],
                [
                    'account_type' => 'receivable',
                    'party_type' => 'customer',
                    'party_id' => $customerId,
                    'debit' => 0,
                    'credit' => $totalAmount,
                    'notes' => 'Receivable adjusted for return '.$salesReturn->return_no,
                ],
            ],
        );
    }

    private function reduceReceivable(int $customerId, float $totalAmount): void
    {
        if ($customerId > 0 && $totalAmount > 0) {
            $this->receivables->adjustCustomerBalance($customerId, -1 * round($totalAmount, 2));
        }
    }

    private function restoreReceivable(int $customerId, float $totalAmount): void
    {
        if ($customerId > 0 && $totalAmount > 0) {
            $this->receivables->adjustCustomerBalance($customerId, round($totalAmount, 2));
        }
    }
}
