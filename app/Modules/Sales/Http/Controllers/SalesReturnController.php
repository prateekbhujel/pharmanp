<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Models\Setting;
use App\Modules\Sales\Http\Requests\SalesReturnRequest;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use App\Modules\Sales\Services\SalesReturnService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * @OA\Tag(
 *     name="SALES - POS and Invoices",
 *     description="API endpoints for SALES - POS and Invoices"
 * )
 */
class SalesReturnController extends ModularController
{
    public function __construct(private readonly SalesReturnService $returns) {}

    /**
     * @OA\Get(
     *     path="/sales/returns",
     *     summary="Api Sales Returns Index",
     *     tags={"SALES - Returns"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->returns->table(
            TableQueryData::fromRequest($request, ['deleted', 'from', 'to', 'customer_id', 'return_type']),
            $request->user(),
        ));
    }

    /**
     * @OA\Get(
     *     path="/sales/returns/{salesReturn}",
     *     summary="Api Sales Returns Show",
     *     tags={"SALES - Returns"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(SalesReturn $salesReturn): JsonResponse
    {
        return response()->json(['data' => $this->returns->payload($salesReturn)]);
    }

    /**
     * @OA\Post(
     *     path="/sales/returns",
     *     summary="Api Sales Returns Store",
     *     tags={"SALES - Returns"},
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
    public function store(SalesReturnRequest $request): JsonResponse
    {
        $return = $this->returns->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Sales return created.',
            'data' => ['id' => $return->id, 'return_no' => $return->return_no],
            'print_url' => route('sales.returns.print', $return),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/sales/returns/{salesReturn}",
     *     summary="Api Sales Returns Update",
     *     tags={"SALES - Returns"},
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
    public function update(SalesReturnRequest $request, SalesReturn $salesReturn): JsonResponse
    {
        return response()->json([
            'message' => 'Sales return updated.',
            'data' => $this->returns->payload($this->returns->update($salesReturn, $request->validated(), $request->user())),
            'print_url' => route('sales.returns.print', $salesReturn),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/sales/returns/{salesReturn}",
     *     summary="Api Sales Returns Destroy",
     *     tags={"SALES - Returns"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, SalesReturn $salesReturn): JsonResponse
    {
        $this->returns->delete($salesReturn, $request->user());

        return response()->json(['message' => 'Sales return deleted.']);
    }

    /**
     * @OA\Get(
     *     path="/sales/returns/invoice-options",
     *     summary="Api Sales Returns Invoice Options",
     *     tags={"SALES - Returns"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function invoiceOptions(Request $request): JsonResponse
    {
        return response()->json($this->returns->invoiceOptions(
            $request->only(['customer_id', 'q']),
            $request->user(),
        ));
    }

    /**
     * @OA\Get(
     *     path="/sales/returns/invoices/{invoice}/items",
     *     summary="Api Sales Returns Invoice Items",
     *     tags={"SALES - Returns"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function invoiceItems(SalesInvoice $invoice): JsonResponse
    {
        return response()->json($this->returns->invoiceItems($invoice));
    }

    public function print(SalesReturn $salesReturn): View
    {
        return view('prints.sales-return', $this->printData($salesReturn));
    }

    public function pdf(SalesReturn $salesReturn)
    {
        return Pdf::loadView('prints.sales-return', $this->printData($salesReturn))
            ->setPaper('a4', 'portrait')
            ->stream($salesReturn->return_no.'.pdf');
    }

    private function printData(SalesReturn $salesReturn): array
    {
        return [
            'salesReturn' => $salesReturn->load(['customer', 'invoice', 'items.product', 'items.batch']),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ];
    }
}
