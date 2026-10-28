<?php

use App\Http\Middleware\AccountReady;
use App\Http\Middleware\Branding;
use App\Http\Middleware\RuntimeSettings;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\Turnstile;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['turnstile' => Turnstile::class, 'account.ready' => AccountReady::class]);
        $middleware->web(append: [RuntimeSettings::class, Branding::class]);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['mail_password', 'mail_username', 's3_key', 's3_secret', 'google_client_secret', 'turnstile_secret_key']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
