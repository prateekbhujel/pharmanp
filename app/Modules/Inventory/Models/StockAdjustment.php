<?php

namespace App\Modules\Inventory\Models;

use App\Core\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'store_id',
        'adjustment_date',
        'product_id',
        'batch_id',
        'adjustment_type',
        'quantity',
        'reason',
        'adjusted_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'quantity' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
