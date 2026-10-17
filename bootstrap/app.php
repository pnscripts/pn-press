<?php

use App\Http\Middleware\ApplyTheme;
use App\Http\Middleware\CachePublicPages;
use App\Http\Middleware\EnsurePluginIsActive;
use App\Press\Plugins\PluginManager;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Route files of active plugins load with the core routes, so `route:cache` includes them.
        then: fn () => app(PluginManager::class)->registerRoutes(),
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->appendToGroup('web', [ApplyTheme::class, CachePublicPages::class]);
        $middleware->alias(['press.plugin' => EnsurePluginIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
