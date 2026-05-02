<?php

namespace App\Modules\Setup\Contracts;

interface AccessControlServiceInterface
{
    public function syncPermissions(): void;

    public function permissionNames(): array;

    public function permissionGroups(): array;
}
