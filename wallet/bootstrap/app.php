<?php

use App\Http\Middleware\AssignApiRequestContext;
use App\Http\Middleware\EnrichAuthenticatedApiLogContext;
use App\Http\Middleware\EnsureUserNotSuspended;
use App\Http\Middleware\IdempotentMoneyRequest;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireNotAdmin;
use App\Http\Middleware\RequireKyc;
use App\Jobs\ProcessExpoPushReceipts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            AssignApiRequestContext::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhook/*',
        ]);

        $middleware->alias([
            'kyc' => RequireKyc::class,
            'admin' => RequireAdmin::class,
            'not_admin' => RequireNotAdmin::class,
            'idempotent.money' => IdempotentMoneyRequest::class,
            'api.log_context_auth' => EnrichAuthenticatedApiLogContext::class,
            'not_suspended' => EnsureUserNotSuspended::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->job((new ProcessExpoPushReceipts)->onQueue('push'))->everyFiveMinutes();
    })->create();
