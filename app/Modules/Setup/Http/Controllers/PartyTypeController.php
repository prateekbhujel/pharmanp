<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Modules\Setup\Models\PartyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartyTypeController
{
    // Return all party types.
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PartyType::query()->orderBy('name')->get(),
        ]);
    }

    // Create a new party type.
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('party_types', 'name')],
            'code' => ['nullable', 'string', 'max:80'],
        ]);

        $partyType = PartyType::query()->create($validated);

        return response()->json([
            'message' => 'Party type created.',
            'data' => $partyType,
        ]);
    }

    // Update a party type.
    public function update(Request $request, PartyType $partyType): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('party_types', 'name')->ignore($partyType->id)],
            'code' => ['nullable', 'string', 'max:80'],
        ]);

        $partyType->update($validated);

        return response()->json([
            'message' => 'Party type updated.',
            'data' => $partyType->fresh(),
        ]);
    }

    // Delete a party type.
    public function destroy(PartyType $partyType): JsonResponse
    {
        $partyType->delete();

        return response()->json([
            'message' => 'Party type deleted.',
        ]);
    }
}
