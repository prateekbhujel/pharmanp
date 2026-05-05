<?php

namespace App\Core\Traits;

use App\Modules\Setup\Models\FiscalYear;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait HasFiscalYear
{
    public static function bootHasFiscalYear(): void
    {
        static::creating(function (Model $model) {
            if (! $model->fiscal_year_id && auth()->check() && auth()->user()->company_id) {
                // Find the current active fiscal year for the company
                $fiscalYear = FiscalYear::query()
                    ->where('company_id', auth()->user()->company_id)
                    ->where('is_current', true)
                    ->first();

                if ($fiscalYear) {
                    $model->fiscal_year_id = $fiscalYear->id;
                }
            }
        });

        static::addGlobalScope('fiscal_year', function ($builder) {
            if (auth()->check() && auth()->user()->company_id) {
                // We cache the current fiscal year id in a static variable to avoid repeated queries
                static $currentFiscalYearId = null;
                
                if ($currentFiscalYearId === null) {
                    $currentFiscalYearId = FiscalYear::query()
                        ->where('company_id', auth()->user()->company_id)
                        ->where('is_current', true)
                        ->value('id') ?: false;
                }

                if ($currentFiscalYearId) {
                    $builder->where($builder->getQuery()->from . '.fiscal_year_id', $currentFiscalYearId);
                }
            }
        });
    }

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }
}
