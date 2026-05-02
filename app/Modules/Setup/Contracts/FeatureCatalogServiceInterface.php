<?php

namespace App\Modules\Setup\Contracts;

interface FeatureCatalogServiceInterface
{
    public function grouped(): array;

    public function syncDefaults(): void;

    public function defaults(): array;
}
