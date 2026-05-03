<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleStructureInspector;
use App\Core\OpenApi\OpenApiAnnotationInspector;
use Tests\TestCase;

class ModuleArchitectureTest extends TestCase
{
    public function test_configured_modules_follow_the_pharmanp_modular_contract(): void
    {
        $rows = app(ModuleStructureInspector::class)->inspect();
        $failures = collect($rows)
            ->filter(fn (array $row): bool => $row['status'] === 'failed')
            ->values()
            ->all();

        $this->assertSame([], $failures);
    }

    public function test_every_repository_interface_is_bound_by_its_module_provider(): void
    {
        $rows = app(ModuleStructureInspector::class)->inspect();
        $unbound = collect($rows)
            ->flatMap(fn (array $row): array => $row['unbound_interfaces'])
            ->values()
            ->all();

        $this->assertSame([], $unbound);
    }

    public function test_api_modules_keep_pis_style_openapi_contracts(): void
    {
        $rows = app(OpenApiAnnotationInspector::class)->inspect();
        $failures = collect($rows)
            ->filter(fn (array $row): bool => $row['status'] === 'failed')
            ->values()
            ->all();

        $this->assertSame([], $failures);
    }
}
