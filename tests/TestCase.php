<?php

namespace Tests;

use App\Core\Services\JwtTokenService;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        File::delete(storage_path('app/installed'));
    }

    public function actingAs(AuthenticatableContract $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($guard === null || $guard === 'web') {
            $this->withHeader('Authorization', 'Bearer '.app(JwtTokenService::class)->issue($user));
        }

        return $this;
    }
}
