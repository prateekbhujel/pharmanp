<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Core\Support\ApiResponse;
use App\Http\Controllers\ModularController;
use App\Modules\Accounting\Models\AccountTransaction;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="ACCOUNTING - Finance",
 *     description="API endpoints for ACCOUNTING - Finance"
 * )
 */
class ExpenseController extends ModularController
{
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
        $query = Expense::query()
            ->with(['expenseCategory', 'paymentModeOption', 'creator'])
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('tenant_id', $tenantId))
            ->latest('expense_date')
            ->latest('id');

        if ($request->filled('search')) {
            $keyword = $request->input('search');
            $query->where(function ($builder) use ($keyword) {
                $builder->where('category', 'like', '%'.$keyword.'%')
                    ->orWhere('vendor_name', 'like', '%'.$keyword.'%')
                    ->orWhere('notes', 'like', '%'.$keyword.'%')
                    ->orWhereHas('expenseCategory', fn ($q) => $q->where('name', 'like', '%'.$keyword.'%'));
            });
        }

        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->input('expense_category_id'));
        }

        if ($request->filled('payment_mode_id')) {
            $query->where('payment_mode_id', $request->input('payment_mode_id'));
        }

        if ($request->filled('from')) {
            $query->where('expense_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('expense_date', '<=', $request->input('to'));
        }

        $perPage = TableQueryData::perPageFromRequest($request);
        $paginated = $query->paginate($perPage);

        // Summary for all expenses (unfiltered).
        $cashModeIds = DropdownOption::query()->forAlias('payment_mode')->where('data', 'cash')->pluck('id');
        $bankModeIds = DropdownOption::query()->forAlias('payment_mode')->where('data', 'bank')->pluck('id');
        $allExpenses = Expense::query()
            ->when($request->user()?->tenant_id, fn ($builder, $tenantId) => $builder->where('tenant_id', $tenantId));

        $summary = [
            'this_month' => (clone $allExpenses)
                ->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount'),
            'cash' => $cashModeIds->isNotEmpty()
                ? (clone $allExpenses)->whereIn('payment_mode_id', $cashModeIds)->sum('amount')
                : (clone $allExpenses)->where('payment_mode', 'cash')->sum('amount'),
            'bank' => $bankModeIds->isNotEmpty()
                ? (clone $allExpenses)->whereIn('payment_mode_id', $bankModeIds)->sum('amount')
                : (clone $allExpenses)->where('payment_mode', 'bank')->sum('amount'),
            'total' => (clone $allExpenses)->sum('amount'),
        ];

        return response()->json([
            'data' => $paginated->getCollection()->map(fn (Expense $expense) => [
                'id' => $expense->id,
                'expense_date' => $expense->expense_date->format('Y-m-d'),
                'expense_date_display' => $expense->expense_date->format('M j, Y'),
                'category' => $expense->expense_category_label,
                'expense_category_id' => $expense->expense_category_id,
                'vendor_name' => $expense->vendor_name,
                'payment_mode' => $expense->payment_mode_label,
                'payment_mode_id' => $expense->payment_mode_id,
                'payment_mode_data' => $expense->paymentModeOption?->data,
                'amount' => round((float) $expense->amount, 2),
                'notes' => $expense->notes,
                'created_by' => $expense->creator?->name ?? '-',
            ]),
            'meta' => ApiResponse::paginationMeta($paginated),
            'summary' => $summary,
            'lookups' => [
                'expense_categories' => DropdownOption::query()->forAlias('expense_category')->active()->orderBy('name')->get(['id', 'name']),
                'payment_modes' => DropdownOption::query()->forAlias('payment_mode')->active()->orderBy('name')->get(['id', 'name', 'data']),
            ],
        ]);
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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:expenses,id'],
            'expense_date' => ['required', 'date'],
            'expense_category_id' => ['required', 'integer', Rule::exists('dropdown_options', 'id')->where(fn ($query) => $query->where('alias', 'expense_category'))],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'payment_mode_id' => ['required', 'integer', Rule::exists('dropdown_options', 'id')->where(fn ($query) => $query->where('alias', 'payment_mode'))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        $expense = DB::transaction(function () use ($validated, $request) {
            $expenseCategory = DropdownOption::query()->forAlias('expense_category')->findOrFail($validated['expense_category_id']);
            $paymentMode = DropdownOption::query()->forAlias('payment_mode')->findOrFail($validated['payment_mode_id']);

            $expense = ! empty($validated['id'])
                ? Expense::query()->findOrFail($validated['id'])
                : new Expense;

            if ($expense->exists) {
                AccountTransaction::query()
                    ->where('source_type', 'Expense')
                    ->where('source_id', $expense->id)
                    ->delete();
            }

            $expense->fill([
                'tenant_id' => $request->user()->tenant_id ?? null,
                'company_id' => $request->user()->company_id ?? null,
                'expense_date' => $validated['expense_date'],
                'expense_category_id' => $expenseCategory->id,
                'category' => $expenseCategory->name,
                'vendor_name' => $validated['vendor_name'] ?? null,
                'payment_mode_id' => $paymentMode->id,
                'payment_mode' => $paymentMode->data ?: strtolower($paymentMode->name),
                'amount' => round((float) $validated['amount'], 2),
                'notes' => $validated['notes'] ?? null,
                'created_by' => $expense->exists ? $expense->created_by : $request->user()->id,
            ]);

            $expense->save();

            // Debit expense account.
            AccountTransaction::query()->create([
                'tenant_id' => $request->user()->tenant_id ?? null,
                'company_id' => $request->user()->company_id ?? null,
                'transaction_date' => $expense->expense_date,
                'source_type' => 'Expense',
                'source_id' => $expense->id,
                'account_type' => 'expense',
                'debit' => $expense->amount,
                'credit' => 0,
                'notes' => 'Expense posted under '.$expense->category,
                'created_by' => $request->user()->id,
            ]);

            // Credit cash or bank account.
            AccountTransaction::query()->create([
                'tenant_id' => $request->user()->tenant_id ?? null,
                'company_id' => $request->user()->company_id ?? null,
                'transaction_date' => $expense->expense_date,
                'source_type' => 'Expense',
                'source_id' => $expense->id,
                'account_type' => ($paymentMode->data === 'cash') ? 'cash' : 'bank',
                'debit' => 0,
                'credit' => $expense->amount,
                'notes' => 'Expense payment by '.$paymentMode->name,
                'created_by' => $request->user()->id,
            ]);

            return $expense;
        });

        return response()->json([
            'message' => empty($validated['id']) ? 'Expense added successfully.' : 'Expense updated successfully.',
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
    public function destroy(Expense $expense): JsonResponse
    {
        DB::transaction(function () use ($expense) {
            AccountTransaction::query()
                ->where('source_type', 'Expense')
                ->where('source_id', $expense->id)
                ->delete();

            $expense->delete();
        });

        return response()->json([
            'message' => 'Expense deleted successfully.',
        ]);
    }
}
