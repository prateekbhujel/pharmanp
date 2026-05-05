<?php

namespace App\Modules\Setup\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;



use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PartyType extends Model
{
    use BelongsToTenant;


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
