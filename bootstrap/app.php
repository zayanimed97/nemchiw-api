<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Shared\Errors\ApiExceptionRenderer;
use Modules\Shared\Http\ForceJson;
use Modules\Shared\Http\LimitBodySize;
use Modules\Shared\Http\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(
            prepend: [ForceJson::class, SecurityHeaders::class],
            append: [LimitBodySize::class],
        );
        $middleware->throttleApi();
        // No route is named `login`: guests on admin web routes go to the Filament login.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ApiExceptionRenderer::register($exceptions);
    })->create();
