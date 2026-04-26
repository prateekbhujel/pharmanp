<?php

namespace App\Modules\Setup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PartyType extends Model
{
    protected $guarded = [];

    // Keep the label nice for selects and tables.
    public function getDisplayNameAttribute(): string
    {
        return Str::headline((string) $this->name);
    }

    // Keep the code usable in old rows and dropdown values.
    public function getDisplayCodeAttribute(): string
    {
        return (string) $this->code;
    }
}
