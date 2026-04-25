<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Core\Support\WorkspaceScope;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Modules\Purchase\Http\Requests\PurchaseStoreRequest;
use App\Modules\Purchase\Http\Resources\PurchaseResource;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Services\PurchaseEntryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('purchase.entries.view') || $request->user()?->can('purchase.entries.create'), 403);

        $sorts = [
            'purchase_no' => 'purchase_no',
            'purchase_date' => 'purchase_date',
            'grand_total' => 'grand_total',
            'created_at' => 'created_at',
        ];
        $sortField = $sorts[request('sort_field', 'purchase_date')] ?? 'purchase_date';
        $sortOrder = request('sort_order') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) request('search'));

        $purchases = WorkspaceScope::apply(Purchase::query(), $request->user(), 'purchases', ['tenant_id', 'company_id', 'store_id'])
            ->with('supplier:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('purchase_no', 'like', '%'.$search.'%')
                        ->orWhere('supplier_invoice_no', 'like', '%'.$search.'%')
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when(request()->filled('supplier_id'), fn ($query) => $query->where('supplier_id', request()->integer('supplier_id')))
            ->when(request()->filled('payment_status'), fn ($query) => $query->where('payment_status', request('payment_status')))
            ->when(request()->filled('from'), fn ($query) => $query->whereDate('purchase_date', '>=', request('from')))
            ->when(request()->filled('to'), fn ($query) => $query->whereDate('purchase_date', '<=', request('to')))
            ->orderBy($sortField, $sortOrder)
            ->orderByDesc('id')
            ->paginate(min(100, max(5, request()->integer('per_page', 15))));

        return response()->json(PurchaseResource::collection($purchases)->response()->getData(true));
    }

    public function store(PurchaseStoreRequest $request, PurchaseEntryService $service): JsonResponse
    {
        $purchase = $service->create($request->validated(), $request->user());

        return (new PurchaseResource($purchase))
            ->additional([
                'message' => 'Purchase posted and stock received.',
                'print_url' => route('purchases.print', $purchase),
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function print(Request $request, Purchase $purchase): View
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('purchase.entries.view') || $request->user()?->can('purchase.entries.create'), 403);
        WorkspaceScope::ensure($purchase, $request->user(), ['tenant_id', 'company_id', 'store_id']);

        return view('prints.purchase-invoice', $this->printData($purchase));
    }

    public function pdf(Request $request, Purchase $purchase)
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('purchase.entries.view') || $request->user()?->can('purchase.entries.create'), 403);
        WorkspaceScope::ensure($purchase, $request->user(), ['tenant_id', 'company_id', 'store_id']);

        return Pdf::loadView('prints.purchase-invoice', $this->printData($purchase))
            ->setPaper('a5')
            ->stream($purchase->purchase_no.'.pdf');
    }

    private function printData(Purchase $purchase): array
    {
        return [
            'purchase' => $purchase->load(['supplier', 'items.product', 'items.batch']),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ];
    }
}
