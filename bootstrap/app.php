<?php

use App\Http\Middleware\LogLivewireTransportFailures;
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
        $middleware->append(LogLivewireTransportFailures::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Livewire sends Accept: */*. Return JSON failures instead of login redirects.
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $exception) => ($request->hasHeader('X-Livewire') && $request->routeIs('*livewire.update'))
            || $request->expectsJson()
        );
    })->create();
