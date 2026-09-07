<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        if ($trustedProxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(
                at: array_map('trim', explode(',', $trustedProxies)),
                headers: TrustProxies::HEADER_X_FORWARDED_FOR
                    | TrustProxies::HEADER_X_FORWARDED_HOST
                    | TrustProxies::HEADER_X_FORWARDED_PORT
                    | TrustProxies::HEADER_X_FORWARDED_PROTO,
            );
        }

        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \App\Http\Middleware\EnforceMfa::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();