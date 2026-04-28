<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
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
            'territory' => 'Kathmandu',
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
}
