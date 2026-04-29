<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\VoucherStoreRequest;
use App\Modules\Accounting\Http\Resources\VoucherResource;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vouchers = Voucher::query()
            ->withCount('entries')
            ->when($request->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when($request->filled('search'), function ($query) use ($request) {
                $keyword = trim((string) $request->query('search'));
                $query->where(function ($builder) use ($keyword) {
                    $builder->where('voucher_no', 'like', '%'.$keyword.'%')
                        ->orWhere('voucher_type', 'like', '%'.$keyword.'%')
                        ->orWhere('notes', 'like', '%'.$keyword.'%');
                });
            })
            ->when($request->filled('voucher_type'), fn ($query) => $query->where('voucher_type', $request->query('voucher_type')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('voucher_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('voucher_date', '<=', $request->query('to')))
            ->latest('voucher_date')
            ->latest('id')
            ->paginate(min($request->integer('per_page', 15), 100));

        return response()->json(VoucherResource::collection($vouchers)->response()->getData(true));
    }

    public function show(Voucher $voucher): VoucherResource
    {
        return new VoucherResource($voucher->load('entries'));
    }

    public function store(VoucherStoreRequest $request, VoucherService $service): JsonResponse
    {
        $voucher = $service->create($request->validated(), $request->user());

        return (new VoucherResource($voucher))
            ->additional(['message' => 'Voucher posted.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(VoucherStoreRequest $request, Voucher $voucher, VoucherService $service): VoucherResource
    {
        return (new VoucherResource($service->update($voucher, $request->validated(), $request->user())))
            ->additional(['message' => 'Voucher updated.']);
    }

    public function destroy(Voucher $voucher, VoucherService $service): JsonResponse
    {
        $service->delete($voucher);

        return response()->json(['message' => 'Voucher deleted successfully.']);
    }
}
