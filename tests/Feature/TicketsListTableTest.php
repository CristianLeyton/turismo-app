<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Models\User;
use App\Support\Permissions;
use Filament\Actions\ActionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Estilo del listado de boletos del panel de administración: acciones de fila
 * en sus dos variantes (menú en celular, botones en escritorio) y el ícono de
 * WhatsApp compartido.
 */
class TicketsListTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    public function test_las_acciones_de_fila_tienen_menu_para_celular_y_botones_para_escritorio(): void
    {
        $actions = Livewire::test(ListTickets::class)->instance()->getTable()->getRecordActions();

        $menus = array_values(array_filter($actions, fn ($a) => $a instanceof ActionGroup));
        $this->assertCount(1, $menus);
        $this->assertStringContainsString('md:hidden', (string) $menus[0]->getExtraAttributeBag()->get('class'));

        $desktop = array_values(array_filter($actions, fn ($a) => ! $a instanceof ActionGroup));
        $this->assertSame(['view', 'download_pdf'], array_map(fn ($a) => $a->getName(), $desktop));

        foreach ($desktop as $action) {
            $this->assertStringContainsString('hidden md:inline-flex', (string) $action->getExtraAttributeBag()->get('class'));
            $this->assertNotEmpty($action->getExtraAttributeBag()->get('title'), 'Los botones de escritorio llevan tooltip.');
        }
    }

    public function test_el_menu_del_celular_ofrece_las_mismas_acciones_con_su_nombre(): void
    {
        $actions = Livewire::test(ListTickets::class)->instance()->getTable()->getRecordActions();
        $menu = collect($actions)->first(fn ($a) => $a instanceof ActionGroup);

        $items = collect($menu->getActions());

        $this->assertSame(['view', 'download_pdf'], $items->map(fn ($a) => $a->getName())->values()->all());
        $this->assertSame(['Ver boleto', 'Descargar'], $items->map(fn ($a) => $a->getLabel())->values()->all());
    }

    public function test_el_icono_de_whatsapp_es_un_componente_compartido(): void
    {
        $html = view('components.icons.whatsapp', ['attributes' => new ComponentAttributeBag(['class' => 'size-4'])])->render();

        $this->assertStringContainsString('data-icon="whatsapp"', $html);
        $this->assertStringContainsString('fill="currentColor"', $html);
        $this->assertStringContainsString('size-4', $html);
    }
}
