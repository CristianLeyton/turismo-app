<?php

use App\Http\Middleware\EnsureClientPanelIsEnabled;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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
        $middleware->redirectGuestsTo(
            fn (): string => Filament::getPanel('admin')->getLoginUrl() ?? '/admin'
        );

        // Con el panel de clientes apagado, el 404 va antes que la
        // redirección al ingreso: si no, /clientes sin sesión redirige a un
        // login que no existe.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureClientPanelIsEnabled::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
