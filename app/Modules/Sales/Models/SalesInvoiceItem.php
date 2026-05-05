<?php

namespace App\Modules\Sales\Models;

use App\Core\Traits\BelongsToTenant;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'sales_invoice_id',
        'product_id',
        'batch_id',
        'quantity',
        'free_quantity',
        'mrp',
        'unit_price',
        'cc_rate',
        'discount_percent',
        'discount_amount',
        'free_goods_value',
        'line_total',
    ];

    protected function casts(): array
    {

        return [
            'quantity' => 'decimal:3',
            'free_quantity' => 'decimal:3',
            'mrp' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'cc_rate' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'free_goods_value' => 'decimal:2',
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
