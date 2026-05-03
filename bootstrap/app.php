<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'installed' => \App\Http\Middleware\EnsurePharmaNpIsInstalled::class,
            'not_installed' => \App\Http\Middleware\RedirectIfPharmaNpIsInstalled::class,
            'pharmanp.api' => \App\Http\Middleware\AuthenticatePharmaApi::class,
        ]);

        $middleware->web(replace: [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\ValidateCsrfToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
