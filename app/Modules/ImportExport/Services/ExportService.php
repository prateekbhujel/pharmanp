<?php

namespace App\Modules\ImportExport\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\ImportExport\Repositories\Interfaces\ExportRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;

class ExportService
{
    public function __construct(private readonly ExportRepositoryInterface $exports) {}

    public function inventoryMaster(Request $request, string $master, string $format)
    {
        $title = match ($master) {
            'companies' => 'Company List',
            'units' => 'Unit List',
            default => abort(404),
        };

        return $this->download($format, $master.'.xlsx', $master.'.pdf', $title, $this->exports->inventoryMasterRows($request, $master));
    }

    public function inventoryProducts(Request $request, string $format)
    {
        return $this->download($format, 'inventory-products.xlsx', 'inventory-products.pdf', 'Inventory Product List', $this->exports->inventoryProductRows($request));
    }

    public function inventoryBatches(Request $request, string $format)
    {
        return $this->download($format, 'inventory-batches.xlsx', 'inventory-batches.pdf', 'Inventory Batch List', $this->exports->inventoryBatchRows($request));
    }

    public function dataset(Request $request, string $dataset, string $format)
    {
        $title = match ($dataset) {
            'suppliers' => 'Supplier List',
            'customers' => 'Customer List',
            'sales-invoices' => 'Sales Invoice List',
            'purchases' => 'Purchase Bill List',
            'purchase-orders' => 'Purchase Order List',
            'payments' => 'Payment List',
            'expenses' => 'Expense List',
            'users' => 'User List',
            'account-tree' => 'Account Tree',
            default => abort(404),
        };

        return $this->download($format, $dataset.'.xlsx', $dataset.'.pdf', $title, $this->exports->datasetRows($request, $dataset));
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
}
