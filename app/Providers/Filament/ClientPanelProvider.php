<?php

namespace App\Providers\Filament;

use App\Filament\Client\Pages\Auth\ClientEditProfile;
use App\Filament\Client\Pages\Auth\ClientLogin;
use App\Filament\Client\Pages\MyTickets;
use App\Filament\Client\Pages\SetPassword;
use App\Http\Middleware\EnsureClientPanelIsEnabled;
use App\Http\Middleware\EnsureClientPasswordIsSet;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel público para clientes: ingresan con DNI y contraseña (login de
 * Filament sobre el guard `client`, tabla clients) y pueden asignar fecha
 * (y asiento) a sus boletos vendidos sin fecha.
 *
 * El guard es distinto del `web` del panel de administración: un vendedor y
 * un cliente pueden tener sesión a la vez en el mismo navegador.
 */
class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('clientes')
            ->path('clientes')
            ->authGuard('client')
            ->login(ClientLogin::class)
            ->profile(ClientEditProfile::class, isSimple: false)
            // El "Salir" del menú no usa el logout de Filament, que invalida
            // toda la sesión y cerraría también la de administración.
            ->userMenuItems([
                'logout' => fn (Action $action): Action => $action->url(fn (): string => route('clientes.logout')),
            ])
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
                SetPassword::class,     // Primera vez: cambia la clave inicial (DNI)
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
            ])
            // Panel apagado desde Parámetros: 404 en todo /clientes, ingreso
            // incluido. Persistente para cortar también las peticiones
            // Livewire de una página que quedó abierta.
            ->middleware([
                EnsureClientPanelIsEnabled::class,
            ], isPersistent: true)
            ->authMiddleware([
                Authenticate::class,
                EnsureClientPasswordIsSet::class,
            ])
            // Debajo del ingreso, las líneas de contacto configuradas en
            // Parámetros > Boleto (si hay alguna): el subtítulo invita a
            // comunicarse cuando no se puede entrar.
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => view('filament.client.login-contact-lines', [
                    'contactLines' => Setting::contactLines(),
                ])->render(),
                scopes: ClientLogin::class,
            )
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
