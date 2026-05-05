<?php

namespace App\Modules\Setup\Services;

use App\Core\DTOs\TableQueryData;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
use App\Modules\Setup\Models\FiscalYear;
use App\Modules\Setup\Repositories\Interfaces\FiscalYearRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalYearService
{
    public function __construct(private readonly FiscalYearRepositoryInterface $fiscalYears) {}

    public function table(TableQueryData $table, User $user): LengthAwarePaginator
    {
        return $this->fiscalYears->paginate($table, $this->resolveCompanyId($user));
    }

    public function save(FiscalYear $fiscalYear, array $data, User $user): FiscalYear
    {
        return DB::transaction(function () use ($fiscalYear, $data, $user): FiscalYear {
            $companyId = $this->resolveCompanyId($user);

            if (! empty($data['is_current'])) {
                $this->fiscalYears->clearCurrent($companyId, $fiscalYear);
            }

            return $this->fiscalYears->save($fiscalYear, $data, $user, $companyId);
        });
    }

    public function delete(FiscalYear $fiscalYear, User $user): void
    {
        $this->ensureOwnedRecord($fiscalYear, $user);

        DB::transaction(function () use ($fiscalYear): void {
            $companyId = (int) $fiscalYear->company_id;
            $wasCurrent = (bool) $fiscalYear->is_current;

            $this->fiscalYears->delete($fiscalYear);

            if (! $wasCurrent) {
                return;
            }

            $replacement = $this->fiscalYears->replacement($companyId);

            if ($replacement) {
                $this->fiscalYears->clearCurrent($companyId);
                $this->fiscalYears->markCurrent($replacement);
            }
        });
    }

    public function ensureOwnedRecord(FiscalYear $fiscalYear, User $user): void
    {
        abort_unless((int) $fiscalYear->company_id === $this->resolveCompanyId($user), 404);
    }

    private function resolveCompanyId(User $user): int
    {
        if ($user->company_id) {
            return (int) $user->company_id;
        }

        if (! $user->canAccessAllTenants() && ! $user->tenant_id) {
            throw ValidationException::withMessages([
                'company_id' => 'Set up a company before creating fiscal years.',
            ]);
        }

        $brandingCompanyId = (int) (Setting::getValue('app.branding', [])['company_id'] ?? 0);

        if ($brandingCompanyId && $this->companyIsAccessible($brandingCompanyId, $user)) {
            return $brandingCompanyId;
        }

        $query = Company::query()
            ->where('is_active', true)
            ->when(
                ! $user->canAccessAllTenants() && $user->tenant_id,
                fn ($builder) => $builder->where('tenant_id', $user->tenant_id)
            );

        $ownedCompany = (clone $query)
            ->where('created_by', $user->id)
            ->oldest('id')
            ->first();

        if ($ownedCompany) {
            return (int) $ownedCompany->id;
        }

        if ((clone $query)->count() === 1) {
            return (int) (clone $query)->value('id');
        }

        throw ValidationException::withMessages([
            'company_id' => 'Choose a company context before managing fiscal years.',
        ]);
    }

    private function companyIsAccessible(int $companyId, User $user): bool
    {
        return Company::query()
            ->whereKey($companyId)
            ->where('is_active', true)
            ->when(
                ! $user->canAccessAllTenants() && $user->tenant_id,
                fn ($builder) => $builder->where('tenant_id', $user->tenant_id)
            )
            ->exists();
    }
}
