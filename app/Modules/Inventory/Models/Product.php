<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'store_id',
        'category_id',
        'manufacturer_id',
        'unit_id',
        'sku',
        'barcode',
        'name',
        'generic_name',
        'composition',
        'formulation',
        'strength',
        'rack_location',
        'mrp',
        'purchase_price',
        'selling_price',
        'cc_rate',
        'reorder_level',
        'reorder_quantity',
        'is_batch_tracked',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'mrp' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'cc_rate' => 'decimal:2',
            'is_batch_tracked' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}
