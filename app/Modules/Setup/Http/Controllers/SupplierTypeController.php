<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Modules\Setup\Models\SupplierType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierTypeController
{
    // Return all supplier types.
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SupplierType::query()->orderBy('name')->get(),
        ]);
    }

    // Create a new supplier type.
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
    public function destroy(SupplierType $supplierType): JsonResponse
    {
        $supplierType->delete();

        return response()->json([
            'message' => 'Supplier type deleted.',
        ]);
    }
}
