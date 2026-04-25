<?php

namespace App\Modules\ImportExport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ImportExport\Http\Requests\ConfirmImportRequest;
use App\Modules\ImportExport\Http\Requests\PreviewImportRequest;
use App\Modules\ImportExport\Models\ImportJob;
use App\Modules\ImportExport\Services\ImportPreviewService;
use Illuminate\Http\JsonResponse;

class ImportWizardController extends Controller
{
    public function targets(ImportPreviewService $service): JsonResponse
    {
        return response()->json([
            'data' => collect($service->targetFields())->map(fn (array $fields, string $target) => [
                'target' => $target,
                'fields' => $fields,
                'required' => $service->requiredFields($target),
            ])->values(),
        ]);
    }

    public function preview(PreviewImportRequest $request, ImportPreviewService $service): JsonResponse
    {
        $job = $service->preview(
            $request->validated('target'),
            $request->file('file'),
            $request->user()?->id,
        );

        return $this->jobResponse($job, $service);
    }

    public function confirm(ConfirmImportRequest $request, ImportPreviewService $service): JsonResponse
    {
        $job = $service->confirm(
            (int) $request->validated('import_job_id'),
            $request->validated('mapping'),
        );

        return $this->jobResponse($job, $service);
    }

    private function jobResponse(ImportJob $job, ImportPreviewService $service): JsonResponse
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
                'rows' => $job->rows->map(fn ($row) => [
                    'row_number' => $row->row_number,
                    'raw_data' => $row->raw_data,
                    'errors' => $row->errors,
                    'status' => $row->status,
                ])->values(),
            ],
        ]);
    }
}
