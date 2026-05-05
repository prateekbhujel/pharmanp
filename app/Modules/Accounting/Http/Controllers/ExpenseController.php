<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Accounting\Http\Requests\ExpenseRequest;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="ACCOUNTING - Finance",
 *     description="API endpoints for ACCOUNTING - Finance"
 * )
 */
class ExpenseController extends ModularController
{
    public function __construct(private readonly ExpenseService $expenses) {}

    // Return paginated expenses with filters and summary stats.
    /**
     * @OA\Get(
     *     path="/accounting/expenses",
     *     summary="Api Accounting Expenses Index",
     *     tags={"ACCOUNTING - Expenses"},
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
        return response()->json($this->expenses->table(
            TableQueryData::fromRequest($request, ['expense_category_id', 'payment_mode_id', 'from', 'to']),
            $request->user(),
        ));
    }

    // Create or update one expense and keep accounting entries in sync.
    /**
     * @OA\Post(
     *     path="/accounting/expenses",
     *     summary="Api Accounting Expenses Store",
     *     tags={"ACCOUNTING - Expenses"},
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
    public function store(ExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenses->save($request->validated(), $request->user());

        return response()->json([
            'message' => empty($request->validated('id')) ? 'Expense added successfully.' : 'Expense updated successfully.',
            'data' => ['id' => $expense->id],
        ]);
    }

    // Delete one expense and remove its linked accounting entries.
    /**
     * @OA\Delete(
     *     path="/accounting/expenses/{expense}",
     *     summary="Api Accounting Expenses Destroy",
     *     tags={"ACCOUNTING - Expenses"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->expenses->delete($expense, $request->user());

        return response()->json([
            'message' => 'Expense deleted successfully.',
        ]);
    }
}
