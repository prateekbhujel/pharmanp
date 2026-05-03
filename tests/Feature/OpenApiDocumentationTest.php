<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_swagger_documentation_page_and_grouped_json_are_available(): void
    {
        Setting::putValue('app.installed', ['installed' => true]);

        $this->get('/api/documentation')
            ->assertOk()
            ->assertSee('swagger-ui')
            ->assertSee('StandaloneLayout');

        $json = $this->getJson('/docs/api-docs.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('servers.0.url', 'http://localhost/api/v1')
            ->json();

        $tags = collect($json['tags'] ?? [])->pluck('name')->all();

        $this->assertContains('AUTH - Authentication', $tags);
        $this->assertContains('INVENTORY - Products', $tags);
        $this->assertContains('ACCOUNTING - Payments', $tags);
        $this->assertArrayHasKey('/auth/login', $json['paths']);
        $this->assertArrayHasKey('/inventory/products', $json['paths']);
    }
}
