<?php

namespace App\Modules\Party\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\WorkspaceScope;
use App\Http\Controllers\Controller;
use App\Modules\Party\Http\Requests\CustomerRequest;
use App\Modules\Party\Http\Requests\PartyIndexRequest;
use App\Modules\Party\Http\Resources\PartyResource;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Services\PartyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(PartyIndexRequest $request, PartyService $service)
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('party.customers.view'), 403);

        return PartyResource::collection($service->customers(TableQueryData::fromRequest($request, ['is_active']), $request->user()));
    }

    public function store(CustomerRequest $request, PartyService $service): JsonResponse
    {
        $customer = $service->createCustomer($request->validated(), $request->user());

        return (new PartyResource($customer))
            ->additional(['message' => 'Customer created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(CustomerRequest $request, Customer $customer, PartyService $service): PartyResource
    {
        WorkspaceScope::ensure($customer, $request->user(), ['tenant_id', 'company_id']);

        return new PartyResource($service->updateCustomer($customer, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('party.customers.manage'), 403);
        WorkspaceScope::ensure($customer, $request->user(), ['tenant_id', 'company_id']);
        $customer->forceFill(['is_active' => false, 'updated_by' => $request->user()?->id])->save();
        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->is_owner
            || $request->user()?->can('party.customers.view')
            || $request->user()?->can('party.customers.manage')
            || $request->user()?->can('sales.invoices.create')
            || $request->user()?->can('sales.pos.use')
            || $request->user()?->can('mr.visits.manage')
            || $request->user()?->can('accounting.vouchers.create')
            || $request->user()?->can('reports.view'),
            403
        );

        return response()->json([
            'data' => WorkspaceScope::apply(Customer::query(), $request->user(), 'customers', ['tenant_id', 'company_id'])
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'phone', 'current_balance', 'credit_limit']),
        ]);
    }
}
