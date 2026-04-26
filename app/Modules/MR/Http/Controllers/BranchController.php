<?php

namespace App\Modules\MR\Http\Controllers;

use App\Modules\MR\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController
{
    // Return flat list with optional hierarchy info, used for selects and management.
    public function index(): JsonResponse
    {
        $branches = Branch::query()
            ->orderByRaw("CASE WHEN type = 'hq' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $b) => $this->rowPayload($b));

        return response()->json(['data' => $branches]);
    }

    // Create a new branch.
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedPayload($request);

        $branch = Branch::query()->create($validated);

        return response()->json([
            'message' => "Branch '{$branch->name}' created.",
            'data'    => $this->rowPayload($branch),
        ], 201);
    }

    // Update an existing branch.
    public function update(Request $request, Branch $branch): JsonResponse
    {
        $validated = $this->validatedPayload($request, $branch->id);

        $branch->update($validated);

        return response()->json([
            'message' => "Branch '{$branch->name}' updated.",
            'data'    => $this->rowPayload($branch->fresh()),
        ]);
    }

    // Delete a branch only when it has no assigned MRs.
    public function destroy(Branch $branch): JsonResponse
    {
        if ($branch->medicalRepresentatives()->exists()) {
            return response()->json([
                'message' => 'This branch has assigned MRs. Reassign them first.',
            ], 422);
        }

        $branch->delete();

        return response()->json(['message' => 'Branch deleted.']);
    }

    // Lightweight options list for Select dropdowns.
    public function options(): JsonResponse
    {
        $options = Branch::query()
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN type = 'hq' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type'])
            ->map(fn ($b) => [
                'id'    => $b->id,
                'name'  => $b->name . ($b->type === 'hq' ? ' (HQ)' : ''),
                'code'  => $b->code,
                'type'  => $b->type,
            ]);

        return response()->json(['data' => $options]);
    }

    private function validatedPayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['nullable', 'string', 'max:40'],
            'type'      => ['required', \Illuminate\Validation\Rule::in(['hq', 'branch'])],
            'parent_id' => ['nullable', 'integer', 'exists:branches,id'],
            'address'   => ['nullable', 'string', 'max:500'],
            'phone'     => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function rowPayload(Branch $branch): array
    {
        return [
            'id'        => $branch->id,
            'name'      => $branch->name,
            'code'      => $branch->code,
            'type'      => $branch->type,
            'parent_id' => $branch->parent_id,
            'address'   => $branch->address,
            'phone'     => $branch->phone,
            'is_active' => (bool) $branch->is_active,
            'is_hq'     => $branch->is_hq,
        ];
    }
}
