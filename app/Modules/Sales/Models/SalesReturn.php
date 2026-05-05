<?php

namespace App\Modules\Sales\Models;

use App\Modules\Party\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Core\Traits\HasFiscalYear;

class SalesReturn extends Model
{
    use SoftDeletes, HasFiscalYear;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'store_id',
        'fiscal_year_id',
        'sales_invoice_id',
        'customer_id',
        'return_no',
        'return_type',
        'return_date',
        'total_amount',
        'reason',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'sales_invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
