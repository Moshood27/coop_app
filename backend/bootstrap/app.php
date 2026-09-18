<?php

use App\Services\SecurityLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
        \App\Console\Commands\CollectAdministrativeCharges::class,
        \App\Console\Commands\ProcessAdministrativeCharges::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust proxies (e.g., ngrok) so Laravel honors X-Forwarded-* headers
        $middleware->trustProxies(at: '*');

        // Exclude Paystack webhook from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'api/webhooks/paystack',
        ]);

        // Alias custom middleware
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'inactivity' => \App\Http\Middleware\InactivityTimeout::class,
            'track_activity' => \App\Http\Middleware\TrackUserActivity::class,
            'bypass_cache' => \App\Http\Middleware\BypassCache::class,
        ]);

        // Ensure Livewire/AJAX requests get JSON (prevents HTML redirects breaking JSON.parse)
        $middleware->prependToGroup('web', [\App\Http\Middleware\ForceJsonForLivewire::class]);

        // Append security and cache headers to all web and API responses
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\BypassCache::class,
        ]);
        $middleware->prependToGroup('api', [\App\Http\Middleware\QueryTokenToBearer::class]);
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\TrackUserActivity::class,
            \App\Http\Middleware\BypassCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (AccessDeniedHttpException $e) {
            SecurityLogger::logUnauthorizedAccess(request()->fullUrl());
        });

        if (class_exists(\Sentry\Laravel\Integration::class)) {
            \Sentry\Laravel\Integration::handles($exceptions);
        }

        // Return JSON for common auth/CSRF issues when the request is Livewire/AJAX
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            $isAjax = $request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->headers->has('X-Livewire');
            if ($isAjax) {
                return response()->json([
                    'message' => 'Unauthenticated',
                ], 401);
            }
        });

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            $isAjax = $request->expectsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->headers->has('X-Livewire');
            if ($isAjax) {
                return response()->json([
                    'message' => 'CSRF token mismatch or session expired',
                ], 419);
            }
        });
    })->create();
