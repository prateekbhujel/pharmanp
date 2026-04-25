<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Modules\Sales\Http\Requests\SalesInvoiceStoreRequest;
use App\Modules\Sales\Http\Resources\SalesInvoiceResource;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
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
            ->with('customer:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('invoice_no', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$search.'%'));
                });
            })
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
        return new SalesInvoiceResource($invoice->load(['customer', 'items.product', 'items.batch']));
    }

    public function print(SalesInvoice $invoice): View
    {
        return view('prints.sales-invoice', $this->printData($invoice));
    }

    public function pdf(SalesInvoice $invoice)
    {
        return Pdf::loadView('prints.sales-invoice', $this->printData($invoice))
            ->setPaper('a5')
            ->stream($invoice->invoice_no.'.pdf');
    }

    private function printData(SalesInvoice $invoice): array
    {
        return [
            'invoice' => $invoice->load(['customer', 'items.product', 'items.batch']),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ];
    }
}
