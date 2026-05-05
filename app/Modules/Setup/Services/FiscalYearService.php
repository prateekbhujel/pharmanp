<?php

namespace App\Modules\Setup\Services;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Setup\Models\FiscalYear;
use App\Modules\Setup\Repositories\Interfaces\FiscalYearRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FiscalYearService
{
    public function __construct(private readonly FiscalYearRepositoryInterface $fiscalYears) {}

    public function table(TableQueryData $table, User $user): LengthAwarePaginator
    {
        return $this->fiscalYears->paginate($table, $user);
    }

    public function save(FiscalYear $fiscalYear, array $data, User $user): FiscalYear
    {
        return DB::transaction(function () use ($fiscalYear, $data, $user): FiscalYear {
            if (! empty($data['is_current'])) {
                $this->fiscalYears->clearCurrent($user, $fiscalYear);
            }

            return $this->fiscalYears->save($fiscalYear, $data, $user);
        });
    }

    public function delete(FiscalYear $fiscalYear, User $user): void
    {
        $this->ensureOwnedRecord($fiscalYear, $user);

        DB::transaction(function () use ($fiscalYear, $user): void {
            $companyId = (int) $fiscalYear->company_id;
            $wasCurrent = (bool) $fiscalYear->is_current;

            $this->fiscalYears->delete($fiscalYear);

            if (! $wasCurrent) {
                return;
            }

            $replacement = $this->fiscalYears->replacement($companyId);

            if ($replacement) {
                $this->fiscalYears->clearCurrent($user);
                $this->fiscalYears->markCurrent($replacement);
            }
        });
    }

    public function ensureOwnedRecord(FiscalYear $fiscalYear, User $user): void
    {
        abort_unless((int) $fiscalYear->company_id === (int) $user->company_id, 404);
    }
}
