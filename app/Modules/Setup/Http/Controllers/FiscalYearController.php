<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\Setup\Http\Requests\FiscalYearRequest;
use App\Modules\Setup\Http\Resources\FiscalYearResource;
use App\Modules\Setup\Models\FiscalYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class FiscalYearController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/settings/fiscal-years",
     *     summary="Api Fiscal Years Index",
     *     tags={"SETUP - Fiscal Years"},
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
        $this->authorizeRequest();

        $sorts = [
            'name' => 'name',
            'starts_on' => 'starts_on',
            'ends_on' => 'ends_on',
            'status' => 'status',
            'created_at' => 'created_at',
        ];

        $sortField = $sorts[request('sort_field', 'starts_on')] ?? 'starts_on';
        $sortOrder = request('sort_order') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) request('search'));
        $user = request()->user();

        $query = FiscalYear::query()
            ->where('company_id', $user->company_id)
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($sortField, $sortOrder)
            ->orderByDesc('id')
            ->paginate(min(100, max(5, request()->integer('per_page', 15))));

        return response()->json(FiscalYearResource::collection($query)->response()->getData(true));
    }

    /**
     * @OA\Post(
     *     path="/settings/fiscal-years",
     *     summary="Api Fiscal Years Store",
     *     tags={"SETUP - Fiscal Years"},
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
    public function store(FiscalYearRequest $request): JsonResponse
    {
        $fiscalYear = DB::transaction(function () use ($request) {
            return $this->persist(new FiscalYear, $request->validated(), $request->user());
        });

        return (new FiscalYearResource($fiscalYear))
            ->additional(['message' => 'Fiscal year created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/settings/fiscal-years/{fiscal_year}",
     *     summary="Api Fiscal Years Update",
     *     tags={"SETUP - Fiscal Years"},
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
    public function update(FiscalYearRequest $request, FiscalYear $fiscalYear): FiscalYearResource
    {
        $this->ensureOwnedRecord($fiscalYear);

        $fiscalYear = DB::transaction(function () use ($request, $fiscalYear) {
            return $this->persist($fiscalYear, $request->validated(), $request->user());
        });

        return new FiscalYearResource($fiscalYear);
    }

    /**
     * @OA\Delete(
     *     path="/settings/fiscal-years/{fiscal_year}",
     *     summary="Api Fiscal Years Destroy",
     *     tags={"SETUP - Fiscal Years"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(FiscalYear $fiscalYear): JsonResponse
    {
        $this->authorizeRequest();
        $this->ensureOwnedRecord($fiscalYear);

        DB::transaction(function () use ($fiscalYear) {
            $companyId = $fiscalYear->company_id;
            $wasCurrent = (bool) $fiscalYear->is_current;

            $fiscalYear->delete();

            if ($wasCurrent) {
                $replacement = FiscalYear::query()
                    ->where('company_id', $companyId)
                    ->where('status', 'open')
                    ->latest('starts_on')
                    ->first();

                if ($replacement) {
                    FiscalYear::query()
                        ->where('company_id', $companyId)
                        ->update(['is_current' => false]);

                    $replacement->forceFill(['is_current' => true])->save();
                }
            }
        });

        return response()->json(['message' => 'Fiscal year deleted.']);
    }

    private function persist(FiscalYear $fiscalYear, array $data, $user): FiscalYear
    {
        if (! empty($data['is_current'])) {
            $query = FiscalYear::query()->where('company_id', $user->company_id);

            if ($fiscalYear->exists) {
                $query->whereKeyNot($fiscalYear->id);
            }

            $query->update(['is_current' => false]);
        }

        $status = $data['status'];
        $closedAt = $status === 'closed' ? ($fiscalYear->closed_at ?? now()) : null;

        $fiscalYear->fill([
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'name' => $data['name'],
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'is_current' => (bool) ($data['is_current'] ?? false),
            'status' => $status,
            'closed_at' => $closedAt,
            'updated_by' => $user->id,
        ]);

        if (! $fiscalYear->exists) {
            $fiscalYear->created_by = $user->id;
        }

        $fiscalYear->save();

        return $fiscalYear->fresh();
    }

    private function authorizeRequest(): void
    {
        abort_unless(request()->user()?->is_owner || request()->user()?->can('settings.manage'), 403);
    }

    private function ensureOwnedRecord(FiscalYear $fiscalYear): void
    {
        abort_unless((int) $fiscalYear->company_id === (int) request()->user()?->company_id, 404);
    }
}
