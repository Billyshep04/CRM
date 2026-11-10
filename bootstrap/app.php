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
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);

        // Campaign HTML is stored exactly as pasted, so it must not be trimmed.
        $middleware->trimStrings(except: ['html_body']);

        // Mail apps send RFC 8058 one-click unsubscribes without a CSRF token; the link's own token authorises it.
        $middleware->validateCsrfTokens(except: ['email/unsubscribe/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
