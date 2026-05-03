<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Support\AssetUrl;
use App\Http\Controllers\ModularController;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class DropdownOptionController extends ModularController
{
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
        $aliases = DropdownOption::managedAliases();

        $query = DropdownOption::query()
            ->whereIn('alias', array_keys($aliases))
            ->orderBy('alias')
            ->orderBy('name');

        if ($request->filled('alias')) {
            $query->where('alias', $request->input('alias'));
        }

        $options = $query->get();

        return response()->json([
            'data' => $options->map(fn (DropdownOption $option) => $this->rowPayload($option))->values(),
            'aliases' => $aliases,
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
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);

        $option = DropdownOption::query()->create([
            'alias' => $validated['alias'],
            'name' => trim($validated['name']),
            'data' => $this->cleanDataValue($validated['data'] ?? null),
            'meta' => $this->metaPayload($request, $validated['meta'] ?? []),
            'status' => (int) ($validated['status'] ?? 1),
        ]);

        return response()->json([
            'message' => $option->alias_label.' saved successfully.',
            'data' => $this->rowPayload($option),
        ]);
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
    public function update(Request $request, DropdownOption $dropdownOption): JsonResponse
    {
        $validated = $this->validatedPayload($request, $dropdownOption->id);

        $dropdownOption->update([
            'alias' => $validated['alias'],
            'name' => trim($validated['name']),
            'data' => $this->cleanDataValue($validated['data'] ?? null),
            'meta' => $this->metaPayload($request, $validated['meta'] ?? ($dropdownOption->meta ?? []), $dropdownOption),
            'status' => (int) ($validated['status'] ?? $dropdownOption->status),
        ]);

        return response()->json([
            'message' => $dropdownOption->alias_label.' updated successfully.',
            'data' => $this->rowPayload($dropdownOption->fresh()),
        ]);
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
    public function toggleStatus(Request $request, DropdownOption $dropdownOption): JsonResponse
    {
        abort_unless((bool) $request->user()?->is_owner || (bool) $request->user()?->can('settings.manage'), 403);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $dropdownOption->update([
            'status' => (int) $validated['is_active'],
        ]);

        return response()->json([
            'message' => $dropdownOption->alias_label.' status updated successfully.',
            'data' => $this->rowPayload($dropdownOption->fresh()),
        ]);
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
        $linked = $this->linkedUsageCount($dropdownOption);

        if ($linked > 0) {
            return response()->json([
                'message' => 'This option is already in use and cannot be deleted.',
            ], 422);
        }

        $dropdownOption->delete();

        return response()->json([
            'message' => 'Option deleted successfully.',
        ]);
    }

    private function validatedPayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'alias' => ['required', Rule::in(array_keys(DropdownOption::managedAliases()))],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('dropdown_options', 'name')
                    ->where(fn ($query) => $query->where('alias', $request->input('alias')))
                    ->ignore($ignoreId),
            ],
            'data' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
            'meta.instructions' => ['nullable', 'string', 'max:1000'],
            'qr_file' => ['nullable', 'image', 'max:2048'],
            'status' => ['nullable', 'boolean'],
        ]);
    }

    private function rowPayload(DropdownOption $option): array
    {
        return [
            'id' => $option->id,
            'alias' => $option->alias,
            'alias_label' => $option->alias_label,
            'name' => $option->name,
            'data' => $option->data,
            'meta' => $this->resolvedMeta($option),
            'status' => (int) $option->status,
            'is_active' => (bool) $option->status,
        ];
    }

    private function cleanDataValue(?string $value): ?string
    {
        return filled($value) ? trim($value) : null;
    }

    private function metaPayload(Request $request, array $meta, ?DropdownOption $option = null): array
    {
        $payload = Arr::where($meta, fn ($value) => filled($value));

        if ($option?->meta && ! $request->has('meta')) {
            $payload = $option->meta;
        }

        if ($request->hasFile('qr_file')) {
            $payload['qr_url'] = $this->storeQrAsset($request->file('qr_file'));
        } elseif ($option?->meta && isset($option->meta['qr_url']) && ! array_key_exists('qr_url', $payload)) {
            $payload['qr_url'] = $option->meta['qr_url'];
        }

        return $payload;
    }

    private function resolvedMeta(DropdownOption $option): array
    {
        $meta = $option->meta ?? [];
        if (! empty($meta['qr_url'])) {
            $meta['qr_url'] = AssetUrl::resolve($meta['qr_url']);
        }

        return $meta;
    }

    private function storeQrAsset(UploadedFile $file): string
    {
        return AssetUrl::publicStorage($file->store('settings/payment-modes', 'public'));
    }

    private function linkedUsageCount(DropdownOption $option): int
    {
        return match ($option->alias) {
            'expense_category' => Expense::query()->where('expense_category_id', $option->id)->count(),
            'payment_mode' => Expense::query()->where('payment_mode_id', $option->id)->count()
                + Payment::query()->where('payment_mode_id', $option->id)->count(),
            default => 0,
        };
    }
}
