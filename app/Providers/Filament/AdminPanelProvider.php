<?php

namespace App\Providers\Filament;

use app\Filament\Resources\Users\Pages\Auth\CustomLogin;
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
            
            ->topNavigation()
            ->navigationItems([
                NavigationItem::make('Vender')
                    ->url(fn() => TicketResource::getUrl('create'))
                    ->label('Vender')
                    ->icon('heroicon-m-banknotes')
                    ->isActiveWhen(fn() => request()->routeIs('filament.admin.resources.tickets.create'))
                    ->sort(0),

                NavigationItem::make('Boletos')
                    ->url(fn() => TicketResource::getUrl())
                    ->label('Boletos')
                    ->icon('heroicon-m-ticket')
                    ->isActiveWhen(fn() => request()->routeIs('filament.admin.resources.tickets.index') || request()->routeIs('filament.admin.resources.tickets.view'))
                    ->sort(0),
            ])
            ->unsavedChangesAlerts()
            ->id('admin')
            ->path('admin')
            ->login(CustomLogin::class)
            ->globalSearch(false)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(
                'panels::head.end',
                fn (): string => '<link rel="stylesheet" href="' . asset('css/custom-reset-button.css') . '">'
            )
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
                //AccountWidget::class,
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
            ]);
            
    }
}
