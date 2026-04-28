<?php

namespace App\Modules\ImportExport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ImportExport\Services\PurchaseOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOcrController extends Controller
{
    public function extract(Request $request, PurchaseOcrService $service): JsonResponse
    {
        $uploadMaxKb = max(1024, (int) config('services.ocr_space.upload_max_kb', 10240));

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.$uploadMaxKb],
        ]);

        $result = $service->extract($request->file('image'));

        return response()->json([
            'message' => $result['extraction_status'] === 'success'
                ? 'OCR text extracted successfully.'
                : ($result['failure_message'] ?? 'OCR could not read this image clearly.'),
            'data' => $result,
        ], $result['extraction_status'] === 'success' ? 200 : 422);
    }

    public function draftPurchase(Request $request, PurchaseOcrService $service): JsonResponse
    {
        $validated = $request->validate([
            'ocr_text' => ['required', 'string'],
            'analysis' => ['nullable', 'array'],
            'matches' => ['nullable', 'array'],
            'selected_purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
        ]);

        return response()->json([
            'message' => 'OCR draft prepared for purchase entry.',
            'data' => $service->draftPurchase($validated),
        ]);
    }
}
