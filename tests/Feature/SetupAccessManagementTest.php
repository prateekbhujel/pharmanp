<?php

namespace Tests\Feature;

use App\Core\Services\DocumentNumberService;
use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
use App\Modules\MR\Models\Branch;
use App\Modules\MR\Models\MedicalRepresentative;
use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_roles_users_and_profile(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $company = Company::query()->create(['name' => 'Access Pharma']);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'is_owner' => true,
            'password' => bcrypt('secret12345'),
        ]);

        $mr = MedicalRepresentative::query()->create([
            'company_id' => $company->id,
            'name' => 'Nabin MR',
            'monthly_target' => 50000,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->getJson('/api/v1/setup/roles')
            ->assertOk()
            ->assertJsonPath('data.permission_groups.Dashboard.0', 'dashboard.view');

        $this->actingAs($owner)->postJson('/api/v1/setup/roles', [
            'name' => 'Manager',
            'permissions' => ['dashboard.view', 'reports.view'],
        ])->assertCreated();

        $createUserResponse = $this->actingAs($owner)->postJson('/api/v1/setup/users', [
            'name' => 'Store Manager',
            'email' => 'manager@example.com',
            'phone' => '9800000000',
            'password' => 'secret12345',
            'role_names' => ['Manager'],
            'medical_representative_id' => $mr->id,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.medical_representative.id', $mr->id);

        $managerId = $createUserResponse->json('data.id');

        $this->actingAs($owner)->patchJson("/api/v1/setup/users/{$managerId}/status", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($owner)->putJson('/api/v1/profile', [
            'name' => 'Owner Updated',
            'email' => $owner->email,
            'phone' => '9811111111',
            'current_password' => 'secret12345',
            'password' => 'secret67890',
            'password_confirmation' => 'secret67890',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Owner Updated');

        $this->assertTrue(password_verify('secret67890', $owner->fresh()->password));
    }

    public function test_owner_can_toggle_managed_dropdown_status(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $company = Company::query()->create(['name' => 'Dropdown Pharma']);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'is_owner' => true,
        ]);

        $option = DropdownOption::query()->create([
            'alias' => 'payment_mode',
            'name' => 'Counter QR',
            'data' => 'wallet',
            'status' => true,
        ]);

        $this->actingAs($owner)->patchJson("/api/v1/settings/dropdown-options/{$option->id}/status", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($option->fresh()->status);
    }

    public function test_owner_can_manage_fiscal_years(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $company = Company::query()->create(['name' => 'Fiscal Pharma']);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'is_owner' => true,
        ]);

        $createResponse = $this->actingAs($owner)->postJson('/api/v1/settings/fiscal-years', [
            'name' => 'FY 2081/82',
            'starts_on' => '2024-07-16',
            'ends_on' => '2025-07-15',
            'is_current' => true,
            'status' => 'open',
        ])->assertCreated();

        $fiscalYearId = $createResponse->json('data.id');

        $this->actingAs($owner)->getJson('/api/v1/settings/fiscal-years')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'FY 2081/82');

        $this->actingAs($owner)->putJson("/api/v1/settings/fiscal-years/{$fiscalYearId}", [
            'name' => 'FY 2081/82 Revised',
            'starts_on' => '2024-07-16',
            'ends_on' => '2025-07-15',
            'is_current' => true,
            'status' => 'open',
        ])->assertOk()
            ->assertJsonPath('data.name', 'FY 2081/82 Revised');

        $this->actingAs($owner)->deleteJson("/api/v1/settings/fiscal-years/{$fiscalYearId}")
            ->assertOk();
    }

    public function test_owner_can_impersonate_and_return_to_admin_with_jwt(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $company = Company::query()->create(['name' => 'Impersonation Pharma']);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'is_owner' => true,
        ]);
        $staff = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Counter Staff',
            'is_active' => true,
        ]);

        $impersonate = $this->actingAs($owner)->postJson("/api/v1/setup/users/{$staff->id}/impersonate")
            ->assertOk();

        $staffToken = $impersonate->json('token');
        $this->assertNotEmpty($staffToken);

        $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $staff->email)
            ->assertJsonPath('data.impersonating', true)
            ->assertJsonPath('data.impersonator_user_id', $owner->id);

        $returned = $this->withHeader('Authorization', 'Bearer '.$staffToken)
            ->postJson('/api/v1/setup/users/stop-impersonating')
            ->assertOk();

        $ownerToken = $returned->json('token');
        $this->assertNotEmpty($ownerToken);

        $this->withHeader('Authorization', 'Bearer '.$ownerToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $owner->email)
            ->assertJsonPath('data.impersonating', false);
    }

    public function test_owner_can_manage_branches_as_master_data(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $company = Company::query()->create(['name' => 'Branch Pharma']);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'is_owner' => true,
        ]);

        $hqResponse = $this->actingAs($owner)->postJson('/api/v1/mr/branches', [
            'name' => 'Kathmandu Head Office',
            'code' => 'KTM-HQ',
            'type' => 'hq',
            'address' => 'Baneshwor, Kathmandu',
            'phone' => '01-5000000',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'KTM-HQ');

        $hqId = $hqResponse->json('data.id');

        $branchResponse = $this->actingAs($owner)->postJson('/api/v1/mr/branches', [
            'name' => 'Pokhara Branch',
            'code' => 'PKR-BR',
            'type' => 'branch',
            'parent_id' => $hqId,
            'address' => 'Lakeside, Pokhara',
            'phone' => '061-500000',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.parent.id', $hqId);

        $branchId = $branchResponse->json('data.id');

        $this->actingAs($owner)->getJson('/api/v1/mr/branches?search=Pokhara')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.address', 'Lakeside, Pokhara');

        $this->actingAs($owner)->patchJson("/api/v1/mr/branches/{$branchId}/status", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($owner)->deleteJson("/api/v1/mr/branches/{$branchId}")
            ->assertOk();

        $this->assertSoftDeleted(Branch::class, ['id' => $branchId]);

        $this->actingAs($owner)->postJson("/api/v1/mr/branches/{$branchId}/restore")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_owner_without_company_context_can_create_fiscal_year_for_single_active_company(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $company = Company::query()->create(['name' => 'Recovered Context Pharma']);
        $owner = User::factory()->create([
            'tenant_id' => null,
            'company_id' => null,
            'is_owner' => true,
        ]);

        $this->actingAs($owner)->postJson('/api/v1/settings/fiscal-years', [
            'name' => 'FY 2082/83',
            'starts_on' => '2025-07-17',
            'ends_on' => '2026-07-16',
            'is_current' => true,
            'status' => 'open',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'FY 2082/83');

        $this->assertDatabaseHas('fiscal_years', [
            'company_id' => $company->id,
            'name' => 'FY 2082/83',
            'is_current' => true,
        ]);
    }

    public function test_owner_without_company_context_uses_branding_company_for_fiscal_year(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        Company::query()->create(['name' => 'Other Pharma']);
        $company = Company::query()->create(['name' => 'Branding Pharma']);
        Setting::putValue('app.branding', ['company_id' => $company->id]);
        $owner = User::factory()->create([
            'tenant_id' => null,
            'company_id' => null,
            'is_owner' => true,
        ]);

        $this->actingAs($owner)->postJson('/api/v1/settings/fiscal-years', [
            'name' => 'FY 2083/84',
            'starts_on' => '2026-07-17',
            'ends_on' => '2027-07-16',
            'is_current' => true,
            'status' => 'open',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'FY 2083/84');

        $this->assertDatabaseHas('fiscal_years', [
            'company_id' => $company->id,
            'name' => 'FY 2083/84',
            'is_current' => true,
        ]);
    }

    public function test_fiscal_year_create_returns_validation_error_when_company_context_is_missing(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $owner = User::factory()->create([
            'tenant_id' => null,
            'company_id' => null,
            'is_owner' => true,
        ]);

        $this->actingAs($owner)->postJson('/api/v1/settings/fiscal-years', [
            'name' => 'FY 2082/83',
            'starts_on' => '2025-07-17',
            'ends_on' => '2026-07-16',
            'is_current' => true,
            'status' => 'open',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('company_id');
    }

    public function test_owner_can_configure_document_numbering_without_exposing_smtp_password(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $owner = User::factory()->create(['is_owner' => true]);

        $this->actingAs($owner)->putJson('/api/v1/settings/admin', [
            'smtp_password' => 'SuperSecretMailPass',
            'document_numbering' => [
                'purchase_order' => [
                    'prefix' => 'MPO',
                    'date_format' => 'none',
                    'separator' => '/',
                    'padding' => 3,
                ],
                'purchase' => [
                    'prefix' => 'PB',
                    'date_format' => 'Y',
                    'separator' => '-',
                    'padding' => 4,
                ],
                'sales_invoice' => [
                    'prefix' => 'SA',
                    'date_format' => 'Ym',
                    'separator' => '-',
                    'padding' => 5,
                ],
                'voucher' => [
                    'prefix' => 'JV',
                    'date_format' => 'Ymd',
                    'separator' => '-',
                    'padding' => 5,
                ],
            ],
        ])->assertOk();

        $this->actingAs($owner)->getJson('/api/v1/settings/admin')
            ->assertOk()
            ->assertJsonPath('data.smtp_password', '')
            ->assertJsonPath('data.smtp_password_set', true)
            ->assertJsonPath('data.document_numbering.purchase_order.prefix', 'MPO');

        $this->assertSame(
            'MPO/001',
            app(DocumentNumberService::class)->next('purchase_order', 'purchase_orders')
        );
    }
}
