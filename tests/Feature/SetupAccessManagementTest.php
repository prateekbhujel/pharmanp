<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Company;
use App\Modules\MR\Models\MedicalRepresentative;
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
            ->assertJsonPath('data.permission_groups.Dashboard.permissions.0.name', 'dashboard.view')
            ->assertJsonPath('data.permission_groups.Dashboard.permissions.0.label', 'View dashboard');

        $this->actingAs($owner)->postJson('/api/v1/setup/roles', [
            'name' => 'Manager',
            'permissions' => ['dashboard.view', 'reports.view'],
        ])->assertCreated();

        $this->actingAs($owner)->postJson('/api/v1/setup/users', [
            'name' => 'Store Manager',
            'email' => 'manager@example.com',
            'phone' => '9800000000',
            'password' => 'secret12345',
            'role_names' => ['Manager'],
            'medical_representative_id' => $mr->id,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.medical_representative.id', $mr->id);

        $userListing = $this->actingAs($owner)->getJson('/api/v1/setup/users?medical_representative_linked=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('summary.total', 2)
            ->assertJsonPath('summary.mr_linked', 1)
            ->json();

        $this->assertTrue(collect($userListing['role_profiles'] ?? [])->contains(fn ($role) => $role['name'] === 'Manager'));

        $this->actingAs($owner)->putJson('/api/v1/profile', [
            'name' => 'Owner Updated',
            'email' => $owner->email,
            'phone' => '9811111111',
            'current_password' => 'secret12345',
            'password' => 'secret67890',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Owner Updated');

        $this->assertTrue(password_verify('secret67890', $owner->fresh()->password));
    }

    public function test_owner_cannot_link_user_to_other_company_mr(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $company = Company::query()->create(['name' => 'Access Pharma']);
        $otherCompany = Company::query()->create(['name' => 'Other Pharma']);
        $owner = User::factory()->create([
            'company_id' => $company->id,
            'is_owner' => true,
        ]);

        MedicalRepresentative::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Outside MR',
            'territory' => 'Pokhara',
            'monthly_target' => 15000,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->postJson('/api/v1/setup/roles', [
            'name' => 'Manager',
            'permissions' => [],
        ])->assertCreated();

        $foreignMr = MedicalRepresentative::query()->where('company_id', $otherCompany->id)->firstOrFail();

        $this->actingAs($owner)->postJson('/api/v1/setup/users', [
            'name' => 'Bad Link',
            'email' => 'bad-link@example.com',
            'password' => 'secret12345',
            'role_names' => ['Manager'],
            'medical_representative_id' => $foreignMr->id,
            'is_active' => true,
        ])->assertNotFound();
    }
}
