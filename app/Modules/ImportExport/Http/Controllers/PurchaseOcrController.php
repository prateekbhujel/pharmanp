<?php

namespace App\Modules\ImportExport\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\ImportExport\Http\Requests\PurchaseOcrDraftRequest;
use App\Modules\ImportExport\Http\Requests\PurchaseOcrExtractRequest;
use App\Modules\ImportExport\Services\PurchaseOcrService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="IMPORT EXPORT - Imports and OCR",
 *     description="API endpoints for IMPORT EXPORT - Imports and OCR"
 * )
 */
class PurchaseOcrController extends ModularController
{
    /**
     * @OA\Post(
     *     path="/imports/ocr/extract",
     *     summary="Api Imports Ocr Extract",
     *     tags={"IMPORT EXPORT - Ocr"},
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
    public function extract(PurchaseOcrExtractRequest $request, PurchaseOcrService $service): JsonResponse
    {
        $result = $service->extract($request->file('image'));

        $message = $result['extraction_status'] === 'success'
            ? 'OCR text extracted successfully.'
            : ($result['failure_message'] ?? 'OCR could not read this image clearly.');

        return $result['extraction_status'] === 'success'
            ? $this->success($result, $message)
            : $this->error($message, 422, ['image' => [$message]]);
    }

    /**
     * @OA\Post(
     *     path="/imports/ocr/draft-purchase",
     *     summary="Api Imports Ocr Draft Purchase",
     *     tags={"IMPORT EXPORT - Ocr"},
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
    public function draftPurchase(PurchaseOcrDraftRequest $request, PurchaseOcrService $service): JsonResponse
    {
        return $this->success($service->draftPurchase($request->validated()), 'OCR draft prepared for purchase entry.');
    }
}
