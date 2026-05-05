<?php

namespace App\Modules\Party\Http\Controllers;

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
    public function destroy(Request $request, Customer $customer, PartyService $service): JsonResponse
    {
        $service->deleteCustomer($customer, $request->user());

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
    public function toggleStatus(Request $request, Customer $customer, PartyService $service): JsonResponse
    {
        $customer = $service->setCustomerStatus($customer, $request->boolean('is_active'), $request->user());

        return response()->json(['message' => 'Customer status updated.', 'data' => new PartyResource($customer)]);
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
    public function restore(Request $request, int $id, PartyService $service): JsonResponse
    {
        $customer = $service->restoreCustomer($id, $request->user());

        return response()->json(['message' => 'Customer restored.', 'data' => new PartyResource($customer)]);
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
    public function options(Request $request, PartyService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->customerOptions($request->user()),
        ]);
    }
}
