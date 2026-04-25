<?php

namespace App\Modules\Purchase\Models;

use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id',
        'product_id',
        'batch_id',
        'batch_no',
        'manufactured_at',
        'expires_at',
        'quantity',
        'free_quantity',
        'purchase_price',
        'mrp',
        'discount_percent',
        'discount_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'manufactured_at' => 'date',
            'expires_at' => 'date',
            'quantity' => 'decimal:3',
            'free_quantity' => 'decimal:3',
            'purchase_price' => 'decimal:2',
            'mrp' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
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
}
