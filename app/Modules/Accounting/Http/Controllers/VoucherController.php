<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\VoucherStoreRequest;
use App\Modules\Accounting\Http\Resources\VoucherResource;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Services\VoucherService;
use Illuminate\Http\JsonResponse;

class VoucherController extends Controller
{
    public function index(): JsonResponse
    {
        $vouchers = Voucher::query()
            ->latest('voucher_date')
            ->latest('id')
            ->paginate(request()->integer('per_page', 15));

        return response()->json(VoucherResource::collection($vouchers)->response()->getData(true));
    }

    public function store(VoucherStoreRequest $request, VoucherService $service): JsonResponse
    {
        $voucher = $service->create($request->validated(), $request->user());

        return (new VoucherResource($voucher))
            ->additional(['message' => 'Voucher posted.'])
            ->response()
            ->setStatusCode(201);
    }
}
