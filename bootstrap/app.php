<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetCurrentShop;
use App\Http\Middleware\SetStorefrontLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetCurrentShop::class,
            EnsureUserIsActive::class,
            SetStorefrontLocale::class,
        ]);

        // لازم يشتغل قبل route model binding، وإلا الـ binding هيرمي MissingTenantException
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: SetCurrentShop::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
