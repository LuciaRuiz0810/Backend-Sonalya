<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // statefulApi() removed: app uses Bearer tokens only, not cookie sessions.
        // Keeping it active caused Sanctum to prefer the web session over the Bearer
        // token when the request originated from the same host, resolving a different
        // user and triggering the soloArtista 403 check incorrectly.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
