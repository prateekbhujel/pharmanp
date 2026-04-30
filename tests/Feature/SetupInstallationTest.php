<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Modules\Inventory\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
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
            'branding' => [
                'app_name' => 'PharmaNP',
                'country_code' => 'NP',
                'currency_symbol' => 'Rs.',
                'calendar_type' => 'bs',
            ],
            'fiscal_year' => [
                'name' => '2026/27',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ],
            'admin' => [
                'name' => 'Pratik Admin',
                'email' => 'pratik@admin.com',
                'password' => 'Done@12345',
                'password_confirmation' => 'Done@12345',
            ],
        ];

        $this->postJson('/setup/complete', $payload)->assertCreated();

        $this->assertTrue(User::query()->where('email', 'pratik@admin.com')->where('is_owner', true)->exists());
        $this->assertNotNull(Setting::getValue('app.installed'));
        $this->assertTrue(File::exists(storage_path('app/installed')));

        $this->postJson('/setup/complete', $payload)->assertStatus(409);
    }

    public function test_owner_can_update_application_branding(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);
        $user = User::factory()->create(['is_owner' => true]);

        $this->actingAs($user)->putJson('/api/v1/setup/branding', [
            'app_name' => 'Bhujel Pharmacy',
            'logo_url' => '/storage/settings/logo.png',
            'sidebar_logo_url' => '/storage/settings/sidebar.png',
            'app_icon_url' => '/storage/settings/icon.png',
            'favicon_url' => '/storage/settings/favicon.ico',
            'accent_color' => '#0f766e',
            'sidebar_default_collapsed' => true,
            'show_breadcrumbs' => false,
            'country_code' => 'NP',
            'currency_symbol' => 'Rs.',
            'calendar_type' => 'bs',
        ])->assertOk();

        $branding = Setting::getValue('app.branding');

        $this->assertSame('Bhujel Pharmacy', $branding['app_name']);
        $this->assertSame('/storage/settings/favicon.ico', $branding['favicon_url']);
        $this->assertTrue($branding['sidebar_default_collapsed']);
        $this->assertFalse($branding['show_breadcrumbs']);
        $this->assertSame('vertical', $branding['layout']);

        $this->actingAs($user)->getJson('/api/v1/setup/branding')
            ->assertOk()
            ->assertJsonPath('data.app_name', 'Bhujel Pharmacy')
            ->assertJsonPath('data.product.name', 'PharmaNP')
            ->assertJsonPath('data.product.developer_name', 'Pratik Bhujel')
            ->assertJsonPath('data.product.developer_email', 'prateekbhujelpb@gmail.com');
    }

    public function test_setup_completion_accepts_brand_uploads_and_defaults_store_name(): void
    {
        Storage::fake('public');

        $response = $this->post('/setup/complete', [
            'company' => ['name' => 'Upload Pharma'],
            'branding' => [
                'app_name' => 'Upload Pharma',
                'country_code' => 'NP',
                'currency_symbol' => 'Rs.',
                'calendar_type' => 'bs',
                'logo_file' => UploadedFile::fake()->image('logo.png'),
                'favicon_file' => UploadedFile::fake()->image('favicon.png', 32, 32),
            ],
            'fiscal_year' => [
                'name' => '2026/27',
                'starts_on' => '2026-01-01',
                'ends_on' => '2026-12-31',
            ],
            'admin' => [
                'name' => 'Upload Admin',
                'email' => 'upload@admin.com',
                'password' => 'Done@12345',
                'password_confirmation' => 'Done@12345',
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();

        $this->assertSame('Main Store', Store::query()->value('name'));

        $branding = Setting::getValue('app.branding');

        $this->assertStringStartsWith('storage/settings/branding/', $branding['logo_url']);
        $this->assertStringStartsWith('storage/settings/branding/', $branding['favicon_url']);
        Storage::disk('public')->assertExists(str_replace('storage/', '', $branding['logo_url']));
        Storage::disk('public')->assertExists(str_replace('storage/', '', $branding['favicon_url']));
    }
}
