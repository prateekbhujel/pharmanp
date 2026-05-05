<?php

namespace App\Modules\Accounting\Services;

use App\Core\DTOs\TableQueryData;
use App\Core\Security\TenantRecordScope;
use App\Core\Support\ApiResponse;
use App\Models\User;
use App\Modules\Accounting\Models\Expense;
use App\Modules\Accounting\Repositories\Interfaces\ExpenseRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private readonly TenantRecordScope $records,
    ) {}

    public function table(TableQueryData $table, ?User $user = null): array
    {
        $page = $this->expenses->paginate($table, $user);

        return [
            'data' => $page->getCollection()->map(fn (Expense $expense) => $this->payload($expense))->values(),
            'meta' => ApiResponse::paginationMeta($page),
            'summary' => $this->expenses->summary($user),
            'lookups' => $this->expenses->lookups(),
        ];
    }

    public function save(array $data, User $user): Expense
    {
        return DB::transaction(function () use ($data, $user) {
            $category = $this->expenses->category((int) $data['expense_category_id']);
            $paymentMode = $this->expenses->paymentMode((int) $data['payment_mode_id']);
            $expense = $this->expenses->findForUpdate($data['id'] ?? null);

            if ($expense->exists) {
                $this->assertAccessible($expense, $user);
                $this->expenses->deleteTransactions($expense);
            }

            $expense = $this->expenses->save($expense, [
                'tenant_id' => $expense->tenant_id ?: $user->tenant_id,
                'company_id' => $expense->company_id ?: $user->company_id,
                'expense_date' => $data['expense_date'],
                'expense_category_id' => $category->id,
                'category' => $category->name,
                'vendor_name' => $data['vendor_name'] ?? null,
                'payment_mode_id' => $paymentMode->id,
                'payment_mode' => $paymentMode->data ?: strtolower($paymentMode->name),
                'amount' => round((float) $data['amount'], 2),
                'notes' => $data['notes'] ?? null,
                'created_by' => $expense->exists ? $expense->created_by : $user->id,
                'updated_by' => $user->id,
            ]);

            $this->postAccounting($expense, $paymentMode->name, $paymentMode->data ?: strtolower($paymentMode->name), $user);

            return $expense->fresh(['expenseCategory', 'paymentModeOption', 'creator']);
        });
    }

    public function delete(Expense $expense, User $user): void
    {
        $this->assertAccessible($expense, $user);

        DB::transaction(function () use ($expense): void {
            $this->expenses->deleteTransactions($expense);
            $this->expenses->delete($expense);
        });
    }

    public function assertAccessible(Expense $expense, User $user): void
    {
        if (! $this->records->canAccess($user, $expense, ['store' => null])) {
            abort(404);
        }
    }

    public function payload(Expense $expense): array
    {
        return [
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
        ];
    }

    private function postAccounting(Expense $expense, string $paymentModeName, string $paymentModeData, User $user): void
    {
        $base = [
            'tenant_id' => $user->tenant_id,
            'company_id' => $user->company_id,
            'transaction_date' => $expense->expense_date,
            'source_type' => 'Expense',
            'source_id' => $expense->id,
            'created_by' => $user->id,
        ];

        $this->expenses->createTransaction([
            ...$base,
            'account_type' => 'expense',
            'debit' => $expense->amount,
            'credit' => 0,
            'notes' => 'Expense posted under '.$expense->category,
        ]);

        $this->expenses->createTransaction([
            ...$base,
            'account_type' => $paymentModeData === 'cash' ? 'cash' : 'bank',
            'debit' => 0,
            'credit' => $expense->amount,
            'notes' => 'Expense payment by '.$paymentModeName,
        ]);
    }
}
