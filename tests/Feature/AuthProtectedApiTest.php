<?php

namespace Tests\Feature;

use App\Core\Services\JwtTokenService;
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
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('code', 200)
            ->assertJsonStructure([
                'status',
                'code',
                'message',
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

    public function test_api_login_can_bootstrap_current_user_with_jwt(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'is_owner' => true,
            'is_active' => true,
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Authenticated.')
            ->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_revokes_current_jwt_token(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create(['is_owner' => true, 'is_active' => true]);
        $jwt = app(JwtTokenService::class)->issue($user);

        $this->withHeader('Authorization', 'Bearer '.$jwt)
            ->getJson('/api/v1/me')
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$jwt)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->withHeader('Authorization', 'Bearer '.$jwt)
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid or expired JWT token.');
    }

    public function test_jwt_can_rotate_browser_token(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create(['is_owner' => true, 'is_active' => true]);
        $jwt = app(JwtTokenService::class)->issue($user);

        $token = $this->withHeader('Authorization', 'Bearer '.$jwt)
            ->postJson('/api/v1/auth/token', [
                'device_name' => 'PharmaNP Browser Session',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'JWT token issued.')
            ->json('token');

        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.stats.products', 0);
    }

    public function test_bearer_token_can_call_api_without_session_or_csrf(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create(['is_owner' => true, 'is_active' => true]);
        $plainToken = app(JwtTokenService::class)->issue($user);

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.stats.products', 0);

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->putJson('/api/v1/setup/branding', [
                'app_name' => 'PharmaNP Demo',
                'country_code' => 'NP',
                'currency_symbol' => 'Rs.',
                'calendar_type' => 'bs',
                'show_breadcrumbs' => true,
                'sidebar_default_collapsed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.app_name', 'PharmaNP Demo');
    }

    public function test_invalid_bearer_token_is_rejected(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);

        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson('/api/v1/dashboard/summary')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid or expired JWT token.');
    }

    public function test_jwt_bearer_token_can_call_api_without_session_or_csrf(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create(['is_owner' => true, 'is_active' => true]);
        $jwt = app(JwtTokenService::class)->issue($user);

        $this->withHeader('Authorization', 'Bearer '.$jwt)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('code', 200)
            ->assertJsonPath('data.stats.products', 0);
    }

    public function test_api_login_can_issue_pharmanp_token_for_standalone_frontend(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create([
            'email' => 'frontend@example.com',
            'password' => 'password',
            'is_owner' => true,
            'is_active' => true,
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'issue_token' => true,
            'device_name' => 'React Dev Server',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('code', 200)
            ->assertJsonPath('message', 'Authenticated.')
            ->assertJsonPath('token_type', 'Bearer')
            ->json('token');

        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.stats.products', 0);
    }

    public function test_authenticated_jwt_can_issue_browser_token_for_swagger(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create(['is_owner' => true, 'is_active' => true]);
        $jwt = app(JwtTokenService::class)->issue($user);

        $token = $this->withHeader('Authorization', 'Bearer '.$jwt)
            ->postJson('/api/v1/auth/token', [
                'device_name' => 'Swagger UI',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'JWT token issued.')
            ->json('token');

        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }
}
