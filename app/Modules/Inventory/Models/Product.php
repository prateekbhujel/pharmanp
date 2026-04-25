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
        'product_code',
        'name',
        'generic_name',
        'composition',
        'group_name',
        'formulation',
        'strength',
        'manufacturer_name',
        'conversion',
        'rack_location',
        'previous_price',
        'mrp',
        'purchase_price',
        'selling_price',
        'cc_rate',
        'discount_percent',
        'reorder_level',
        'reorder_quantity',
        'is_batch_tracked',
        'is_active',
        'notes',
        'keywords',
        'description',
        'image_path',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'mrp' => 'decimal:2',
            'previous_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'cc_rate' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'conversion' => 'decimal:3',
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
