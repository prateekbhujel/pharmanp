<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

class ReportController extends Controller
{
    public function __invoke(string $report, Request $request, ReportService $service): JsonResponse
    {
        return response()->json($service->run($report, $request));
    }

    public function export(string $report, string $format, Request $request, ReportService $service)
    {
        $request->merge(['per_page' => min((int) $request->query('per_page', 5000), 5000)]);
        $payload = $service->run($report, $request);
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
        return $rows->map(function ($row) {
            return collect((array) $row)->mapWithKeys(function ($value, $key) {
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
