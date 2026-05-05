<?php

namespace App\Modules\Reports\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

class ReportExportService
{
    public function __construct(private readonly ReportService $reports) {}

    public function export(string $report, string $format, Request $request)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $request->merge(['per_page' => min((int) $request->query('per_page', 5000), 5000)]);

        $payload = $this->reports->run($report, $request, 5000);
        $rows = $this->exportRows(collect($payload['data'] ?? []));
        $title = Str::of($report)->replace('-', ' ')->title()->append(' Report')->toString();

        if ($format === 'pdf') {
            return Pdf::loadView('exports.table', [
                'title' => $title,
                'rows' => $rows,
                'columns' => array_keys($rows->first() ?? []),
                'generatedAt' => now()->format('Y-m-d H:i'),
            ])->setPaper('a4', 'landscape')->stream(Str::slug($title).'.pdf');
        }

        $directory = storage_path('app/temp-exports');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/'.uniqid('report_', true).'_'.Str::slug($title).'.xlsx';
        (new FastExcel($rows))->export($path);

        return response()->download($path, Str::slug($title).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function exportRows(Collection $rows): Collection
    {
        return $rows->map(function ($row): array {
            return collect((array) $row)->mapWithKeys(function ($value, $key): array {
                $label = Str::of((string) $key)->replace('_', ' ')->title()->toString();

                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                } elseif (is_array($value)) {
                    $value = json_encode($value);
                } elseif ($value === null || $value === '') {
                    $value = '-';
                }

                return [$label => $value];
            })->all();
        });
    }
}
