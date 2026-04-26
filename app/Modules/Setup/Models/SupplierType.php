<?php

namespace App\Modules\Setup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SupplierType extends Model
{
    protected $guarded = [];

    // Keep the supplier type label easy to read in selects and tables.
    public function getDisplayNameAttribute(): string
    {
        return Str::headline((string) $this->name);
    }

    // Keep the code stable for form values and imports.
    public function getDisplayCodeAttribute(): string
    {
        return (string) $this->code;
    }
}
