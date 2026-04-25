<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SetupInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_status_reports_not_installed_before_completion(): void
    {
        $this->getJson('/setup/status')
            ->assertOk()
            ->assertJsonPath('data.installed', false);
    }

    public function test_setup_completion_creates_owner_and_blocks_second_setup(): void
    {
        $payload = [
            'company' => ['name' => 'PharmaNP Test', 'address' => 'Kathmandu'],
            'store' => ['name' => 'Main Store'],
            'admin' => [
                'name' => 'Owner',
                'email' => 'owner@pharmanp.test',
                'password' => 'StrongPass123',
                'password_confirmation' => 'StrongPass123',
            ],
        ];

        $this->postJson('/setup/complete', $payload)->assertCreated();

        $this->assertTrue(User::query()->where('email', 'owner@pharmanp.test')->where('is_owner', true)->exists());
        $this->assertNotNull(Setting::getValue('app.installed'));
        $this->assertTrue(File::exists(storage_path('app/installed')));

        $this->postJson('/setup/complete', $payload)->assertStatus(409);
    }
}
