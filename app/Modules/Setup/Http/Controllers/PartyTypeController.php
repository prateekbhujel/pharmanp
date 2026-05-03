<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\Setup\Models\PartyType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class PartyTypeController extends ModularController
{
    // Return all party types.
    /**
     * @OA\Get(
     *     path="/settings/party-types",
     *     summary="Api Settings Party Types Index",
     *     tags={"SETUP - Party Types"},
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
            'data' => PartyType::query()->orderBy('name')->get(),
        ]);
    }

    // Create a new party type.
    /**
     * @OA\Post(
     *     path="/settings/party-types",
     *     summary="Api Settings Party Types Store",
     *     tags={"SETUP - Party Types"},
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
    /**
     * @OA\Put(
     *     path="/settings/party-types/{partyType}",
     *     summary="Api Settings Party Types Update",
     *     tags={"SETUP - Party Types"},
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
    /**
     * @OA\Delete(
     *     path="/settings/party-types/{partyType}",
     *     summary="Api Settings Party Types Destroy",
     *     tags={"SETUP - Party Types"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(PartyType $partyType): JsonResponse
    {
        $partyType->delete();

        return response()->json([
            'message' => 'Party type deleted.',
        ]);
    }
}
