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
            'auth.agent' => \App\Http\Middleware\AgentAuthMiddleware::class,
        ]);

        // Trust Cloudflare/reverse proxy so Laravel reads X-Forwarded-Proto correctly
        // (without this, HTTPS requests behind the proxy look like HTTP internally,
        // which drops the Secure session cookie right after login).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
