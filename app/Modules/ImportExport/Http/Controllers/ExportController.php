<?php

namespace App\Modules\ImportExport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Company;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\ProductCategory;
use App\Modules\Inventory\Models\Unit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;

class ExportController extends Controller
{
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
