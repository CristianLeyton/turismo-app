<?php

namespace Tests\Feature;

use App\Filament\Resources\Sales\Pages\ManageSales;
use App\Filament\Resources\Sales\SalesResource;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\HandleComponents;
use Tests\TestCase;

class SalesResourceTest extends TestCase
{
    use CreatesSalesRecords;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-15 10:00:00'));

        // Primer usuario (id=1, el de pruebas): ve a todos los vendedores.
        $this->admin = User::factory()->superAdmin()->create();
        $this->actingAs($this->admin);
    }

    /**
     * Abre la tabla de Ventas filtrada por un rango (y métodos opcionales).
     *
     * @param  list<string>  $methods
     */
    private function salesTable(string $from, string $to, array $methods = []): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(ManageSales::class)
            ->set('tableFilters.date_range.from', $from)
            ->set('tableFilters.date_range.to', $to)
            ->set('tableFilters.payment_method.methods', $methods);
    }

    public function test_los_montos_de_la_fila_respetan_el_rango_de_fechas(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-10-10 09:00:00', [5000]);
        $this->createSaleFor($seller, '2026-11-10 09:00:00', [1000]);

        $this->salesTable('2026-11-01', '2026-11-30')
            ->assertCanSeeTableRecords([$seller])
            ->assertSeeHtml('$1.000')
            ->assertDontSeeHtml('$6.000');
    }

    public function test_un_vendedor_con_ventas_antes_y_despues_del_rango_no_aparece(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-09-10 09:00:00');
        $this->createSaleFor($seller, '2026-11-10 09:00:00');

        $this->salesTable('2026-10-01', '2026-10-31')
            ->assertCanNotSeeTableRecords([$seller]);
    }

    public function test_un_vendedor_con_ventas_dentro_del_rango_aparece(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-10-31 22:00:00', [2500]);

        $this->salesTable('2026-10-01', '2026-10-31')
            ->assertCanSeeTableRecords([$seller])
            ->assertSeeHtml('$2.500');
    }

    public function test_un_vendedor_con_solo_pagos_en_el_periodo_aparece_con_saldo_negativo(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-09-10 09:00:00', [9000]);
        $this->createPaymentFor($seller, '2026-10-05', 4000);

        $this->salesTable('2026-10-01', '2026-10-31')
            ->assertCanSeeTableRecords([$seller])
            ->assertSeeHtml('$-4.000')
            ->assertSee('Total pagos');

        $this->assertSame(4000.0, $this->summaryTotals('2026-10-01', '2026-10-31')['pagos']);
        $this->assertSame(-4000.0, $this->summaryTotals('2026-10-01', '2026-10-31')['saldo']);
    }

    public function test_un_vendedor_sin_movimientos_no_aparece(): void
    {
        $seller = $this->createSeller();

        $this->salesTable('2026-11-01', '2026-11-30')
            ->assertCanNotSeeTableRecords([$seller]);
    }

    public function test_el_filtro_por_metodo_tambien_filtra_los_pagos_en_la_fila_y_el_resumen(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00', [7000], 'cash');
        $this->createSaleFor($seller, '2026-11-10 10:00:00', [3000], 'transfer');
        $this->createPaymentFor($seller, '2026-11-11', 1000, 'cash');
        $this->createPaymentFor($seller, '2026-11-11', 2000, 'transfer');

        $this->salesTable('2026-11-01', '2026-11-30', ['cash'])
            ->assertCanSeeTableRecords([$seller])
            ->assertSeeHtml('$7.000')
            ->assertSeeHtml('$6.000');

        $totals = $this->summaryTotals('2026-11-01', '2026-11-30', ['cash']);
        $this->assertSame(1, $totals['boletos']);
        $this->assertSame(7000.0, $totals['ventas']);
        $this->assertSame(1, $totals['pagos_count']);
        $this->assertSame(1000.0, $totals['pagos']);
        $this->assertSame(6000.0, $totals['saldo']);
    }

    public function test_ordenar_respeta_el_metodo_filtrado(): void
    {
        $mostCash = $this->createSeller();
        $this->createSaleFor($mostCash, '2026-11-10 09:00:00', [3000, 2000], 'cash');

        $mostTransfer = $this->createSeller();
        $this->createSaleFor($mostTransfer, '2026-11-10 09:00:00', [1000], 'cash');
        $this->createSaleFor($mostTransfer, '2026-11-10 10:00:00', [20000], 'transfer');
        $this->createPaymentFor($mostTransfer, '2026-11-11', 900, 'cash');

        // Con "Efectivo": boletos 2 vs 1, ventas 5.000 vs 1.000, saldo 5.000 vs 100.
        foreach (['total_amount', 'saldo', 'tickets_count'] as $column) {
            $this->salesTable('2026-11-01', '2026-11-30', ['cash'])
                ->sortTable($column, 'desc')
                ->assertCanSeeTableRecords([$mostCash, $mostTransfer], inOrder: true);
        }

        $this->salesTable('2026-11-01', '2026-11-30', ['cash'])
            ->sortTable('payments_total', 'desc')
            ->assertCanSeeTableRecords([$mostTransfer, $mostCash], inOrder: true);
    }

    public function test_la_cantidad_de_consultas_no_crece_con_las_ventas(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00');

        $few = $this->countTableQueries();

        foreach (range(1, 15) as $day) {
            $this->createSaleFor($seller, sprintf('2026-11-%02d 09:00:00', $day), [1000, 2000]);
            $this->createPaymentFor($seller, sprintf('2026-11-%02d', $day), 500);
        }

        $this->assertSame($few, $this->countTableQueries());
    }

    public function test_el_detalle_abre_con_el_rango_y_los_metodos_de_la_tabla(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00', [7000], 'cash');
        $this->createSaleFor($seller, '2026-11-10 10:00:00', [3000], 'transfer');

        // Livewire no renderiza el componente anidado en los tests: se revisa
        // con qué datos lo monta el modal.
        $component = $this->salesTable('2026-11-01', '2026-11-30', ['cash', 'transfer'])
            ->mountTableAction('view_tickets', $seller);

        $data = $component->instance()->getMountedAction()->getModalContent()->getData();

        $this->assertSame($seller->id, $data['user']->id);
        $this->assertSame('2026-11-01', $data['from']);
        $this->assertSame('2026-11-30', $data['to']);
        $this->assertSame(['cash', 'transfer'], $data['payments']);

        $this->actingAs($this->admin);
        $detail = Livewire::test(\App\Livewire\SalesUserTickets::class, [
            'userId' => $seller->id,
            'from' => $data['from'],
            'to' => $data['to'],
            'payments' => $data['payments'],
        ]);

        $this->assertSame(10000.0, (float) $detail->instance()->totals['ventas_total']);
    }

    /**
     * Totales de la tarjeta de resumen con la tabla montada en esos filtros.
     * Livewire::current() es el último componente del stack, como pasa al
     * renderizar el render hook de la tarjeta.
     *
     * @param  list<string>  $methods
     * @return array<string, mixed>
     */
    private function summaryTotals(string $from, string $to, array $methods = []): array
    {
        $component = $this->salesTable($from, $to, $methods)->instance();

        HandleComponents::$componentStack[] = $component;

        try {
            return SalesResource::mobileSummaryTotals();
        } finally {
            array_pop(HandleComponents::$componentStack);
        }
    }

    private function countTableQueries(): int
    {
        $component = $this->salesTable('2026-11-01', '2026-11-30');

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $component->call('$refresh');
        $count = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();

        return $count;
    }
}
