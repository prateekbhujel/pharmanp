<?php

namespace App\Modules\Purchase\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    use BelongsToTenant, HasFiscalYear;

    protected $fillable = [
        'purchase_return_id',
        'purchase_item_id',
        'batch_id',
        'product_id',
        'return_qty',
        'rate',
        'discount_percent',
        'discount_amount',
        'net_rate',
        'return_amount',
    ];

    protected function casts(): array
    {

        return [
            'return_qty' => 'decimal:3',
            'rate' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'net_rate' => 'decimal:2',
            'return_amount' => 'decimal:2',
        ];
    }

    public function purchaseReturn(): BelongsTo
    {

        return $this->belongsTo(PurchaseReturn::class);
    }

    public function purchaseItem(): BelongsTo
    {

        return $this->belongsTo(PurchaseItem::class);
    }

    public function batch(): BelongsTo
    {

        return $this->belongsTo(Batch::class);
    }

    public function product(): BelongsTo
    {

        return $this->belongsTo(Product::class);
    }
}
