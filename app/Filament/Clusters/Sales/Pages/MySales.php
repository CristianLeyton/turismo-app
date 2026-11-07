<?php

namespace App\Filament\Clusters\Sales\Pages;

use App\Filament\Clusters\Sales\SalesCluster;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * "Mis ventas": el vendedor logueado ve sus boletos vendidos, los pagos que
 * realizó y su saldo, con el mismo detalle (SalesUserTickets) que el admin
 * abre en el modal "Ver detalle" de Ventas. El componente se autoriza solo
 * y no deja cambiar el vendedor consultado desde el navegador.
 */
class MySales extends Page
{
    protected static ?string $cluster = SalesCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserCircle;

    protected static ?string $navigationLabel = 'Mis ventas';

    protected static ?string $title = 'Mis ventas';

    protected static ?string $slug = 'mis-ventas';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.clusters.sales.pages.my-sales';

    public static function canAccess(): bool
    {
        return Auth::check() && (bool) Auth::user()?->can('sales.view_own');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'Tus boletos vendidos, pagos realizados y saldo';
    }

    /**
     * Datos con los que se monta el detalle: el usuario logueado y el mes
     * actual (los mismos valores que "Restablecer").
     *
     * @return array{userId: int, from: string, to: string}
     */
    public function getDetailParameters(): array
    {
        return [
            'userId' => (int) Auth::id(),
            'from' => Carbon::now()->startOfMonth()->toDateString(),
            'to' => Carbon::now()->endOfMonth()->toDateString(),
        ];
    }
}
