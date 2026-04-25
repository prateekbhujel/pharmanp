<?php

namespace Tests\Feature;

use App\Models\Setting;
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
}
