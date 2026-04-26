<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesInvoiceItem;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Models\SalesReturnItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesReturnController
{
    // Return paginated sales returns.
    public function index(Request $request): JsonResponse
    {
        $query = SalesReturn::query()
            ->with(['invoice', 'customer', 'items.product'])
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

        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (SalesReturn $return) => [
                'id' => $return->id,
                'return_no' => $return->return_no,
                'return_date' => $return->return_date->format('Y-m-d'),
                'return_date_display' => $return->return_date->format('M j, Y'),
                'invoice_no' => $return->invoice?->invoice_no ?? '-',
                'customer_name' => $return->customer?->name ?? '-',
                'total_amount' => round((float) $return->total_amount, 2),
                'reason' => $return->reason,
                'status' => $return->status,
                'items_count' => $return->items->count(),
            ]),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    // Create a sales return: reverse stock and post accounting entries.
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sales_invoice_id' => ['required', 'integer', 'exists:sales_invoices,id'],
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

        $return = DB::transaction(function () use ($validated, $request) {
            $invoice = SalesInvoice::query()->findOrFail($validated['sales_invoice_id']);
            $nextNo = 'SR-' . str_pad((string) (SalesReturn::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT);

            $salesReturn = SalesReturn::query()->create([
                'tenant_id' => $request->user()->tenant_id ?? null,
                'company_id' => $request->user()->company_id ?? null,
                'sales_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'return_no' => $nextNo,
                'return_date' => $validated['return_date'],
                'total_amount' => 0,
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $totalAmount = 0;

            foreach ($validated['items'] as $item) {
                $lineTotal = round((float) $item['quantity'] * (float) $item['unit_price'], 2);

                SalesReturnItem::query()->create([
                    'sales_return_id' => $salesReturn->id,
                    'sales_invoice_item_id' => $item['sales_invoice_item_id'] ?? null,
                    'product_id' => $item['product_id'],
                    'batch_id' => $item['batch_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $lineTotal,
                ]);

                $totalAmount += $lineTotal;

                // Reverse stock: add quantity back to batch.
                if (! empty($item['batch_id'])) {
                    $batch = Batch::query()->find($item['batch_id']);
                    if ($batch) {
                        $batch->increment('quantity_available', (float) $item['quantity']);
                    }
                }
            }

            $salesReturn->update(['total_amount' => round($totalAmount, 2)]);

            // Post accounting entries: debit sales (reduce income), credit receivable.
            AccountTransaction::query()->create([
                'tenant_id' => $request->user()->tenant_id ?? null,
                'company_id' => $request->user()->company_id ?? null,
                'transaction_date' => $salesReturn->return_date,
                'source_type' => 'SalesReturn',
                'source_id' => $salesReturn->id,
                'account_type' => 'sales',
                'party_type' => 'customer',
                'party_id' => $invoice->customer_id,
                'debit' => $totalAmount,
                'credit' => 0,
                'notes' => 'Sales return ' . $salesReturn->return_no,
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
                'party_id' => $invoice->customer_id,
                'debit' => 0,
                'credit' => $totalAmount,
                'notes' => 'Receivable adjusted for return ' . $salesReturn->return_no,
                'created_by' => $request->user()->id,
            ]);

            return $salesReturn;
        });

        return response()->json([
            'message' => 'Sales return created.',
            'data' => ['id' => $return->id, 'return_no' => $return->return_no],
        ]);
    }

    // Delete a sales return and reverse its effects.
    public function destroy(SalesReturn $salesReturn): JsonResponse
    {
        DB::transaction(function () use ($salesReturn) {
            // Restore stock to batches.
            foreach ($salesReturn->items as $item) {
                if ($item->batch_id) {
                    $batch = Batch::query()->find($item->batch_id);
                    if ($batch) {
                        $batch->decrement('quantity_available', (float) $item->quantity);
                    }
                }
            }

            AccountTransaction::query()
                ->where('source_type', 'SalesReturn')
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
}
