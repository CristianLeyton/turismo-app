<?php

namespace Tests\Feature;

use App\Filament\Clusters\Sales\Pages\MySales;
use App\Filament\Clusters\Sales\SalesCluster;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Sales\SalesResource;
use App\Livewire\SalesUserTickets;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MySalesPageTest extends TestCase
{
    use CreatesSalesRecords;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-15 10:00:00'));

        // Ocupa el id=1 (usuario de pruebas) para que no lo tome un vendedor.
        User::factory()->superAdmin()->create();
    }

    public function test_el_vendedor_abre_mis_ventas(): void
    {
        $seller = $this->createSeller();

        $this->actingAs($seller)
            ->get(MySales::getUrl())
            ->assertOk()
            ->assertSee('Mis ventas')
            ->assertSee('Tus boletos vendidos, pagos realizados y saldo')
            ->assertSeeLivewire(SalesUserTickets::class);
    }

    public function test_el_vendedor_ve_sus_datos_y_no_los_de_otro(): void
    {
        $seller = $this->createSeller();
        $other = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00', [12345]);
        $this->createSaleFor($other, '2026-11-10 09:00:00', [98765]);
        $this->createPaymentFor($seller, '2026-11-12', 2345);

        $this->actingAs($seller);

        $page = Livewire::test(MySales::class);
        $this->assertSame($seller->id, $page->instance()->getDetailParameters()['userId']);

        Livewire::test(SalesUserTickets::class, $page->instance()->getDetailParameters())
            ->assertSee('$12.345')
            ->assertSee('$2.345')
            ->assertSee('$10.000')
            ->assertDontSee('$98.765');
    }

    public function test_mis_ventas_abre_con_el_mes_actual(): void
    {
        $seller = $this->createSeller();
        $this->actingAs($seller);

        $parameters = Livewire::test(MySales::class)->instance()->getDetailParameters();

        $this->assertSame('2026-11-01', $parameters['from']);
        $this->assertSame('2026-11-30', $parameters['to']);
    }

    public function test_el_administrador_por_defecto_no_ve_mis_ventas(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get(MySales::getUrl())
            ->assertForbidden();

        $this->assertFalse(MySales::shouldRegisterNavigation());
    }

    public function test_un_vendedor_sin_otros_permisos_de_ventas_llega_a_mis_ventas_desde_el_cluster(): void
    {
        $seller = $this->createSeller();
        $this->actingAs($seller);

        $this->assertTrue(SalesCluster::canAccessClusteredComponents());
        $this->assertFalse(SalesResource::canViewAny());
        $this->assertFalse(PaymentResource::canViewAny());

        $this->get(SalesCluster::getUrl())
            ->assertRedirect(MySales::getUrl());
    }

    public function test_el_vendedor_exporta_solo_sus_datos(): void
    {
        $seller = $this->createSeller();
        $this->createSaleFor($seller, '2026-11-10 09:00:00');
        $this->actingAs($seller);

        $parameters = Livewire::test(MySales::class)->instance()->getDetailParameters();

        Livewire::test(SalesUserTickets::class, $parameters)
            ->call('exportPdf')
            ->assertFileDownloaded("Detalle_{$seller->username}_01-11-2026_30-11-2026.pdf");

        Livewire::test(SalesUserTickets::class, $parameters)
            ->call('exportExcel')
            ->assertFileDownloaded("Detalle_{$seller->username}_01-11-2026_30-11-2026.xlsx");
    }
}
