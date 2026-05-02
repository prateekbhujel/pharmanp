<?php

namespace App\Core\Modules;

final readonly class ModuleDefinition
{
    public function __construct(
        public string $key,
        public string $name,
        public string $domain,
        public string $namespace,
        public ?string $frontendPath = null,
        public ?string $provider = null,
    ) {}

    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            name: (string) $config['name'],
            domain: (string) $config['domain'],
            namespace: (string) $config['namespace'],
            frontendPath: $config['frontend'] ?? null,
            provider: $config['provider'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'domain' => $this->domain,
            'namespace' => $this->namespace,
            'frontend_path' => $this->frontendPath,
            'provider' => $this->provider,
        ];
    }
}
