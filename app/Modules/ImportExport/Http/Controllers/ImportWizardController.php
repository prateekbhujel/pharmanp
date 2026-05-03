<?php

namespace App\Modules\ImportExport\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\ImportExport\Http\Requests\ConfirmImportRequest;
use App\Modules\ImportExport\Http\Requests\PreviewImportRequest;
use App\Modules\ImportExport\Models\ImportJob;
use App\Modules\ImportExport\Services\ImportPreviewService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @OA\Tag(
 *     name="IMPORT EXPORT - Imports and OCR",
 *     description="API endpoints for IMPORT EXPORT - Imports and OCR"
 * )
 */
class ImportWizardController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/imports/targets",
     *     summary="Api Imports Targets",
     *     tags={"IMPORT EXPORT - Targets"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/imports/preview",
     *     summary="Api Imports Preview",
     *     tags={"IMPORT EXPORT - Preview"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function preview(PreviewImportRequest $request, ImportPreviewService $service): JsonResponse
    {
        $job = $service->preview(
            $request->validated('target'),
            $request->file('file'),
            $request->user()?->id,
        );

        return $this->jobResponse($job, $service);
    }

    /**
     * @OA\Get(
     *     path="/imports/targets/{target}/sample",
     *     summary="Api Imports Sample",
     *     tags={"IMPORT EXPORT - Targets"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function sample(string $target, ImportPreviewService $service): StreamedResponse
    {
        abort_unless(array_key_exists($target, $service->targetFields()), 404);

        return response()->streamDownload(function () use ($target, $service) {
            echo $service->sampleCsv($target);
        }, $target.'-sample.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @OA\Post(
     *     path="/imports/confirm",
     *     summary="Api Imports Confirm",
     *     tags={"IMPORT EXPORT - Confirm"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function confirm(ConfirmImportRequest $request, ImportPreviewService $service): JsonResponse
    {
        $job = $service->confirm(
            (int) $request->validated('import_job_id'),
            $request->validated('mapping'),
            $request->user(),
        );

        return $this->jobResponse($job, $service);
    }

    /**
     * @OA\Get(
     *     path="/imports/{job}/rejected.csv",
     *     summary="Api Imports Rejected",
     *     tags={"IMPORT EXPORT - {job}"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function rejected(ImportJob $job, ImportPreviewService $service): StreamedResponse
    {
        return response()->streamDownload(function () use ($job, $service) {
            echo $service->rejectedCsv($job);
        }, 'import-'.$job->id.'-rejected.csv', ['Content-Type' => 'text/csv']);
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
