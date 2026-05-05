<?php

namespace App\Modules\Inventory\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;



use App\Modules\Party\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model
{
    use BelongsToTenant, SoftDeletes;


    protected $fillable = [
        'tenant_id',
        'company_id',
        'store_id',
        'product_id',
        'supplier_id',
        'purchase_id',
        'batch_no',
        'barcode',
        'storage_location',
        'manufactured_at',
        'expires_at',
        'quantity_received',
        'quantity_available',
        'purchase_price',
        'mrp',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
return [
            'manufactured_at' => 'date',
            'expires_at' => 'date',
            'quantity_received' => 'decimal:3',
            'quantity_available' => 'decimal:3',
            'purchase_price' => 'decimal:2',
            'mrp' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
return $this->belongsTo(Supplier::class);
    }
}
