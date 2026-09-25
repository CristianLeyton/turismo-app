<?php

namespace App\View\Components;

use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Filament\Resources\Sales\Pages\ManageSales;
use Illuminate\Contracts\View\View;

/**
 * Render hook de las tablas del clúster de dinero (Ventas y Pagos):
 * tarjeta de totales sobre la tabla, en todos los breakpoints.
 *
 * Reemplaza a la fila "Resumen" del footer de cada tabla: usa los
 * totales del resource correspondiente (mobileSummaryTotals() en
 * SalesResource / PaymentResource), que consumen la MISMA fuente que
 * los exports y la query ya filtrada: reaccionan a fechas, método de
 * pago, vendedor y búsqueda sin eventos extra.
 *
 * El hook TOOLBAR_AFTER de la tabla se registra SIN scope (se renderiza sin
 * scopes en filament-tables::index), así que el componente decide por sí
 * mismo si corresponde pintarse: solo en ManageSales o ManagePayments.
 */
class TableSummaryCard extends \Illuminate\View\Component
{
    public function render(): View
    {
        $livewire = \Livewire\Livewire::current();

        if ($livewire instanceof ManageSales) {
            return view('filament.resources.sales.summary-card', [
                'totals' => \App\Filament\Resources\Sales\SalesResource::mobileSummaryTotals(),
            ]);
        }

        if ($livewire instanceof ManagePayments) {
            return view('filament.resources.payments.summary-card', [
                'totals' => \App\Filament\Resources\Payments\PaymentResource::mobileSummaryTotals(),
            ]);
        }

        // Otras páginas con tabla (Boletos, Clientes, etc.) no llevan tarjeta.
        return view('filament.resources.summary-card-empty');
    }
}
