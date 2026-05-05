<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Purchase\Http\Requests\PurchaseStoreRequest;
use App\Modules\Purchase\Http\Resources\PurchaseResource;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Services\PurchaseEntryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * @OA\Tag(
 *     name="PURCHASE - Purchase Workflow",
 *     description="API endpoints for PURCHASE - Purchase Workflow"
 * )
 */
class PurchaseController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/purchases",
     *     summary="Api Purchases Index",
     *     tags={"PURCHASE - Purchase Bills"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, PurchaseEntryService $service): JsonResponse
    {
        $purchases = $service->table(
            TableQueryData::fromRequest($request, ['supplier_id', 'payment_status', 'from', 'to']),
            $request->user(),
        );

        return response()->json(PurchaseResource::collection($purchases)->response()->getData(true));
    }

    /**
     * @OA\Post(
     *     path="/purchases",
     *     summary="Api Purchases Store",
     *     tags={"PURCHASE - Purchase Bills"},
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
    public function store(PurchaseStoreRequest $request, PurchaseEntryService $service): JsonResponse
    {
        $purchase = $service->create($request->validated(), $request->user());

        return (new PurchaseResource($purchase))
            ->additional([
                'message' => 'Purchase posted and stock received.',
                'print_url' => route('purchases.print', $purchase, false),
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function print(Request $request, Purchase $purchase, PurchaseEntryService $service): View
    {
        $service->assertAccessible($purchase, $request->user());

        return view('prints.purchase-invoice', $service->printPayload($purchase));
    }

    public function pdf(Request $request, Purchase $purchase, PurchaseEntryService $service)
    {
        $service->assertAccessible($purchase, $request->user());

        @ini_set('memory_limit', '512M');
        @ini_set('pcre.backtrack_limit', '5000000');

        return Pdf::loadView('prints.purchase-invoice', $service->printPayload($purchase))
            ->setPaper('a4', 'landscape')
            ->stream($purchase->purchase_no.'.pdf');
    }
}
