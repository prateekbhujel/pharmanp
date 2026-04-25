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

    public function test_authenticated_dashboard_summary_loads(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create(['is_owner' => true]);

        $this->actingAs($user)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'stats',
                    'top_products',
                    'low_stock_rows',
                    'expiry_rows',
                    'recent_sales',
                    'recent_purchases',
                ],
            ]);
    }
}
