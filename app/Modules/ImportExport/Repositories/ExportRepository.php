<?php

namespace App\Modules\ImportExport\Repositories;

use App\Core\Query\TableQueryApplier;
use App\Core\Support\MoneyAmount;
use App\Models\User;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Support\AccountCatalog;
use App\Modules\ImportExport\Repositories\Interfaces\ExportRepositoryInterface;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Unit;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExportRepository implements ExportRepositoryInterface
{
    public function __construct(private readonly TableQueryApplier $tables) {}

    public function inventoryMasterRows(Request $request, string $master): Collection
    {
        $model = match ($master) {
            'companies' => Company::class,
            'units' => Unit::class,
            default => abort(404),
        };

        $query = $model::query();
        $this->tables->operatingContext($query, $request->user(), ['store' => null]);

        return $query
            ->when($request->boolean('deleted'), fn (Builder $query) => $query->onlyTrashed())
            ->when($this->search($request) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search($request).'%'))
            ->orderBy('name')
            ->limit($this->rowLimit($request))
            ->get()
            ->map(fn ($row) => match ($master) {
                'companies' => [
                    'Company Name' => $row->name,
                    'Type' => ucfirst((string) $row->company_type),
                    'Default CC Rate' => MoneyAmount::decimal($row->default_cc_rate).'%',
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
            });
    }

    public function inventoryProductRows(Request $request): Collection
    {
        $batchStockScope = function (Builder $query) use ($request): void {
            $this->tables->operatingContext($query, $request->user());
            $query->where('is_active', true);
        };

        $query = Product::query()
            ->with(['company:id,name', 'unit:id,name', 'division:id,name,code'])
            ->withSum(['batches as stock_on_hand' => $batchStockScope], 'quantity_available');
        $this->tables->operatingContext($query, $request->user());

        return $query
            ->when($request->filled('company_id'), fn (Builder $query) => $query->where('company_id', $request->integer('company_id')))
            ->when($request->filled('division_id'), fn (Builder $query) => $query->where('division_id', $request->integer('division_id')))
            ->when($this->search($request) !== '', function (Builder $query) use ($request): void {
                $search = $this->search($request);
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('generic_name', 'like', '%'.$search.'%')
                        ->orWhere('product_code', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%');
                });
            })
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->limit($this->rowLimit($request))
            ->get()
            ->map(fn (Product $product): array => [
                'Product' => $product->name,
                'Generic Name' => $product->generic_name ?: '-',
                'Company' => $product->company?->name ?: '-',
                'Division' => $product->division?->name ?: '-',
                'HS Code' => $product->hs_code ?: '-',
                'Packaging' => $product->packaging_type ?: '-',
                'Unit' => $product->unit?->name ?: '-',
                'Reorder Level' => MoneyAmount::decimal($product->reorder_level),
                'Current Stock' => MoneyAmount::decimal($product->stock_on_hand),
                'MRP' => MoneyAmount::decimal($product->mrp),
                'CC Rate' => MoneyAmount::decimal($product->cc_rate).'%',
                'Status' => $product->is_active ? 'Active' : 'Inactive',
            ]);
    }

    public function inventoryBatchRows(Request $request): Collection
    {
        $query = Batch::query()
            ->with(['product:id,name,generic_name', 'supplier:id,name'])
            ->whereNull('deleted_at');
        $this->tables->operatingContext($query, $request->user());

        return $query
            ->when($request->filled('product_id'), fn (Builder $query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('supplier_id'), fn (Builder $query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('expiry_status'), fn (Builder $query) => $this->applyExpiryFilter($query, (string) $request->query('expiry_status')))
            ->when($this->search($request) !== '', function (Builder $query) use ($request): void {
                $search = $this->search($request);
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('batch_no', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%')
                        ->orWhere('storage_location', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('expires_at')
            ->limit($this->rowLimit($request))
            ->get()
            ->map(fn (Batch $batch): array => [
                'Product' => $batch->product?->name ?: '-',
                'Batch No' => $batch->batch_no,
                'Supplier' => $batch->supplier?->name ?: '-',
                'Expiry Date' => $batch->expires_at?->toDateString() ?: '-',
                'Qty Available' => MoneyAmount::decimal($batch->quantity_available),
                'Purchase Price' => MoneyAmount::decimal($batch->purchase_price),
                'MRP' => MoneyAmount::decimal($batch->mrp),
                'Storage' => $batch->storage_location ?: '-',
                'Status' => $batch->is_active ? 'Active' : 'Inactive',
            ]);
    }

    public function datasetRows(Request $request, string $dataset): Collection
    {
        return match ($dataset) {
            'suppliers' => $this->supplierRows($request),
            'customers' => $this->customerRows($request),
            'sales-invoices' => $this->salesInvoiceRows($request),
            'purchases' => $this->purchaseRows($request),
            'purchase-orders' => $this->purchaseOrderRows($request),
            'payments' => $this->paymentRows($request),
            'expenses' => $this->expenseRows($request),
            'users' => $this->userRows($request),
            'account-tree' => $this->accountTreeRows($request),
            default => abort(404),
        };
    }

    private function supplierRows(Request $request): Collection
    {
        $query = Supplier::query();
        $this->tables->operatingContext($query, $request->user(), ['store' => null]);

        return $query
            ->when($request->boolean('deleted'), fn (Builder $query) => $query->onlyTrashed())
            ->when($this->search($request) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search($request).'%'))
            ->orderBy('name')
            ->limit($this->rowLimit($request))
            ->get()
            ->map(fn (Supplier $supplier): array => [
                'Supplier' => $supplier->name,
                'Phone' => $supplier->phone ?: '-',
                'Email' => $supplier->email ?: '-',
                'PAN' => $supplier->pan_number ?: '-',
                'Balance' => MoneyAmount::decimal($supplier->current_balance),
                'Status' => $supplier->deleted_at ? 'Deleted' : ($supplier->is_active ? 'Active' : 'Inactive'),
            ]);
    }

    private function customerRows(Request $request): Collection
    {
        $query = Customer::query();
        $this->tables->operatingContext($query, $request->user(), ['store' => null]);

        return $query
            ->when($request->boolean('deleted'), fn (Builder $query) => $query->onlyTrashed())
            ->when($this->search($request) !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$this->search($request).'%'))
            ->orderBy('name')
            ->limit($this->rowLimit($request))
            ->get()
            ->map(fn (Customer $customer): array => [
                'Customer' => $customer->name,
                'Phone' => $customer->phone ?: '-',
                'Email' => $customer->email ?: '-',
                'PAN' => $customer->pan_number ?: '-',
                'Credit Limit' => MoneyAmount::decimal($customer->credit_limit),
                'Balance' => MoneyAmount::decimal($customer->current_balance),
                'Status' => $customer->deleted_at ? 'Deleted' : ($customer->is_active ? 'Active' : 'Inactive'),
            ]);
    }

    private function salesInvoiceRows(Request $request): Collection
    {
        $query = SalesInvoice::query()
            ->with(['customer:id,name', 'medicalRepresentative:id,name'])
            ->whereNull('deleted_at');
        $this->tables->operatingContext($query, $request->user());

        return $query
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('invoice_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('invoice_date', '<=', $request->query('to')))
            ->orderByDesc('invoice_date')
            ->limit($this->rowLimit($request))
            ->get()
            ->map(fn (SalesInvoice $invoice): array => [
                'Invoice' => $invoice->invoice_no,
                'Date' => $invoice->invoice_date?->toDateString(),
                'Customer' => $invoice->customer?->name ?: 'Walk-in',
                'MR' => $invoice->medicalRepresentative?->name ?: '-',
                'Payment' => $invoice->payment_status,
                'Total' => MoneyAmount::decimal($invoice->grand_total),
                'Paid' => MoneyAmount::decimal($invoice->paid_amount),
            ]);
    }

    private function purchaseRows(Request $request): Collection
    {
        $query = Purchase::query()
            ->with('supplier:id,name')
            ->whereNull('deleted_at');
        $this->tables->operatingContext($query, $request->user());

        return $query
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('purchase_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('purchase_date', '<=', $request->query('to')))
            ->orderByDesc('purchase_date')
            ->limit($this->rowLimit($request))
            ->get()
            ->map(fn (Purchase $purchase): array => [
                'Purchase' => $purchase->purchase_no,
                'Date' => $purchase->purchase_date?->toDateString(),
                'Supplier Bill' => $purchase->supplier_invoice_no ?: '-',
                'Supplier' => $purchase->supplier?->name ?: '-',
                'Payment' => $purchase->payment_status,
                'Total' => MoneyAmount::decimal($purchase->grand_total),
                'Paid' => MoneyAmount::decimal($purchase->paid_amount),
            ]);
    }

    private function purchaseOrderRows(Request $request): Collection
    {
        $query = PurchaseOrder::query()
            ->with('supplier:id,name')
            ->whereNull('deleted_at');
        $this->tables->operatingContext($query, $request->user());

        return $query
            ->orderByDesc('order_date')
            ->limit($this->rowLimit($request))
            ->get()
            ->map(fn (PurchaseOrder $order): array => [
                'Order' => $order->order_no,
                'Date' => $order->order_date?->toDateString(),
                'Expected' => $order->expected_date?->toDateString() ?: '-',
                'Supplier' => $order->supplier?->name ?: '-',
                'Status' => $order->status,
                'Total' => MoneyAmount::decimal($order->grand_total),
            ]);
    }

    private function paymentRows(Request $request): Collection
    {
        $query = Payment::query()
            ->with(['customer:id,name', 'supplier:id,name', 'paymentModeOption:id,name'])
            ->whereNull('deleted_at');
        $this->tables->operatingContext($query, $request->user());

        return $query
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('payment_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('payment_date', '<=', $request->query('to')))
            ->orderByDesc('payment_date')
            ->limit($this->rowLimit($request))
            ->get()
            ->map(fn (Payment $payment): array => [
                'Payment' => $payment->payment_no,
                'Date' => $payment->payment_date?->toDateString(),
                'Direction' => $payment->direction === 'in' ? 'Payment In' : 'Payment Out',
                'Party' => $payment->party_name,
                'Mode' => $payment->payment_mode_label,
                'Amount' => MoneyAmount::decimal($payment->amount),
                'Reference' => $payment->reference_no ?: '-',
            ]);
    }

    private function expenseRows(Request $request): Collection
    {
        $query = Expense::query();
        $this->tables->operatingContext($query, $request->user(), ['store' => null]);

        return $query
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('expense_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('expense_date', '<=', $request->query('to')))
            ->orderByDesc('expense_date')
            ->limit($this->rowLimit($request))
            ->get()
            ->map(fn (Expense $expense): array => [
                'Date' => $expense->expense_date?->toDateString(),
                'Category' => $expense->category ?: '-',
                'Payment Mode' => $expense->payment_mode ?: '-',
                'Amount' => MoneyAmount::decimal($expense->amount),
                'Notes' => $expense->notes ?: '-',
            ]);
    }

    private function userRows(Request $request): Collection
    {
        $query = User::query()
            ->with('roles:id,name')
            ->when(! $request->user()?->canAccessAllTenants(), function (Builder $query) use ($request): void {
                $user = $request->user();
                $query
                    ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
                    ->when($user?->company_id, fn (Builder $builder, int $companyId) => $builder->where('company_id', $companyId))
                    ->when($user?->store_id, function (Builder $builder, int $storeId): void {
                        $builder->where(function (Builder $store) use ($storeId): void {
                            $store->where('store_id', $storeId)->orWhereNull('store_id');
                        });
                    });
            });

        return $query
            ->orderBy('name')
            ->limit($this->rowLimit($request))
            ->get()
            ->map(fn (User $user): array => [
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
            ->when(! $request->user()?->canAccessAllTenants(), function ($query) use ($request): void {
                $user = $request->user();
                $query
                    ->when($user?->tenant_id, fn ($builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
                    ->when($user?->company_id, fn ($builder, int $companyId) => $builder->where('company_id', $companyId))
                    ->when($user?->store_id, function ($builder, int $storeId): void {
                        $builder->where(function ($store) use ($storeId): void {
                            $store->where('store_id', $storeId)->orWhereNull('store_id');
                        });
                    });
            })
            ->when($request->filled('fiscal_year_id'), fn ($query) => $query->where('fiscal_year_id', $request->integer('fiscal_year_id')))
            ->groupBy('account_type')
            ->get()
            ->keyBy('account_type');

        return collect(AccountCatalog::all())->map(function (array $account) use ($summary): array {
            $totals = $summary->get($account['key']);
            $debit = MoneyAmount::decimal($totals?->debit_total ?? 0);
            $credit = MoneyAmount::decimal($totals?->credit_total ?? 0);
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

    private function search(Request $request): string
    {
        return trim((string) $request->query('search'));
    }

    private function rowLimit(Request $request): int
    {
        return min(10000, max(1, $request->integer('limit', 10000)));
    }
}
