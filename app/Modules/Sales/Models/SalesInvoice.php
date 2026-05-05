<?php

namespace App\Modules\Sales\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\Party\Models\Customer;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesInvoice extends Model
{
    use BelongsToTenant, HasFiscalYear, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'store_id',
        'branch_id',
        'fiscal_year_id',
        'customer_id',
        'medical_representative_id',
        'invoice_no',
        'invoice_date',
        'due_date',
        'sale_type',
        'status',
        'payment_status',
        'payment_mode_id',
        'payment_type',
        'subtotal',
        'discount_total',
        'grand_total',
        'paid_amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function medicalRepresentative(): BelongsTo
    {
        return $this->belongsTo(MedicalRepresentative::class, 'medical_representative_id');
    }

    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(DropdownOption::class, 'payment_mode_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SalesReturn::class);
    }
}
