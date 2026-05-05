<?php

namespace App\Modules\Accounting\Models;

use App\Models\User;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Core\Traits\HasFiscalYear;

class Payment extends Model
{
    use SoftDeletes, HasFiscalYear;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'store_id',
        'fiscal_year_id',
        'payment_no',
        'payment_date',
        'direction',
        'party_type',
        'party_id',
        'payment_mode_id',
        'payment_mode',
        'amount',
        'reference_no',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'party_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'party_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentModeOption(): BelongsTo
    {
        return $this->belongsTo(DropdownOption::class, 'payment_mode_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentBillAllocation::class);
    }

    // Return the display name of the linked party.
    public function getPartyNameAttribute(): string
    {
        if ($this->party_type === 'customer') {
            return $this->customer?->name ?? '-';
        }

        if ($this->party_type === 'supplier') {
            return $this->supplier?->name ?? '-';
        }

        return '-';
    }

    public function getPaymentModeLabelAttribute(): string
    {
        return $this->paymentModeOption?->name ?? ucfirst(str_replace('_', ' ', (string) $this->payment_mode));
    }
}
