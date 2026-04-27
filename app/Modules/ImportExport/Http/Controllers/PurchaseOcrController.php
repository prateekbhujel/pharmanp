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
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $result = $service->extract($request->file('image'));

        return response()->json([
            'message' => $result['extraction_status'] === 'success'
                ? 'OCR text extracted successfully.'
                : ($result['failure_message'] ?? 'OCR could not read this image clearly.'),
            'data' => $result,
        ], $result['extraction_status'] === 'success' ? 200 : 422);
    }
}
