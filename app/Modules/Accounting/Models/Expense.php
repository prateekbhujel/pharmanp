<?php

namespace App\Modules\Accounting\Models;

use App\Core\Traits\BelongsToTenant;
use App\Models\User;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'expense_date',
        'expense_category_id',
        'category',
        'vendor_name',
        'payment_mode_id',
        'payment_mode',
        'amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(DropdownOption::class, 'expense_category_id');
    }

    public function paymentModeOption(): BelongsTo
    {
        return $this->belongsTo(DropdownOption::class, 'payment_mode_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Return the readable expense category label for display.
    public function getExpenseCategoryLabelAttribute(): string
    {
        return $this->expenseCategory?->name ?? $this->category ?? '-';
    }

    // Return the readable payment mode label for display.
    public function getPaymentModeLabelAttribute(): string
    {
        return $this->paymentModeOption?->name ?? ucfirst($this->payment_mode ?? '-');
    }
}
