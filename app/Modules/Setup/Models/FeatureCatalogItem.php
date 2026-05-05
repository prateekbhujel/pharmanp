<?php

namespace App\Modules\Setup\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FeatureCatalogItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'module',
        'code',
        'name',
        'description',
        'status',
        'is_billable',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_billable' => 'boolean',
        ];
    }
}
