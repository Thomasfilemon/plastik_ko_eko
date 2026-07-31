<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\RedirectIfNotAuthenticated;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\AdminDatabaseSwitchMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Exceptions\UnauthorizedException;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Only trust proxies when explicitly configured.
        // Defaulting to '*' breaks login when SSL is misconfigured
        // (session cookie gets Secure flag and browser drops it).
        $trustedProxies = env('TRUSTED_PROXIES');
        if (!empty($trustedProxies)) {
            $middleware->trustProxies(
                at: $trustedProxies === '*' ? '*' : array_map('trim', explode(',', $trustedProxies))
            );
        }

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Append to Laravel's default web stack (EncryptCookies, CSRF, Session, etc.)
        // Do NOT replace the whole group — that breaks sessions/login.
        $append = [
            RedirectIfNotAuthenticated::class,
            AdminDatabaseSwitchMiddleware::class,
        ];

        if (filter_var(env('APP_FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN)) {
            array_unshift($append, ForceHttps::class);
        }

        $middleware->web(append: $append);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (UnauthorizedException $e, $request) {
            abort(403, 'Access denied. Admins only.');
        });
    })->create();
