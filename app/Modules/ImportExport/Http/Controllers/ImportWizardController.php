<?php

namespace App\Modules\ImportExport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ImportExport\Http\Requests\ConfirmImportRequest;
use App\Modules\ImportExport\Http\Requests\PreviewImportRequest;
use App\Modules\ImportExport\Models\ImportJob;
use App\Modules\ImportExport\Contracts\ImportPreviewServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportWizardController extends Controller
{
    public function targets(ImportPreviewServiceInterface $service): JsonResponse
    {
        return response()->json([
            'data' => collect($service->targetFields())->map(fn (array $fields, string $target) => [
                'target' => $target,
                'fields' => $fields,
                'required' => $service->requiredFields($target),
            ])->values(),
        ]);
    }

    public function preview(PreviewImportRequest $request, ImportPreviewServiceInterface $service): JsonResponse
    {
        $job = $service->preview(
            $request->validated('target'),
            $request->file('file'),
            $request->user()?->id,
        );

        return $this->jobResponse($job, $service);
    }

    public function sample(string $target, ImportPreviewServiceInterface $service): StreamedResponse
    {
        abort_unless(array_key_exists($target, $service->targetFields()), 404);

        return response()->streamDownload(function () use ($target, $service) {
            echo $service->sampleCsv($target);
        }, $target.'-sample.csv', ['Content-Type' => 'text/csv']);
    }

    public function confirm(ConfirmImportRequest $request, ImportPreviewServiceInterface $service): JsonResponse
    {
        $job = $service->confirm(
            (int) $request->validated('import_job_id'),
            $request->validated('mapping'),
            $request->user(),
        );

        return $this->jobResponse($job, $service);
    }

    public function rejected(ImportJob $job, ImportPreviewServiceInterface $service): StreamedResponse
    {
        return response()->streamDownload(function () use ($job, $service) {
            echo $service->rejectedCsv($job);
        }, 'import-'.$job->id.'-rejected.csv', ['Content-Type' => 'text/csv']);
    }

    private function jobResponse(ImportJob $job, ImportPreviewServiceInterface $service): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $job->id,
                'target' => $job->target,
                'status' => $job->status,
                'original_filename' => $job->original_filename,
                'detected_columns' => $job->detected_columns,
                'system_fields' => $service->targetFields()[$job->target] ?? [],
                'required_fields' => $service->requiredFields($job->target),
                'total_rows' => $job->total_rows,
                'valid_rows' => $job->valid_rows,
                'invalid_rows' => $job->invalid_rows,
                'rows' => $job->rows->sortBy('row_number')->take(50)->map(fn ($row) => [
                    'row_number' => $row->row_number,
                    'raw_data' => $row->raw_data,
                    'errors' => $row->errors,
                    'status' => $row->status,
                ])->values(),
            ],
        ]);
    }
}
