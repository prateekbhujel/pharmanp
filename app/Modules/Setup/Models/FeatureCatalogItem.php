<?php

namespace App\Modules\Setup\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureCatalogItem extends Model
{
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
