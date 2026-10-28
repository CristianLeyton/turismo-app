<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Users\Pages\Auth\CustomLogin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use App\Filament\Pages\CustomDashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Enums\ThemeMode;
use Filament\Navigation\NavigationItem;
use Filament\View\PanelsRenderHook;
use App\Filament\Widgets\Panel as PanelWidget;
use App\Filament\Widgets\UserAccount;
use Illuminate\Support\Facades\Auth;

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
                    ->url(fn() => TicketResource::getUrl('create'))
                    ->label('Vender')
                    ->icon('heroicon-m-banknotes')
                    ->isActiveWhen(fn() => request()->routeIs('filament.admin.tickets.resources.tickets.create'))
                    ->sort(0),
            ])
            ->unsavedChangesAlerts()
            ->id('admin')
            ->path('admin')
            ->login(CustomLogin::class)
            ->globalSearch(false)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Fuchsia,
            ])
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
               del panel de administración; desde ahí lo mando al panel
               público de clientes ("Mis boletos", PanelProvider con path
               'clientes', que incluye el ingreso por DNI). Sólo en el login
               del panel de administración: PanelsRenderHook sólo se
               aplica en la página Login de este panel. */
/*             ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => <<<'HTML'
                    <style>
                        .clientes-login-shortcut {
                            margin-top: 1rem;
                            padding: 0.75rem 1rem;
                            border: 1px solid #f3e8f6;
                            border-radius: 0.75rem;
                            background: #faf5fd;
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                            gap: 0.75rem;
                            flex-wrap: wrap;
                            font-size: 0.875rem;
                            color: #57534e;
                        }

                        .clientes-login-shortcut p {
                            margin: 0;
                        }

                        .clientes-login-shortcut a {
                            color: #c026d3;
                            font-weight: 600;
                            text-decoration: none;
                            white-space: nowrap;
                        }

                        .clientes-login-shortcut a:hover {
                            text-decoration: underline;
                        }
                    </style>
                    <div class="clientes-login-shortcut">
                        <p>¿Sos pasajero? Tus boletos están en el <a href="/clientes">panel de Clientes.</a></p>
                        
                    </div>
                HTML,
            ) */;
    }
}
