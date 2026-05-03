<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\Setup\Models\SupplierType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class SupplierTypeController extends ModularController
{
    // Return all supplier types.
    /**
     * @OA\Get(
     *     path="/settings/supplier-types",
     *     summary="Api Settings Supplier Types Index",
     *     tags={"SETUP - Supplier Types"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SupplierType::query()->orderBy('name')->get(),
        ]);
    }

    // Create a new supplier type.
    /**
     * @OA\Post(
     *     path="/settings/supplier-types",
     *     summary="Api Settings Supplier Types Store",
     *     tags={"SETUP - Supplier Types"},
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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('supplier_types', 'name')],
            'code' => ['nullable', 'string', 'max:80'],
        ]);

        $supplierType = SupplierType::query()->create($validated);

        return response()->json([
            'message' => 'Supplier type created.',
            'data' => $supplierType,
        ]);
    }

    // Update a supplier type.
    /**
     * @OA\Put(
     *     path="/settings/supplier-types/{supplierType}",
     *     summary="Api Settings Supplier Types Update",
     *     tags={"SETUP - Supplier Types"},
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
    public function update(Request $request, SupplierType $supplierType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('supplier_types', 'name')->ignore($supplierType->id)],
            'code' => ['nullable', 'string', 'max:80'],
        ]);

        $supplierType->update($validated);

        return response()->json([
            'message' => 'Supplier type updated.',
            'data' => $supplierType->fresh(),
        ]);
    }

    // Delete a supplier type.
    /**
     * @OA\Delete(
     *     path="/settings/supplier-types/{supplierType}",
     *     summary="Api Settings Supplier Types Destroy",
     *     tags={"SETUP - Supplier Types"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(SupplierType $supplierType): JsonResponse
    {
        $supplierType->delete();

        return response()->json([
            'message' => 'Supplier type deleted.',
        ]);
    }
}
