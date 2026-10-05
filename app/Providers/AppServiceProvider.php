<?php

namespace App\Providers;

use App\Models\User;
use App\Observers\UserObserver;
use App\Support\Permissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Administrador: bypass de todas las verificaciones de
        // autorización (policies y Gate). Debe devolver null para el resto
        // de los usuarios para que se ejecuten las policies normales.
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole(Permissions::ROLE_SUPER) ? true : null;
        });

        // Compatibilidad is_admin <=> roles (ver UserObserver).
        User::observe(UserObserver::class);

        // Resumen de totales para las tablas del clúster de dinero (Ventas y
        // Pagos). Reemplaza a las filas "Resumen" del footer: se inyecta dentro
        // del componente Livewire (TOOLBAR_AFTER) para que reaccione a los
        // filtros sin eventos extra.
        // TOOLBAR_AFTER se renderiza sin scopes en filament-tables::index, así
        // que el guard "¿en qué página estoy?" vive en el componente.
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            \Filament\Tables\View\TablesRenderHook::TOOLBAR_AFTER,
            fn (): string => (new \App\View\Components\TableSummaryCard)->render()->render(),
        );
    }
}
