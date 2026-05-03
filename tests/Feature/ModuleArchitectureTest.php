<?php

namespace Tests\Feature;

use App\Core\Modules\ModuleStructureInspector;
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
}
