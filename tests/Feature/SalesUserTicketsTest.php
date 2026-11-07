<?php

namespace Tests\Feature;

use App\Livewire\SalesUserTickets;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class SalesUserTicketsTest extends TestCase
{
    use CreatesSalesRecords;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-15 10:00:00'));
    }

    public function test_el_administrador_con_ver_detalle_consulta_a_cualquier_vendedor(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00', [12000]);

        $this->actingAs($this->createAdmin());

        Livewire::test(SalesUserTickets::class, ['userId' => $seller->id, 'from' => '2026-11-01', 'to' => '2026-11-30'])
            ->assertOk()
            ->assertSee('$12.000');
    }

    public function test_el_vendedor_con_ver_mis_ventas_consulta_sus_propios_datos(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00', [8000]);

        $this->actingAs($seller);

        Livewire::test(SalesUserTickets::class, ['userId' => $seller->id])
            ->assertOk()
            ->assertSee('$8.000');
    }

    public function test_el_vendedor_no_puede_montar_el_detalle_de_otro_vendedor(): void
    {
        $seller = $this->createSeller();
        $other = $this->createSeller();

        $this->actingAs($seller);

        Livewire::test(SalesUserTickets::class, ['userId' => $other->id])
            ->assertForbidden();
    }

    public function test_el_navegador_no_puede_cambiar_el_vendedor_consultado(): void
    {
        $seller = $this->createSeller();
        $other = $this->createSeller();

        $this->actingAs($seller);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(SalesUserTickets::class, ['userId' => $seller->id])
            ->set('userId', $other->id);
    }

    public function test_exportar_sin_permiso_se_rechaza(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00');

        $this->actingAs($seller);

        $pdf = Livewire::test(SalesUserTickets::class, ['userId' => $seller->id]);
        $excel = Livewire::test(SalesUserTickets::class, ['userId' => $seller->id]);

        // Pierde el permiso con el detalle abierto.
        \Spatie\Permission\Models\Role::findByName(Permissions::ROLE_SELLER)->revokePermissionTo('sales.view_own');
        $seller->unsetRelation('roles')->unsetRelation('permissions');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $pdf->call('exportPdf')->assertForbidden();
        $excel->call('exportExcel')->assertForbidden();
    }

    public function test_el_vendedor_exporta_sus_propios_datos(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00');

        $this->actingAs($seller);

        Livewire::test(SalesUserTickets::class, ['userId' => $seller->id])
            ->call('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_filtrar_por_dos_metodos_suma_solo_sus_ventas_y_pagos(): void
    {
        $card = \App\Models\PaymentMethod::create(['label' => 'Tarjeta', 'color' => 'warning', 'is_active' => true]);
        \Illuminate\Support\Facades\Cache::flush();

        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00', [1000], 'cash');
        $this->createSaleFor($seller, '2026-11-11 09:00:00', [2000], 'transfer');
        $this->createSaleFor($seller, '2026-11-12 09:00:00', [4000], $card->code);
        $this->createPaymentFor($seller, '2026-11-13', 300, 'cash');
        $this->createPaymentFor($seller, '2026-11-13', 500, 'transfer');

        $this->actingAs($seller);

        $component = Livewire::test(SalesUserTickets::class, ['userId' => $seller->id, 'from' => '2026-11-01', 'to' => '2026-11-30'])
            ->assertSee('Tarjeta')
            ->assertSee('Todos los métodos')
            ->set('payments', ['cash', 'transfer'])
            ->assertSet('payments', ['cash', 'transfer'])
            ->assertSee('Efectivo, Transferencia');

        $totals = $component->instance()->totals;
        $this->assertSame(3000.0, (float) $totals['ventas_total']);
        $this->assertSame(800.0, (float) $totals['payments_total']);
        $this->assertSame(2200.0, (float) $totals['saldo']);

        $component->set('payments', ['cash'])->assertSet('payments', ['cash']);
        $this->assertSame(1000.0, (float) $component->instance()->totals['ventas_total']);
        $this->assertSame(300.0, (float) $component->instance()->totals['payments_total']);
    }

    public function test_el_boton_del_desplegable_nombra_hasta_dos_metodos_y_cuenta_los_demas(): void
    {
        \App\Models\PaymentMethod::create(['label' => 'Posnet', 'color' => 'warning', 'is_active' => true]);
        \Illuminate\Support\Facades\Cache::flush();

        $seller = $this->createSeller();
        $this->actingAs($seller);

        $component = Livewire::test(SalesUserTickets::class, ['userId' => $seller->id]);

        $this->assertSame('Todos los métodos', $component->instance()->paymentsButtonLabel());

        $component->set('payments', ['cash']);
        $this->assertSame('Efectivo', $component->instance()->paymentsButtonLabel());

        $component->set('payments', ['cash', 'transfer']);
        $this->assertSame('Efectivo, Transferencia', $component->instance()->paymentsButtonLabel());

        $component->set('payments', ['cash', 'transfer', 'posnet']);
        $this->assertSame('3 métodos', $component->instance()->paymentsButtonLabel());
    }

    public function test_los_metodos_enviados_desde_el_navegador_se_normalizan(): void
    {
        $seller = $this->createSeller();
        $this->actingAs($seller);

        Livewire::test(SalesUserTickets::class, ['userId' => $seller->id])
            ->set('payments', ['cash', '', 'cash', 'transfer'])
            ->assertSet('payments', ['cash', 'transfer']);
    }

    public function test_restablecer_vuelve_a_todos_los_metodos_y_al_mes_actual(): void
    {
        $seller = $this->createSeller();
        $this->actingAs($seller);

        Livewire::test(SalesUserTickets::class, ['userId' => $seller->id, 'from' => '2026-01-01', 'to' => '2026-01-31', 'payments' => ['cash']])
            ->assertSet('payments', ['cash'])
            ->call('resetFilters')
            ->assertSet('payments', [])
            ->assertSet('from', '2026-11-01')
            ->assertSet('to', '2026-11-30');
    }

    public function test_el_nombre_del_export_incluye_los_metodos_elegidos(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00');
        $this->actingAs($seller);

        Livewire::test(SalesUserTickets::class, ['userId' => $seller->id, 'from' => '2026-11-01', 'to' => '2026-11-30', 'payments' => ['cash', 'transfer']])
            ->call('exportExcel')
            ->assertFileDownloaded("Detalle_{$seller->username}_01-11-2026_30-11-2026_cash-transfer.xlsx");
    }

    public function test_un_metodo_personalizado_se_muestra_con_su_color_en_filas_y_desglose(): void
    {
        $posnet = \App\Models\PaymentMethod::create(['label' => 'Posnet', 'color' => 'rose', 'is_active' => true]);
        \Illuminate\Support\Facades\Cache::flush();

        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00', [5000], $posnet->code);
        $this->actingAs($seller);

        $html = Livewire::test(SalesUserTickets::class, ['userId' => $seller->id, 'from' => '2026-11-01', 'to' => '2026-11-30'])->html();

        $classes = \App\Models\PaymentMethod::PALETTE['rose']['classes'];

        // Una vez en la fila de la venta y otra en el desglose por método.
        $this->assertGreaterThanOrEqual(2, substr_count($html, $classes));
    }

    public function test_un_usuario_sin_permisos_de_ventas_no_monta_el_detalle(): void
    {
        $seller = $this->createSeller();
        \Spatie\Permission\Models\Role::findByName(Permissions::ROLE_SELLER)->revokePermissionTo('sales.view_own');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($seller->fresh());

        Livewire::test(SalesUserTickets::class, ['userId' => $seller->id])
            ->assertForbidden();
    }
}
