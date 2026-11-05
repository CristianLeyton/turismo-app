<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\CustomDashboard;
use App\Filament\Resources\Tickets\TicketResource;
use App\Filament\Resources\Users\Pages\Auth\CustomLogin;
use App\Filament\Widgets\Panel as PanelWidget;
use App\Filament\Widgets\UserAccount;
use App\Models\Setting;
use App\Support\BrandPalette;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->profile(isSimple: false, page: EditProfile::class)
            /* ->registration() */
            /* ->passwordReset()
            ->emailVerification() */
            /* ->emailChangeVerification() */
            ->spa(hasPrefetching: true)
            /*             ->sidebarCollapsibleOnDesktop() */
            ->default()
            ->brandLogo(fn () => view('filament.admin.logo'))
            ->topNavigation()
            ->navigationItems([
                NavigationItem::make('Vender')
                    ->url(fn () => TicketResource::getUrl('create'))
                    ->label('Vender')
                    ->icon('heroicon-m-banknotes')
                    ->isActiveWhen(fn () => request()->routeIs('filament.admin.tickets.resources.tickets.create'))
                    ->sort(0),
            ])
            ->unsavedChangesAlerts()
            ->id('admin')
            ->path('admin')
            ->login(CustomLogin::class)
            ->globalSearch(false)
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Color de la empresa (Parámetros > Apariencia): la paleta se
            // genera en cada request, sin recompilar el tema.
            ->colors(fn (): array => ['primary' => BrandPalette::current()])
            // Texto sobre el color principal (logo y vistas propias): blanco
            // u oscuro según el contraste con el color elegido.
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => BrandPalette::textColorStyleTag(),
            )
            ->defaultThemeMode(ThemeMode::Light)
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                CustomDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                PanelWidget::class,
                UserAccount::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            /* La ruedita del mouse no cambia el valor de los inputs numéricos:
               sólo interfiere si el campo tiene foco (sino la página scrollea
               con normalidad). Lo inverso de las flechas ya se ocultó en el
               tema; esto cubre el cambio por rueda conjugado con foco. */
            /* El menú de usuario (click en el avatar): encabezado con el
               nombre completo y los roles asignados, arriba de "Perfil". */
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_BEFORE,
                fn () => Auth::user()
                    ? view('filament.admin.user-menu-info', ['user' => Auth::user()])->render()
                    : '',
            )
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
                        }, { passive: false });                    </script>
                HTML,
            )
            /* Atajo para clientes: un pasajero puede llegar por error al login
               del panel de administración; desde ahí lo mando al ingreso del
               panel de clientes. Solo con el parámetro "Panel de clientes
               activo": apagado, /clientes responde 404. El render hook solo
               se aplica en la página Login de este panel. */
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => Setting::panelClientesActivo()
                    ? view('filament.admin.client-login-shortcut')->render()
                    : '',
            );
    }
}
