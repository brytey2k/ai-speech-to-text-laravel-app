<?php

declare(strict_types=1);

use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->append(TrustProxies::class);
    })
    ->withSchedule(static function (Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('transcriptions:resubmit-failed')->everyMinute();
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        //
    })->create();
