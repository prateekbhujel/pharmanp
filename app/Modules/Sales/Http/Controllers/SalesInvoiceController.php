<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Models\Setting;
use App\Modules\Sales\Http\Requests\SalesInvoiceStoreRequest;
use App\Modules\Sales\Http\Resources\SalesInvoiceResource;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesInvoiceService;
use App\Modules\Setup\Models\DropdownOption;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
    public function index(): JsonResponse
    {
        $sorts = [
            'invoice_no' => 'invoice_no',
            'invoice_date' => 'invoice_date',
            'grand_total' => 'grand_total',
            'created_at' => 'created_at',
        ];
        $sortField = $sorts[request('sort_field', 'invoice_date')] ?? 'invoice_date';
        $sortOrder = request('sort_order') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) request('search'));

        $invoices = SalesInvoice::query()
            ->with(['customer:id,name', 'medicalRepresentative:id,name'])
            ->when(request()->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('invoice_no', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when(request()->filled('customer_id'), fn ($query) => $query->where('customer_id', request()->integer('customer_id')))
            ->when(request()->filled('payment_status'), fn ($query) => $query->where('payment_status', request('payment_status')))
            ->when(request()->filled('medical_representative_id'), fn ($query) => $query->where('medical_representative_id', request()->integer('medical_representative_id')))
            ->when(request()->filled('from'), fn ($query) => $query->whereDate('invoice_date', '>=', request('from')))
            ->when(request()->filled('to'), fn ($query) => $query->whereDate('invoice_date', '<=', request('to')))
            ->orderBy($sortField, $sortOrder)
            ->orderByDesc('id')
            ->paginate(min(100, max(5, request()->integer('per_page', 15))));

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
        return new SalesInvoiceResource($invoice->load(['customer', 'medicalRepresentative', 'items.product', 'items.batch', 'returns.items.product', 'returns.items.batch']));
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
    public function updatePayment(Request $request, SalesInvoice $invoice, SalesInvoiceService $service): SalesInvoiceResource
    {
        $validated = $request->validate([
            'paid_amount' => ['required', 'numeric', 'min:0', 'max:'.(float) $invoice->grand_total],
            'payment_mode_id' => [
                'nullable',
                Rule::exists('dropdown_options', 'id')->where(fn ($query) => $query->where('alias', 'payment_mode')),
            ],
        ]);

        $cashAccount = 'cash';
        if (! empty($validated['payment_mode_id'])) {
            $mode = DropdownOption::query()->forAlias('payment_mode')->find($validated['payment_mode_id']);
            $cashAccount = strtolower((string) ($mode?->data ?: $mode?->name)) === 'cash' ? 'cash' : 'bank';
        }

        $invoice = $service->updatePayment($invoice, [
            'paid_amount' => $validated['paid_amount'],
            'cash_account' => $cashAccount,
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
            'invoice' => $invoice->load(['customer', 'medicalRepresentative', 'items.product', 'items.batch']),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ];
    }
}
