<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Models\Setting;
use App\Modules\Sales\Http\Requests\SalesInvoicePaymentRequest;
use App\Modules\Sales\Http\Requests\SalesInvoiceStoreRequest;
use App\Modules\Sales\Http\Resources\SalesInvoiceResource;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesInvoiceService;
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
class SalesInvoiceController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/sales/invoices",
     *     summary="Api Invoices Index",
     *     tags={"SALES - Invoices"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, SalesInvoiceService $service): JsonResponse
    {
        $invoices = $service->table(
            TableQueryData::fromRequest($request, ['customer_id', 'payment_status', 'medical_representative_id', 'from', 'to']),
            $request->user(),
        );

        return response()->json(SalesInvoiceResource::collection($invoices)->response()->getData(true));
    }

    /**
     * @OA\Post(
     *     path="/sales/invoices",
     *     summary="Api Invoices Store",
     *     tags={"SALES - Invoices"},
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
    public function store(SalesInvoiceStoreRequest $request, SalesInvoiceService $service): JsonResponse
    {
        $invoice = $service->create($request->validated(), $request->user());

        return (new SalesInvoiceResource($invoice))
            ->additional([
                'message' => 'Invoice posted and stock deducted.',
                'print_url' => route('sales.invoices.print', $invoice),
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Get(
     *     path="/sales/invoices/{invoice}",
     *     summary="Api Invoices Show",
     *     tags={"SALES - Invoices"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(SalesInvoice $invoice): SalesInvoiceResource
    {
        return new SalesInvoiceResource($invoice->load(['customer', 'medicalRepresentative', 'paymentMode', 'items.product', 'items.batch', 'returns.items.product', 'returns.items.batch']));
    }

    /**
     * @OA\Get(
     *     path="/sales/invoices/{invoice}/items",
     *     summary="Api Sales Invoices Items",
     *     tags={"SALES - Invoices"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function items(SalesInvoice $invoice): JsonResponse
    {
        $invoice->load(['items.product', 'items.batch']);

        return response()->json([
            'data' => (new SalesInvoiceResource($invoice))->resolve(request())['items'] ?? [],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/sales/invoices/{invoice}/returns",
     *     summary="Api Sales Invoices Returns",
     *     tags={"SALES - Invoices"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function returns(SalesInvoice $invoice): JsonResponse
    {
        $invoice->load(['returns.items.product', 'returns.items.batch']);

        return response()->json([
            'data' => (new SalesInvoiceResource($invoice))->resolve(request())['returns'] ?? [],
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/sales/invoices/{invoice}/payment",
     *     summary="Api Sales Invoices Payment",
     *     tags={"SALES - Invoices"},
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
    public function updatePayment(SalesInvoicePaymentRequest $request, SalesInvoice $invoice, SalesInvoiceService $service): SalesInvoiceResource
    {
        $validated = $request->validated();

        $invoice = $service->updatePayment($invoice, [
            'paid_amount' => $validated['paid_amount'],
            'payment_mode_id' => $validated['payment_mode_id'] ?? null,
            'cash_account' => $service->cashAccountForPaymentMode($validated['payment_mode_id'] ?? null),
        ], $request->user());

        return (new SalesInvoiceResource($invoice))->additional(['message' => 'Invoice payment updated.']);
    }

    public function print(SalesInvoice $invoice): View
    {
        return view('prints.sales-invoice', $this->printData($invoice));
    }

    public function pdf(SalesInvoice $invoice)
    {
        return Pdf::loadView('prints.sales-invoice', $this->printData($invoice))
            ->setPaper('a4', 'landscape')
            ->stream($invoice->invoice_no.'.pdf');
    }

    private function printData(SalesInvoice $invoice): array
    {
        return [
            'invoice' => $invoice->load(['customer', 'medicalRepresentative', 'paymentMode', 'items.product', 'items.batch']),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ];
    }
}
