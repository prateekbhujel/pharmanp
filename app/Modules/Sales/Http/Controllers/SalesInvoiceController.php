<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\Support\WorkspaceScope;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Modules\Sales\Http\Requests\SalesInvoiceStoreRequest;
use App\Modules\Sales\Http\Resources\SalesInvoiceResource;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('sales.invoices.view') || $request->user()?->can('sales.pos.use'), 403);

        $sorts = [
            'invoice_no' => 'invoice_no',
            'invoice_date' => 'invoice_date',
            'grand_total' => 'grand_total',
            'created_at' => 'created_at',
        ];
        $sortField = $sorts[request('sort_field', 'invoice_date')] ?? 'invoice_date';
        $sortOrder = request('sort_order') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) request('search'));

        $invoices = WorkspaceScope::apply(SalesInvoice::query(), $request->user(), 'sales_invoices', ['tenant_id', 'company_id', 'store_id'])
            ->with(['customer:id,name', 'medicalRepresentative:id,name'])
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

    public function show(SalesInvoice $invoice): SalesInvoiceResource
    {
        abort_unless(request()->user()?->is_owner || request()->user()?->can('sales.invoices.view') || request()->user()?->can('sales.pos.use'), 403);
        WorkspaceScope::ensure($invoice, request()->user(), ['tenant_id', 'company_id', 'store_id']);

        return new SalesInvoiceResource($invoice->load(['customer', 'medicalRepresentative', 'items.product', 'items.batch']));
    }

    public function print(Request $request, SalesInvoice $invoice): View
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('sales.invoices.view') || $request->user()?->can('sales.pos.use'), 403);
        WorkspaceScope::ensure($invoice, $request->user(), ['tenant_id', 'company_id', 'store_id']);

        return view('prints.sales-invoice', $this->printData($invoice));
    }

    public function pdf(Request $request, SalesInvoice $invoice)
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('sales.invoices.view') || $request->user()?->can('sales.pos.use'), 403);
        WorkspaceScope::ensure($invoice, $request->user(), ['tenant_id', 'company_id', 'store_id']);

        return Pdf::loadView('prints.sales-invoice', $this->printData($invoice))
            ->setPaper('a5')
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
