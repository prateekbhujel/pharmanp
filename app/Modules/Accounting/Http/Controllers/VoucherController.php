<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Core\Support\WorkspaceScope;
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
        abort_unless($request->user()?->is_owner || $request->user()?->can('accounting.vouchers.view'), 403);

        $vouchers = WorkspaceScope::apply(Voucher::query(), $request->user(), 'vouchers', ['tenant_id', 'company_id'])
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
