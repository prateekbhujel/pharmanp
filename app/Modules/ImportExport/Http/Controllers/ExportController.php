<?php

namespace App\Modules\ImportExport\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Models\User;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Support\AccountCatalog;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Sales\Models\SalesInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * @OA\Tag(
 *     name="IMPORT EXPORT - Imports and OCR",
 *     description="API endpoints for IMPORT EXPORT - Imports and OCR"
 * )
 */
class ExportController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/exports/inventory/masters/{master}/{format}",
     *     summary="Api Exports Inventory Masters",
     *     tags={"IMPORT EXPORT - Exports"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function inventoryMaster(Request $request, string $master, string $format)
    {
        $this->authorize('viewAny', Product::class);

        $rows = $this->masterRows($request, $master);
        $title = match ($master) {
            'companies' => 'Company List',
            'units' => 'Unit List',
            'categories' => 'Category List',
            default => abort(404),
        };

        return $this->download($format, $master.'.xlsx', $master.'.pdf', $title, $rows);
    }

    /**
     * @OA\Get(
     *     path="/exports/inventory/products/{format}",
     *     summary="Api Exports Inventory Products",
     *     tags={"IMPORT EXPORT - Exports"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function inventoryProducts(Request $request, string $format)
    {
        $this->authorize('viewAny', Product::class);

        $rows = Product::query()
            ->with(['company:id,name', 'unit:id,name', 'category:id,name'])
            ->withSum(['batches as stock_on_hand' => fn (Builder $query) => $query->where('is_active', true)], 'quantity_available')
            ->when($request->filled('company_id'), fn (Builder $query) => $query->where('company_id', $request->integer('company_id')))
            ->when(trim((string) $request->query('search')) !== '', function (Builder $query) use ($request) {
                $search = trim((string) $request->query('search'));
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('generic_name', 'like', '%'.$search.'%')
                        ->orWhere('product_code', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => [
                'Product' => $product->name,
                'Generic Name' => $product->generic_name ?: '-',
                'Company' => $product->company?->name ?: '-',
                'Formulation' => $product->formulation ?: '-',
                'Unit' => $product->unit?->name ?: '-',
                'Reorder Level' => (float) $product->reorder_level,
                'Current Stock' => (float) $product->stock_on_hand,
                'MRP' => (float) $product->mrp,
                'CC Rate' => number_format((float) $product->cc_rate, 2).'%',
                'Status' => $product->is_active ? 'Active' : 'Inactive',
            ]);

        return $this->download($format, 'inventory-products.xlsx', 'inventory-products.pdf', 'Inventory Product List', $rows);
    }

    /**
     * @OA\Get(
     *     path="/exports/inventory/batches/{format}",
     *     summary="Api Exports Inventory Batches",
     *     tags={"IMPORT EXPORT - Exports"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function inventoryBatches(Request $request, string $format)
    {
        $this->authorize('viewAny', Product::class);

        $rows = Batch::query()
            ->with(['product:id,name,generic_name', 'supplier:id,name'])
            ->whereNull('deleted_at')
            ->when($request->filled('product_id'), fn (Builder $query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('supplier_id'), fn (Builder $query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('expiry_status'), fn (Builder $query) => $this->applyExpiryFilter($query, (string) $request->query('expiry_status')))
            ->when(trim((string) $request->query('search')) !== '', function (Builder $query) use ($request) {
                $search = trim((string) $request->query('search'));
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('batch_no', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%')
                        ->orWhere('storage_location', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('expires_at')
            ->get()
            ->map(fn (Batch $batch) => [
                'Product' => $batch->product?->name ?: '-',
                'Batch No' => $batch->batch_no,
                'Supplier' => $batch->supplier?->name ?: '-',
                'Expiry Date' => $batch->expires_at?->toDateString() ?: '-',
                'Qty Available' => (float) $batch->quantity_available,
                'Purchase Price' => (float) $batch->purchase_price,
                'MRP' => (float) $batch->mrp,
                'Storage' => $batch->storage_location ?: '-',
                'Status' => $batch->is_active ? 'Active' : 'Inactive',
            ]);

        return $this->download($format, 'inventory-batches.xlsx', 'inventory-batches.pdf', 'Inventory Batch List', $rows);
    }

    /**
     * @OA\Get(
     *     path="/exports/{dataset}/{format}",
     *     summary="Api Exports Dataset",
     *     tags={"IMPORT EXPORT - Exports"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function dataset(Request $request, string $dataset, string $format)
    {
        [$title, $rows] = match ($dataset) {
            'suppliers' => ['Supplier List', $this->supplierRows($request)],
            'customers' => ['Customer List', $this->customerRows($request)],
            'sales-invoices' => ['Sales Invoice List', $this->salesInvoiceRows($request)],
            'purchases' => ['Purchase Bill List', $this->purchaseRows($request)],
            'purchase-orders' => ['Purchase Order List', $this->purchaseOrderRows($request)],
            'payments' => ['Payment List', $this->paymentRows($request)],
            'expenses' => ['Expense List', $this->expenseRows($request)],
            'users' => ['User List', $this->userRows($request)],
            'account-tree' => ['Account Tree', $this->accountTreeRows($request)],
            default => abort(404),
        };

        return $this->download($format, $dataset.'.xlsx', $dataset.'.pdf', $title, $rows);
    }

    private function masterRows(Request $request, string $master): Collection
    {
        $model = match ($master) {
            'companies' => Company::class,
            'units' => Unit::class,
            'categories' => ProductCategory::class,
            default => abort(404),
        };

        return $model::query()
            ->when($request->boolean('deleted'), fn (Builder $query) => $query->onlyTrashed())
            ->when(trim((string) $request->query('search')) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.trim((string) $request->query('search')).'%'))
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => match ($master) {
                'companies' => [
                    'Company Name' => $row->name,
                    'Type' => ucfirst((string) $row->company_type),
                    'Default CC Rate' => number_format((float) $row->default_cc_rate, 2).'%',
                    'Status' => $row->deleted_at ? 'Deleted' : ($row->is_active ? 'Active' : 'Inactive'),
                    'Added Date' => $row->created_at?->toDateString(),
                ],
                'units' => [
                    'Unit Name' => $row->name,
                    'Usage Type' => ucfirst((string) $row->type),
                    'Description' => $row->description ?: '-',
                    'Status' => $row->deleted_at ? 'Deleted' : ($row->is_active ? 'Active' : 'Inactive'),
                    'Added Date' => $row->created_at?->toDateString(),
                ],
                'categories' => [
                    'Category Name' => $row->name,
                    'Code' => $row->code ?: '-',
                    'Status' => $row->deleted_at ? 'Deleted' : ($row->is_active ? 'Active' : 'Inactive'),
                    'Added Date' => $row->created_at?->toDateString(),
                ],
            });
    }

    private function supplierRows(Request $request): Collection
    {
        return Supplier::query()
            ->when($request->boolean('deleted'), fn (Builder $query) => $query->onlyTrashed())
            ->when(trim((string) $request->query('search')) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.trim((string) $request->query('search')).'%'))
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'Supplier' => $supplier->name,
                'Phone' => $supplier->phone ?: '-',
                'Email' => $supplier->email ?: '-',
                'PAN' => $supplier->pan_number ?: '-',
                'Balance' => (float) $supplier->current_balance,
                'Status' => $supplier->deleted_at ? 'Deleted' : ($supplier->is_active ? 'Active' : 'Inactive'),
            ]);
    }

    private function customerRows(Request $request): Collection
    {
        return Customer::query()
            ->when($request->boolean('deleted'), fn (Builder $query) => $query->onlyTrashed())
            ->when(trim((string) $request->query('search')) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.trim((string) $request->query('search')).'%'))
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => [
                'Customer' => $customer->name,
                'Phone' => $customer->phone ?: '-',
                'Email' => $customer->email ?: '-',
                'PAN' => $customer->pan_number ?: '-',
                'Credit Limit' => (float) $customer->credit_limit,
                'Balance' => (float) $customer->current_balance,
                'Status' => $customer->deleted_at ? 'Deleted' : ($customer->is_active ? 'Active' : 'Inactive'),
            ]);
    }

    private function salesInvoiceRows(Request $request): Collection
    {
        return SalesInvoice::query()
            ->with(['customer:id,name', 'medicalRepresentative:id,name'])
            ->whereNull('deleted_at')
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('invoice_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('invoice_date', '<=', $request->query('to')))
            ->orderByDesc('invoice_date')
            ->get()
            ->map(fn (SalesInvoice $invoice) => [
                'Invoice' => $invoice->invoice_no,
                'Date' => $invoice->invoice_date?->toDateString(),
                'Customer' => $invoice->customer?->name ?: 'Walk-in',
                'MR' => $invoice->medicalRepresentative?->name ?: '-',
                'Payment' => $invoice->payment_status,
                'Total' => (float) $invoice->grand_total,
                'Paid' => (float) $invoice->paid_amount,
            ]);
    }

    private function purchaseRows(Request $request): Collection
    {
        return Purchase::query()
            ->with('supplier:id,name')
            ->whereNull('deleted_at')
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('purchase_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('purchase_date', '<=', $request->query('to')))
            ->orderByDesc('purchase_date')
            ->get()
            ->map(fn (Purchase $purchase) => [
                'Purchase' => $purchase->purchase_no,
                'Date' => $purchase->purchase_date?->toDateString(),
                'Supplier Bill' => $purchase->supplier_invoice_no ?: '-',
                'Supplier' => $purchase->supplier?->name ?: '-',
                'Payment' => $purchase->payment_status,
                'Total' => (float) $purchase->grand_total,
                'Paid' => (float) $purchase->paid_amount,
            ]);
    }

    private function purchaseOrderRows(Request $request): Collection
    {
        return PurchaseOrder::query()
            ->with('supplier:id,name')
            ->whereNull('deleted_at')
            ->orderByDesc('order_date')
            ->get()
            ->map(fn (PurchaseOrder $order) => [
                'Order' => $order->order_no,
                'Date' => $order->order_date?->toDateString(),
                'Expected' => $order->expected_date?->toDateString() ?: '-',
                'Supplier' => $order->supplier?->name ?: '-',
                'Status' => $order->status,
                'Total' => (float) $order->grand_total,
            ]);
    }

    private function paymentRows(Request $request): Collection
    {
        return Payment::query()
            ->with(['customer:id,name', 'supplier:id,name', 'paymentModeOption:id,name'])
            ->whereNull('deleted_at')
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('payment_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('payment_date', '<=', $request->query('to')))
            ->orderByDesc('payment_date')
            ->get()
            ->map(fn (Payment $payment) => [
                'Payment' => $payment->payment_no,
                'Date' => $payment->payment_date?->toDateString(),
                'Direction' => $payment->direction === 'in' ? 'Payment In' : 'Payment Out',
                'Party' => $payment->party_name,
                'Mode' => $payment->payment_mode_label,
                'Amount' => (float) $payment->amount,
                'Reference' => $payment->reference_no ?: '-',
            ]);
    }

    private function expenseRows(Request $request): Collection
    {
        return Expense::query()
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('expense_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('expense_date', '<=', $request->query('to')))
            ->orderByDesc('expense_date')
            ->get()
            ->map(fn (Expense $expense) => [
                'Date' => $expense->expense_date?->toDateString(),
                'Category' => $expense->category ?: '-',
                'Payment Mode' => $expense->payment_mode ?: '-',
                'Amount' => (float) $expense->amount,
                'Notes' => $expense->notes ?: '-',
            ]);
    }

    private function userRows(Request $request): Collection
    {
        return User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'Name' => $user->name,
                'Email' => $user->email,
                'Roles' => $user->roles->pluck('name')->implode(', ') ?: '-',
                'Status' => $user->is_active ? 'Active' : 'Inactive',
                'Owner' => $user->is_owner ? 'Yes' : 'No',
            ]);
    }

    private function accountTreeRows(Request $request): Collection
    {
        $summary = DB::table('account_transactions')
            ->selectRaw('account_type, SUM(debit) as debit_total, SUM(credit) as credit_total')
            ->groupBy('account_type')
            ->get()
            ->keyBy('account_type');

        return collect(AccountCatalog::all())->map(function (array $account) use ($summary) {
            $totals = $summary->get($account['key']);
            $debit = round((float) ($totals?->debit_total ?? 0), 2);
            $credit = round((float) ($totals?->credit_total ?? 0), 2);
            $closing = AccountCatalog::closingBalance($debit, $credit, $account['nature']);

            return [
                'Code' => $account['code'],
                'Account' => $account['name'],
                'Group' => $account['group'],
                'Normal Side' => strtoupper($account['nature']),
                'Debit' => $debit,
                'Credit' => $credit,
                'Closing' => $closing['amount'].' '.$closing['side'],
            ];
        });
    }

    private function download(string $format, string $excelName, string $pdfName, string $title, Collection $rows)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        if ($format === 'pdf') {
            return Pdf::loadView('exports.table', [
                'title' => $title,
                'rows' => $rows,
                'columns' => array_keys($rows->first() ?? []),
                'generatedAt' => now()->format('Y-m-d H:i'),
            ])->setPaper('a4', 'landscape')->stream($pdfName);
        }

        $directory = storage_path('app/temp-exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/'.uniqid('export_', true).'_'.$excelName;
        (new FastExcel($rows))->export($path);

        return response()->download($path, $excelName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function applyExpiryFilter(Builder $query, string $status): void
    {
        if ($status === 'expired') {
            $query->whereDate('expires_at', '<', today());
        } elseif ($status === '30d') {
            $query->whereBetween('expires_at', [today(), today()->addDays(30)]);
        } elseif ($status === '60d') {
            $query->whereBetween('expires_at', [today(), today()->addDays(60)]);
        } elseif ($status === 'available') {
            $query->where('quantity_available', '>', 0);
        }
    }
}
