<?php

namespace App\Core\Modules;

use Illuminate\Support\Collection;

final class ModuleRegistry
{
    /** @var Collection<string, ModuleDefinition> */
    private Collection $modules;

    public function __construct(array $modules)
    {
        $this->modules = collect($modules)
            ->map(fn (array $module, string $key) => ModuleDefinition::fromConfig($key, $module));
    }

    /**
     * @return Collection<string, ModuleDefinition>
     */
    public function all(): Collection
    {
        return $this->modules;
    }

    public function find(string $key): ?ModuleDefinition
    {
        return $this->modules->get($key);
    }

    public function toArray(): array
    {
        return $this->modules
            ->map(fn (ModuleDefinition $module) => $module->toArray())
            ->values()
            ->all();
    }
}
