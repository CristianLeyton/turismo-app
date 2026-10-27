<?php

namespace App\Providers\Filament;

use App\Filament\Client\Pages\ClientAccess;
use App\Filament\Client\Pages\ClientProfile;
use App\Filament\Client\Pages\MyTickets;
use App\Filament\Client\Pages\SetPassword;
use App\Http\Middleware\EnsureClientIdentified;
use Filament\Enums\ThemeMode;
use Filament\View\PanelsRenderHook;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel público para clientes: identifica por DNI y permite asignar fecha
 * (y asiento) a sus boletos vendidos sin fecha.
 *
 * Sin login y sin authMiddleware: el delimitado no depende de Filament Auth
 * sino de la sesión de identificación (App\Support\ClientSession), que
 * EnsureClientIdentified exige en todas las páginas menos el ingreso.
 *
 * El panel de admin queda intacto (paths y providers separados).
 */
class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('clientes')
            ->path('clientes')
            ->brandName('Mis boletos')
            // Estética idéntica al panel principal: mismo tema compilado,
            // mismo logo y mismo color primario.
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandLogo(fn () => view('filament.admin.logo'))
            ->colors(['primary' => Color::Fuchsia])
            // El tema sigue la preferencia claro/oscuro del dispositivo del usuario.
            ->defaultThemeMode(ThemeMode::System)
            ->spa(hasPrefetching: true)
            ->topNavigation()
            ->pages([
                MyTickets::class,       // Dashboard: "/" del panel
                ClientAccess::class,    // Ingreso por DNI (+ contraseña opcional)
                SetPassword::class,     // Primera vez: cambia la clave inicial (DNI)
                ClientProfile::class,   // Mi perfil: datos y cambio de contraseña
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                EnsureClientIdentified::class,
            ])
            /* Igual que en el panel admin: la ruedita no cambia el valor de
               los inputs numéricos enfocados. */
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => <<<'HTML'
                    <script>
                        document.addEventListener('wheel', (event) => {
                            const el = event.target instanceof Element
                                ? event.target.closest('input[type="number"]')
                                : null;

                            if (el && document.activeElement === el) {
                                event.preventDefault();
                            }
                        }, { passive: false });
                    </script>
                HTML,
            );
    }
}
