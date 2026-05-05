<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Http\Controllers\ModularController;
use App\Modules\Setup\DTOs\DropdownOptionData;
use App\Modules\Setup\Http\Requests\DropdownOptionRequest;
use App\Modules\Setup\Http\Requests\DropdownOptionStatusRequest;
use App\Modules\Setup\Http\Resources\DropdownOptionResource;
use App\Modules\Setup\Models\DropdownOption;
use App\Modules\Setup\Services\DropdownOptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class DropdownOptionController extends ModularController
{
    public function __construct(private readonly DropdownOptionService $options) {}

    // Return all managed dropdown options grouped by alias.
    /**
     * @OA\Get(
     *     path="/settings/dropdown-options",
     *     summary="Api Settings Dropdown Options Index",
     *     tags={"SETUP - Dropdown Options"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $options = $this->options->managed($request->query('alias'));

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'Dropdown options retrieved successfully.',
            'data' => DropdownOptionResource::collection($options)->resolve($request),
            'aliases' => $this->options->aliases(),
        ]);
    }

    // Create a new dropdown option row.
    /**
     * @OA\Post(
     *     path="/settings/dropdown-options",
     *     summary="Api Settings Dropdown Options Store",
     *     tags={"SETUP - Dropdown Options"},
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
    public function store(DropdownOptionRequest $request): JsonResponse
    {
        $option = $this->options->create(
            DropdownOptionData::fromRequest($request),
            $request->file('qr_file'),
        );

        return $this->resource(new DropdownOptionResource($option), $option->alias_label.' saved successfully.', 201);
    }

    // Update one shared dropdown option row.
    /**
     * @OA\Put(
     *     path="/settings/dropdown-options/{dropdownOption}",
     *     summary="Api Settings Dropdown Options Update",
     *     tags={"SETUP - Dropdown Options"},
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
    public function update(DropdownOptionRequest $request, DropdownOption $dropdownOption): JsonResponse
    {
        $option = $this->options->update(
            $dropdownOption,
            DropdownOptionData::fromRequest($request),
            $request->file('qr_file'),
        );

        return $this->resource(new DropdownOptionResource($option), $option->alias_label.' updated successfully.');
    }

    /**
     * @OA\Patch(
     *     path="/settings/dropdown-options/{dropdownOption}/status",
     *     summary="Api Settings Dropdown Options Status",
     *     tags={"SETUP - Dropdown Options"},
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
    public function toggleStatus(DropdownOptionStatusRequest $request, DropdownOption $dropdownOption): JsonResponse
    {
        $option = $this->options->updateStatus($dropdownOption, (bool) $request->validated('is_active'));

        return $this->success(
            (new DropdownOptionResource($option))->resolve($request),
            $option->alias_label.' status updated successfully.'
        );
    }

    // Delete only when the option is not already linked anywhere.
    /**
     * @OA\Delete(
     *     path="/settings/dropdown-options/{dropdownOption}",
     *     summary="Api Settings Dropdown Options Destroy",
     *     tags={"SETUP - Dropdown Options"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(DropdownOption $dropdownOption): JsonResponse
    {
        $this->options->delete($dropdownOption);

        return $this->success(null, 'Option deleted successfully.');
    }
}
