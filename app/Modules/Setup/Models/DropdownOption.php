<?php

namespace App\Modules\Setup\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DropdownOption extends Model
{
    protected $guarded = [];

    public const MANAGED_ALIASES = [
        'product_status' => [
            'label' => 'Product Status',
            'supports_data' => false,
        ],
        'sales_type' => [
            'label' => 'Sales Type',
            'supports_data' => false,
        ],
        'payment_mode' => [
            'label' => 'Payment Mode',
            'supports_data' => true,
        ],
        'payment_type' => [
            'label' => 'Payment Type',
            'supports_data' => false,
        ],
        'adjustment_type' => [
            'label' => 'Stock Adjustment Type',
            'supports_data' => true,
        ],
        'expense_category' => [
            'label' => 'Expense Category',
            'supports_data' => true,
        ],
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'status' => 'boolean',
        ];
    }

    // Most dropdowns only need active values, so this keeps controller code short.
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    // Alias is used everywhere, so this tiny scope avoids repeating raw where calls.
    public function scopeForAlias(Builder $query, string $alias): Builder
    {
        return $query->where('alias', $alias);
    }

    // Settings page and quick-add modals both use the same alias metadata.
    public static function managedAliases(): array
    {
        return static::MANAGED_ALIASES;
    }

    // Settings and quick-add both show a readable alias label without duplicating arrays.
    public function getAliasLabelAttribute(): string
    {
        return static::MANAGED_ALIASES[$this->alias]['label'] ?? ucwords(str_replace('_', ' ', $this->alias));
    }

    // Keep the API shape consistent with other status-aware resources.
    public function getIsActiveAttribute(): bool
    {
        return (bool) $this->status;
    }
}
