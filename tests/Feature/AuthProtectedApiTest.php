<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthProtectedApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_api_requires_authentication(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);

        $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
    }

    public function test_dashboard_api_requires_permission_when_authenticated(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create(['is_owner' => false]);

        $this->actingAs($user)
            ->getJson('/api/v1/dashboard/summary')
            ->assertForbidden();
    }
}
