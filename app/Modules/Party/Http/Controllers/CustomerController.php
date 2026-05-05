<?php

namespace App\Modules\Party\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Party\Http\Requests\CustomerRequest;
use App\Modules\Party\Http\Requests\PartyIndexRequest;
use App\Modules\Party\Http\Resources\PartyResource;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Services\PartyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="PARTY - Customers and Suppliers",
 *     description="API endpoints for PARTY - Customers and Suppliers"
 * )
 */
class CustomerController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/customers",
     *     summary="Api Customers Index",
     *     tags={"PARTY - Customers"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(PartyIndexRequest $request, PartyService $service)
    {
        return PartyResource::collection($service->customers(TableQueryData::fromRequest($request, ['is_active', 'deleted']), $request->user()));
    }

    /**
     * @OA\Post(
     *     path="/customers",
     *     summary="Api Customers Store",
     *     tags={"PARTY - Customers"},
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
    public function store(CustomerRequest $request, PartyService $service): JsonResponse
    {
        $customer = $service->createCustomer($request->validated(), $request->user());

        return (new PartyResource($customer))
            ->additional(['message' => 'Customer created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/customers/{customer}",
     *     summary="Api Customers Update",
     *     tags={"PARTY - Customers"},
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
    public function update(CustomerRequest $request, Customer $customer, PartyService $service): PartyResource
    {
        return new PartyResource($service->updateCustomer($customer, $request->validated(), $request->user()));
    }

    /**
     * @OA\Delete(
     *     path="/customers/{customer}",
     *     summary="Api Customers Destroy",
     *     tags={"PARTY - Customers"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $customer->forceFill(['is_active' => false, 'updated_by' => $request->user()?->id])->save();
        $customer->delete();

        return response()->json(['message' => 'Customer deleted.']);
    }

    /**
     * @OA\Patch(
     *     path="/customers/{customer}/status",
     *     summary="Api Customers Status",
     *     tags={"PARTY - Customers"},
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
    public function toggleStatus(Request $request, Customer $customer): JsonResponse
    {
        $customer->forceFill([
            'is_active' => $request->boolean('is_active'),
            'updated_by' => $request->user()?->id,
        ])->save();

        return response()->json(['message' => 'Customer status updated.', 'data' => new PartyResource($customer->refresh())]);
    }

    /**
     * @OA\Post(
     *     path="/customers/{id}/restore",
     *     summary="Api Customers Restore",
     *     tags={"PARTY - Customers"},
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
    public function restore(Request $request, int $id): JsonResponse
    {
        $customer = Customer::query()->onlyTrashed()->findOrFail($id);
        $customer->restore();
        $customer->forceFill(['is_active' => true, 'updated_by' => $request->user()?->id])->save();

        return response()->json(['message' => 'Customer restored.', 'data' => new PartyResource($customer->refresh())]);
    }

    /**
     * @OA\Get(
     *     path="/customers/options",
     *     summary="Api Customers Options",
     *     tags={"PARTY - Customers"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => Customer::query()
                ->where('is_active', true)
                ->when(request()->user()?->tenant_id, fn ($query, $tenantId) => $query->where('tenant_id', $tenantId))
                ->orderBy('name')
                ->limit(50)
                ->get(['id', 'name', 'phone', 'current_balance', 'credit_limit']),
        ]);
    }
}
