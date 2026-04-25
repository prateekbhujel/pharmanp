<?php

namespace App\Modules\Party\Http\Controllers;

use App\Core\DTOs\TableQueryData;
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
        return PartyResource::collection($service->customers(TableQueryData::fromRequest($request, ['is_active'])));
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
        return new PartyResource($service->updateCustomer($customer, $request->validated(), $request->user()));
    }

    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $customer->forceFill(['is_active' => false, 'updated_by' => $request->user()?->id])->save();
        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'data' => Customer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'phone', 'current_balance', 'credit_limit']),
        ]);
    }
}
