<?php

namespace App\Modules\Purchase\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;
use App\Modules\Party\Models\Supplier;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use BelongsToTenant, HasFiscalYear, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'store_id',
        'fiscal_year_id',
        'supplier_id',
        'purchase_no',
        'supplier_invoice_no',
        'purchase_date',
        'due_date',
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
            'purchase_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {

        return $this->belongsTo(Supplier::class);
    }

    public function paymentMode(): BelongsTo
    {

        return $this->belongsTo(DropdownOption::class, 'payment_mode_id');
    }

    public function items(): HasMany
    {

        return $this->hasMany(PurchaseItem::class);
    }
}
