<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Inventory\Contracts\StockMovementServiceInterface;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Models\SalesReturnItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesReturnController
{
    // Return paginated sales returns.
    public function index(Request $request): JsonResponse
    {
        $query = SalesReturn::query()
            ->with(['invoice', 'customer', 'items.product'])
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('tenant_id', $tenantId))
            ->when($request->boolean('deleted'), fn ($builder) => $builder->onlyTrashed())
            ->latest('return_date')
            ->latest('id');

        if ($request->filled('search')) {
            $keyword = $request->input('search');
            $query->where(function ($builder) use ($keyword) {
                $builder->where('return_no', 'like', '%' . $keyword . '%')
                    ->orWhere('reason', 'like', '%' . $keyword . '%')
                    ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', '%' . $keyword . '%'))
                    ->orWhereHas('invoice', fn ($q) => $q->where('invoice_no', 'like', '%' . $keyword . '%'));
            });
        }

        if ($request->filled('from')) {
            $query->where('return_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('return_date', '<=', $request->input('to'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (SalesReturn $return) => [
                'id' => $return->id,
                'sales_invoice_id' => $return->sales_invoice_id,
                'customer_id' => $return->customer_id,
                'return_no' => $return->return_no,
                'return_date' => $return->return_date->format('Y-m-d'),
                'return_date_display' => $return->return_date->format('M j, Y'),
                'invoice_no' => $return->invoice?->invoice_no ?? '-',
                'customer_name' => $return->customer?->name ?? '-',
                'total_amount' => round((float) $return->total_amount, 2),
                'reason' => $return->reason,
                'status' => $return->status,
                'items_count' => $return->items->count(),
                'deleted_at' => $return->deleted_at?->toISOString(),
            ]),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    public function show(SalesReturn $salesReturn): JsonResponse
    {
        return response()->json([
            'data' => $this->returnPayload($salesReturn->load(['invoice', 'customer', 'items.product', 'items.batch'])),
        ]);
    }

    // Create a sales return: reverse stock and post accounting entries.
    public function store(Request $request, StockMovementServiceInterface $stock): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $return = DB::transaction(function () use ($validated, $request, $stock) {
            $invoice = ! empty($validated['sales_invoice_id'])
                ? SalesInvoice::query()->findOrFail($validated['sales_invoice_id'])
                : null;
            $customerId = $invoice?->customer_id ?? ($validated['customer_id'] ?? null);

            if (! $customerId) {
                throw ValidationException::withMessages(['customer_id' => 'Customer is required for manual sales return.']);
            }

            $nextNo = 'SR-' . str_pad((string) (SalesReturn::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT);

            $salesReturn = SalesReturn::query()->create([
                'tenant_id' => $request->user()->tenant_id ?? null,
                'company_id' => $request->user()->company_id ?? null,
                'store_id' => $request->user()->store_id ?? null,
                'sales_invoice_id' => $invoice?->id,
                'customer_id' => $customerId,
                'return_no' => $nextNo,
                'return_date' => $validated['return_date'],
                'total_amount' => 0,
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $totalAmount = $this->postReturnItems($salesReturn, $validated['items'], $stock, $request);
            $salesReturn->update(['total_amount' => round($totalAmount, 2)]);
            $this->postAccounting($salesReturn, $customerId, $totalAmount, $request);

            return $salesReturn;
        });

        return response()->json([
            'message' => 'Sales return created.',
            'data' => ['id' => $return->id, 'return_no' => $return->return_no],
        ]);
    }

    public function update(Request $request, SalesReturn $salesReturn, StockMovementServiceInterface $stock): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $return = DB::transaction(function () use ($validated, $request, $salesReturn, $stock) {
            $salesReturn->load('items');
            $this->reverseReturnEffects($salesReturn, $stock, $request);

            SalesReturnItem::query()->where('sales_return_id', $salesReturn->id)->delete();
            AccountTransaction::query()
                ->whereIn('source_type', ['SalesReturn', 'sales_return'])
                ->where('source_id', $salesReturn->id)
                ->delete();

            $invoice = ! empty($validated['sales_invoice_id'])
                ? SalesInvoice::query()->findOrFail($validated['sales_invoice_id'])
                : null;
            $customerId = $invoice?->customer_id ?? ($validated['customer_id'] ?? null);

            if (! $customerId) {
                throw ValidationException::withMessages(['customer_id' => 'Customer is required for manual sales return.']);
            }

            $salesReturn->update([
                'sales_invoice_id' => $invoice?->id,
                'customer_id' => $customerId,
                'return_date' => $validated['return_date'],
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'updated_by' => $request->user()->id,
            ]);

            $totalAmount = $this->postReturnItems($salesReturn, $validated['items'], $stock, $request);
            $salesReturn->update(['total_amount' => round($totalAmount, 2)]);
            $this->postAccounting($salesReturn, $customerId, $totalAmount, $request);

            return $salesReturn->fresh(['invoice', 'customer', 'items.product', 'items.batch']);
        });

        return response()->json([
            'message' => 'Sales return updated.',
            'data' => $this->returnPayload($return),
        ]);
    }

    // Delete a sales return and reverse its effects.
    public function destroy(Request $request, SalesReturn $salesReturn, StockMovementServiceInterface $stock): JsonResponse
    {
        DB::transaction(function () use ($salesReturn, $stock, $request) {
            $salesReturn->load('items');
            $this->reverseReturnEffects($salesReturn, $stock, $request);

            AccountTransaction::query()
                ->whereIn('source_type', ['SalesReturn', 'sales_return'])
                ->where('source_id', $salesReturn->id)
                ->delete();

            $salesReturn->items()->delete();
            $salesReturn->delete();
        });

        return response()->json([
            'message' => 'Sales return deleted.',
        ]);
    }

    // Return invoices available for return selection.
    public function invoiceOptions(Request $request): JsonResponse
    {
        $query = SalesInvoice::query()
            ->with('customer')
            ->where('status', 'confirmed')
            ->latest('invoice_date');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('q')) {
            $keyword = $request->input('q');
            $query->where(function ($builder) use ($keyword) {
                $builder->where('invoice_no', 'like', '%' . $keyword . '%');
            });
        }

        return response()->json([
            'data' => $query->limit(50)->get()->map(fn (SalesInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date->format('M j, Y'),
                'customer_name' => $invoice->customer?->name ?? '-',
                'grand_total' => round((float) $invoice->grand_total, 2),
            ]),
        ]);
    }

    // Return items from a specific invoice for return form.
    public function invoiceItems(SalesInvoice $invoice): JsonResponse
    {
        return response()->json([
            'data' => $invoice->items->map(fn (SalesInvoiceItem $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? '-',
                'batch_id' => $item->batch_id,
                'quantity' => round((float) $item->quantity, 3),
                'unit_price' => round((float) $item->unit_price, 2),
                'line_total' => round((float) $item->line_total, 2),
            ]),
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'sales_invoice_id' => ['nullable', 'integer', 'exists:sales_invoices,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'return_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_invoice_item_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.batch_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function postReturnItems(SalesReturn $salesReturn, array $items, StockMovementServiceInterface $stock, Request $request): float
    {
        $totalAmount = 0.0;

        foreach ($items as $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineTotal = round($quantity * $unitPrice, 2);

            SalesReturnItem::query()->create([
                'sales_return_id' => $salesReturn->id,
                'sales_invoice_item_id' => $item['sales_invoice_item_id'] ?? null,
                'product_id' => $item['product_id'],
                'batch_id' => $item['batch_id'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ]);

            $totalAmount += $lineTotal;

            $stock->record([
                'tenant_id' => $salesReturn->tenant_id,
                'company_id' => $salesReturn->company_id,
                'store_id' => $salesReturn->store_id,
                'movement_date' => $salesReturn->return_date?->toDateString(),
                'product_id' => $item['product_id'],
                'batch_id' => $item['batch_id'] ?? null,
                'movement_type' => 'sales_return_in',
                'quantity_in' => $quantity,
                'quantity_out' => 0,
                'source_type' => 'sales_return',
                'source_id' => $salesReturn->id,
                'reference_type' => 'sales_return',
                'reference_id' => $salesReturn->id,
                'notes' => 'Sales return '.$salesReturn->return_no,
                'created_by' => $request->user()->id,
            ]);
        }

        return round($totalAmount, 2);
    }

    private function reverseReturnEffects(SalesReturn $salesReturn, StockMovementServiceInterface $stock, Request $request): void
    {
        foreach ($salesReturn->items as $item) {
            $stock->record([
                'tenant_id' => $salesReturn->tenant_id,
                'company_id' => $salesReturn->company_id,
                'store_id' => $salesReturn->store_id,
                'movement_date' => now()->toDateString(),
                'product_id' => $item->product_id,
                'batch_id' => $item->batch_id,
                'movement_type' => 'sales_return_reverse',
                'quantity_in' => 0,
                'quantity_out' => (float) $item->quantity,
                'source_type' => 'sales_return',
                'source_id' => $salesReturn->id,
                'reference_type' => 'sales_return',
                'reference_id' => $salesReturn->id,
                'notes' => 'Reverse sales return '.$salesReturn->return_no,
                'created_by' => $request->user()->id,
            ]);
        }
    }

    private function postAccounting(SalesReturn $salesReturn, int $customerId, float $totalAmount, Request $request): void
    {
        AccountTransaction::query()->create([
            'tenant_id' => $request->user()->tenant_id ?? null,
            'company_id' => $request->user()->company_id ?? null,
            'transaction_date' => $salesReturn->return_date,
            'source_type' => 'SalesReturn',
            'source_id' => $salesReturn->id,
            'account_type' => 'sales',
            'party_type' => 'customer',
            'party_id' => $customerId,
            'debit' => $totalAmount,
            'credit' => 0,
            'notes' => 'Sales return '.$salesReturn->return_no,
            'created_by' => $request->user()->id,
        ]);

        AccountTransaction::query()->create([
            'tenant_id' => $request->user()->tenant_id ?? null,
            'company_id' => $request->user()->company_id ?? null,
            'transaction_date' => $salesReturn->return_date,
            'source_type' => 'SalesReturn',
            'source_id' => $salesReturn->id,
            'account_type' => 'receivable',
            'party_type' => 'customer',
            'party_id' => $customerId,
            'debit' => 0,
            'credit' => $totalAmount,
            'notes' => 'Receivable adjusted for return '.$salesReturn->return_no,
            'created_by' => $request->user()->id,
        ]);
    }

    private function returnPayload(SalesReturn $salesReturn): array
    {
        return [
            'id' => $salesReturn->id,
            'sales_invoice_id' => $salesReturn->sales_invoice_id,
            'customer_id' => $salesReturn->customer_id,
            'return_no' => $salesReturn->return_no,
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
}
