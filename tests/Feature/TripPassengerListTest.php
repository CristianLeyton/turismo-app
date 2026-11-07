<?php

namespace Tests\Feature;

use App\Filament\Resources\Trips\Pages\ManageTrips;
use App\Filament\Resources\Trips\TripResource;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripExcelService;
use App\Services\TripPdfService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Lista de pasajeros de un viaje para quien controla la subida y bajada:
 * orden por parada, agrupado, filtros, "Viaja con" en el titular y
 * teléfono para llamar (spec trip-passenger-list, cambio
 * control-pasajeros-viaje).
 */
class TripPassengerListTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;

    private Route $route;

    private Location $oran;

    private Location $pichanal;

    private Location $salta;

    private Ticket $anaTicket;

    private Ticket $luisTicket;

    private Ticket $carlaTicket;

    protected function setUp(): void
    {
        parent::setUp();

        // Observaciones apagadas: la columna Teléfono se ve.
        Setting::flushCache();
        Setting::set(Setting::OBSERVACIONES_VENTA, '0');

        $this->oran = Location::create(['name' => 'Orán P', 'is_active' => true]);
        $this->pichanal = Location::create(['name' => 'Pichanal P', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta P', 'is_active' => true]);

        $bus = Bus::create(['name' => 'Coche P', 'plate' => 'PPP111', 'seat_count' => 12, 'floors' => 1]);
        $seats = collect(['2', '3', '10'])->mapWithKeys(fn (string $number): array => [
            $number => Seat::create(['bus_id' => $bus->id, 'seat_number' => $number, 'is_active' => true, 'floor' => '1']),
        ]);

        $this->route = Route::create(['name' => 'Orán - Salta P', 'bus_id' => $bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $this->route->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $this->route->id, 'location_id' => $this->pichanal->id, 'stop_order' => 2]);
        RouteStop::create(['route_id' => $this->route->id, 'location_id' => $this->salta->id, 'stop_order' => 3]);

        $schedule = Schedule::create([
            'route_id' => $this->route->id,
            'name' => 'Mañana P',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);

        $this->trip = Trip::create([
            'route_id' => $this->route->id,
            'schedule_id' => $schedule->id,
            'bus_id' => $bus->id,
            'trip_date' => '2030-10-01',
        ]);

        $sale = Sale::create([
            'user_id' => User::factory()->vendedor()->create()->id,
            'sale_date' => now(),
            'total_amount' => 45000,
        ]);

        // Se cargan desordenados a propósito: Pichanal primero, Orán asiento 10 y después el 2.
        $this->carlaTicket = $this->ticket($sale, $seats['3'], $this->pichanal, $this->salta, [
            'first_name' => 'Carla', 'last_name' => 'Ruiz', 'dni' => '33444555', 'phone_number' => '3874441111',
        ]);

        $ana = Passenger::create([
            'first_name' => 'Ana', 'last_name' => 'Gómez', 'dni' => '28333444',
            'phone_number' => '3815550001', 'passenger_type' => 'adult',
        ]);
        Passenger::create([
            'first_name' => 'Tomás', 'last_name' => 'Gómez', 'dni' => '50111222',
            'passenger_type' => 'child', 'parent_passenger_id' => $ana->id,
        ]);
        $this->anaTicket = Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $this->trip->id,
            'seat_id' => $seats['10']->id,
            'passenger_id' => $ana->id,
            'travels_with_child' => true,
            'travels_with_pets' => true,
            'pet_count' => 2,
            'pet_names' => 'Firulais, Toby',
            'is_round_trip' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
            'price' => 15000,
        ]);

        $this->luisTicket = $this->ticket($sale, $seats['2'], $this->oran, $this->pichanal, [
            'first_name' => 'Luis', 'last_name' => 'Díaz', 'dni' => '31555666', 'phone_number' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    // ====================== Orden ======================

    public function test_ordena_por_parada_de_subida_y_asiento_numerico(): void
    {
        $this->assertSame(
            ['Díaz Luis', 'Gómez Ana', 'Gómez Tomás', 'Firulais, Toby', 'Ruiz Carla'],
            $this->rowNames(),
        );
    }

    public function test_los_acompanantes_van_debajo_de_su_titular(): void
    {
        $rows = $this->trip->getPassengersWithDetails()->values();

        $this->assertSame(['adult', 'adult', 'child', 'pet', 'adult'], $rows->pluck('type')->all());
        $this->assertSame($this->anaTicket->id, $rows[2]['ticket_id']);
        $this->assertSame($this->anaTicket->id, $rows[3]['ticket_id']);
    }

    public function test_un_boleto_cuya_parada_de_origen_se_elimino_aparece_al_final(): void
    {
        $this->stop($this->oran)->delete();

        $this->assertSame(
            ['Ruiz Carla', 'Díaz Luis', 'Gómez Ana', 'Gómez Tomás', 'Firulais, Toby'],
            $this->rowNames(),
        );
    }

    public function test_un_boleto_con_origen_que_nunca_fue_parada_sigue_apareciendo(): void
    {
        $metan = Location::create(['name' => 'Metán P', 'is_active' => true]);
        $this->carlaTicket->update(['origin_location_id' => $metan->id]);

        $names = $this->rowNames();

        $this->assertCount(5, $names);
        $this->assertSame('Ruiz Carla', end($names));
    }

    public function test_una_parada_eliminada_y_restaurada_no_duplica_pasajeros(): void
    {
        $stop = $this->stop($this->oran);
        $stop->delete();
        $stop->restore();

        $this->assertSame(
            ['Díaz Luis', 'Gómez Ana', 'Gómez Tomás', 'Firulais, Toby', 'Ruiz Carla'],
            $this->rowNames(),
        );
    }

    // ====================== Datos por fila ======================

    public function test_las_paradas_se_numeran_por_posicion_en_el_recorrido(): void
    {
        $this->stop($this->pichanal)->delete();

        $this->assertSame(
            [[1, 'Orán P'], [2, 'Salta P']],
            $this->trip->fresh()->passengerListStops()->map(fn (array $stop): array => [$stop['order'], $stop['name']])->all(),
        );
    }

    public function test_cada_fila_trae_la_posicion_de_su_origen_y_destino(): void
    {
        $rows = $this->trip->getPassengersWithDetails();
        $carla = $rows->firstWhere('name', 'Ruiz Carla');
        $tomas = $rows->firstWhere('name', 'Gómez Tomás');

        $this->assertSame([2, 3], [$carla['origin_order'], $carla['destination_order']]);
        $this->assertSame([1, 3], [$tomas['origin_order'], $tomas['destination_order']]);
        $this->assertSame($this->oran->id, $tomas['origin_location_id']);
    }

    public function test_el_origen_fuera_del_recorrido_no_tiene_posicion(): void
    {
        $this->stop($this->pichanal)->delete();

        $carla = $this->trip->fresh()->getPassengersWithDetails()->firstWhere('name', 'Ruiz Carla');

        $this->assertNull($carla['origin_order']);
    }

    public function test_el_titular_trae_sus_acompanantes_y_el_texto_viaja_con(): void
    {
        $ana = $this->trip->getPassengersWithDetails()->firstWhere('name', 'Gómez Ana');

        $this->assertSame([
            ['type' => 'child', 'name' => 'Gómez Tomás'],
            ['type' => 'pet', 'name' => 'Firulais, Toby', 'count' => 2],
        ], $ana['companions']);
        $this->assertSame('Gómez Tomás (menor), Firulais, Toby (2 mascotas)', $ana['travels_with']);
    }

    public function test_un_titular_sin_acompanantes_no_tiene_viaja_con(): void
    {
        $luis = $this->trip->getPassengersWithDetails()->firstWhere('name', 'Díaz Luis');

        $this->assertSame([], $luis['companions']);
        $this->assertNull($luis['travels_with']);
    }

    public function test_los_acompanantes_conservan_el_nombre_del_titular_para_el_excel(): void
    {
        $rows = $this->trip->getPassengersWithDetails();

        $this->assertSame('Gómez Ana', $rows->firstWhere('type', 'child')['parent_name']);
        $this->assertSame('Gómez Ana', $rows->firstWhere('type', 'pet')['parent_name']);
    }

    public function test_el_texto_de_busqueda_va_sin_tildes_y_en_minusculas(): void
    {
        $ana = $this->trip->getPassengersWithDetails()->firstWhere('name', 'Gómez Ana');

        $this->assertStringContainsString('gomez ana', $ana['search']);
        $this->assertStringContainsString('28333444', $ana['search']);
        $this->assertStringContainsString('10', $ana['search']);
        $this->assertStringContainsString((string) $this->anaTicket->id, $ana['search']);
    }

    public function test_el_texto_de_busqueda_unifica_espacios_repetidos(): void
    {
        $this->luisTicket->passenger->update(['first_name' => 'Luis  Alberto ']);

        $luis = $this->trip->fresh()->getPassengersWithDetails()->firstWhere('dni', '31555666');

        $this->assertStringStartsWith('diaz luis alberto 31555666', $luis['search']);
    }

    public function test_el_telefono_se_normaliza_con_codigo_de_pais(): void
    {
        $rows = $this->trip->getPassengersWithDetails();

        $this->assertSame('543815550001', $rows->firstWhere('name', 'Gómez Ana')['phone_e164']);
        $this->assertSame('', $rows->firstWhere('name', 'Díaz Luis')['phone_e164']);
    }

    // ====================== Acción "Ver detalles" ======================

    public function test_el_modal_ver_detalles_muestra_la_lista_de_pasajeros(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageTrips::class)
            ->mountAction(TestAction::make('view_details')->table($this->trip))
            ->assertMountedActionModalSee(['Gómez Ana', 'Ruiz Carla', '1. Orán P']);
    }

    // ====================== Modal: resumen y agrupado ======================

    public function test_el_modal_no_muestra_el_resumen_de_subidas_y_bajadas(): void
    {
        $html = $this->renderModal();

        $this->assertStringNotContainsString('Subidas y bajadas por parada', $html);
        $this->assertStringNotContainsString('Bajan', $html);
    }

    public function test_el_agrupado_se_puede_apagar_y_se_recuerda(): void
    {
        $html = $this->renderModal();

        $this->assertStringContainsString('Agrupar por parada', $html);
        $this->assertStringContainsString('data-switch="group-by-stop"', $html);
        // Encendido por defecto y guardado en el navegador del usuario.
        $this->assertStringContainsString("groupByStop: \$persist(true).as('trip-passengers-group-by-stop')", $html);
        // Apagado oculta solo los encabezados: cada uno depende del switch.
        $this->assertSame(
            substr_count($html, 'data-group-heading'),
            substr_count($html, 'x-show="groupByStop" data-group-heading'),
        );
        $this->assertSame(2, substr_count($html, 'data-group-heading'));
    }

    public function test_el_modal_agrupa_por_parada_de_subida_numerada(): void
    {
        $html = $this->renderModal();

        $this->assertSeeInOrderHtml($html, [
            'data-group="1"', '1. Orán P', 'suben 4', 'Díaz Luis', 'Gómez Ana',
            'data-group="2"', '2. Pichanal P', 'suben 1', 'Ruiz Carla',
        ]);
        $this->assertStringNotContainsString('data-group="3"', $html, 'Salta no tiene subidas: no lleva grupo.');
    }

    public function test_los_origenes_fuera_del_recorrido_van_en_un_grupo_final_sin_numero(): void
    {
        $this->stop($this->pichanal)->delete();

        $html = $this->renderModal($this->trip->fresh());

        $this->assertSeeInOrderHtml($html, ['data-group="1"', 'Gómez Ana', 'data-group="out"', 'Fuera del recorrido', 'Ruiz Carla']);
    }

    // ====================== Modal: filtros y buscador ======================

    public function test_los_filtros_ofrecen_las_paradas_numeradas_en_orden_del_recorrido(): void
    {
        $html = $this->renderModal();

        foreach (['origin', 'destination'] as $filter) {
            $this->assertMatchesRegularExpression(
                '/data-filter="'.$filter.'"[^>]*>\s*<option value="">[^<]*todas<\/option>\s*'
                .'<option value="'.$this->oran->id.'">1\. Orán P<\/option>\s*'
                .'<option value="'.$this->pichanal->id.'">2\. Pichanal P<\/option>\s*'
                .'<option value="'.$this->salta->id.'">3\. Salta P<\/option>/u',
                $html,
            );
        }
    }

    public function test_cada_fila_lleva_los_datos_para_filtrar(): void
    {
        $html = $this->renderModal();

        $this->assertMatchesRegularExpression(
            '/data-ticket="'.$this->carlaTicket->id.'"\s+data-origin="'.$this->pichanal->id.'"\s+data-destination="'.$this->salta->id.'"\s+data-search="ruiz carla 33444555 3 '.$this->carlaTicket->id.'"/',
            $html,
        );
        $this->assertStringContainsString('x-show="isVisible('.$this->carlaTicket->id.')"', $html);
    }

    public function test_el_modal_tiene_buscador_y_estado_vacio(): void
    {
        $html = $this->renderModal();

        $this->assertStringContainsString('Buscar por nombre, DNI, asiento o N° de boleto', $html);
        $this->assertStringContainsString('Ningún pasajero coincide con los filtros.', $html);
        $this->assertStringContainsString('Limpiar filtros', $html);
    }

    // ====================== Modal: "Viaja con" ======================

    public function test_el_viaja_con_va_en_la_fila_del_titular(): void
    {
        $html = $this->renderModal();

        $this->assertSeeInOrderHtml($html, [
            'Gómez Ana', 'Viaja con: Gómez Tomás (menor), Firulais, Toby (2 mascotas)',
            'Menor', 'Gómez Tomás',
            'Mascota', 'Firulais, Toby',
        ]);
        $this->assertStringNotContainsString('Viaja con: Gómez Ana', $html);
        $this->assertStringNotContainsString('Mascota de:', $html);
    }

    // ====================== Modal: teléfono ======================

    public function test_el_telefono_se_puede_llamar_y_escribir_por_whatsapp(): void
    {
        $html = $this->renderModal();

        $this->assertStringContainsString('href="tel:3815550001"', $html);
        $this->assertStringContainsString('href="https://wa.me/543815550001"', $html);
        $this->assertStringContainsString('title="Escribir por WhatsApp"', $html);
    }

    public function test_el_boton_de_whatsapp_usa_el_icono_de_whatsapp(): void
    {
        $this->assertMatchesRegularExpression(
            '/href="https:\/\/wa\.me\/543815550001"[^>]*>\s*<svg[^>]*data-icon="whatsapp"/',
            $this->renderModal(),
        );
    }

    public function test_en_el_celular_el_dni_va_al_lado_del_nombre(): void
    {
        $this->assertMatchesRegularExpression(
            '/<span class="font-medium">Gómez Ana<\/span>\s*<span class="[^"]*md:hidden[^"]*">DNI 28333444<\/span>/u',
            $this->renderModal(),
        );
    }

    public function test_en_el_celular_el_recorrido_completo_va_al_final_de_la_tarjeta(): void
    {
        $this->assertMatchesRegularExpression(
            '/<td class="order-last w-full[^"]*">\s*<span[^>]*>Orán P<\/span>\s*<span class="md:hidden">\s*<span[^>]*>→<\/span>\s*<span[^>]*>Salta P<\/span>/u',
            $this->renderModal(),
        );
    }

    public function test_un_pasajero_sin_telefono_no_tiene_links(): void
    {
        $this->anaTicket->passenger->update(['phone_number' => null]);
        $this->carlaTicket->passenger->update(['phone_number' => null]);

        $html = $this->renderModal($this->trip->fresh());

        $this->assertStringContainsString('Teléfono', $html);
        $this->assertStringNotContainsString('href="tel:', $html);
        $this->assertStringNotContainsString('wa.me', $html);
    }

    public function test_con_observaciones_prendidas_el_telefono_sigue_oculto(): void
    {
        Setting::set(Setting::OBSERVACIONES_VENTA, '1');

        $html = $this->renderModal();

        $this->assertStringNotContainsString('Teléfono', $html);
        $this->assertStringNotContainsString('href="tel:', $html);
        $this->assertStringNotContainsString('wa.me', $html);
    }

    // ====================== PDF ======================

    public function test_el_pdf_lleva_el_viaja_con_en_el_titular(): void
    {
        $html = $this->renderPdf();

        $this->assertStringContainsString('Viaja con: Gómez Tomás (menor), Firulais, Toby (2 mascotas)', $html);
        $this->assertStringNotContainsString('Viaja con: Gómez Ana', $html);
        $this->assertStringNotContainsString('Mascota de:', $html);
        $this->assertSeeInOrderHtml($html, ['Gómez Ana', 'class="companion-tag">Menor<', 'Gómez Tomás', 'class="companion-tag">Mascota<', 'Firulais, Toby']);
    }

    public function test_el_pdf_separa_por_parada_de_subida(): void
    {
        $this->assertSeeInOrderHtml($this->renderPdf(), [
            'class="group-row"', '1. Orán P', '— suben 4', 'Díaz Luis', 'Gómez Ana',
            'class="group-row"', '2. Pichanal P', '— suben 1', 'Ruiz Carla',
        ]);
    }

    public function test_el_pdf_muestra_raya_en_los_datos_vacios(): void
    {
        $this->assertMatchesRegularExpression('/Díaz Luis\s*<\/td>\s*<td>31555666<\/td>\s*<td>—<\/td>/u', $this->renderPdf());
    }

    public function test_el_pdf_se_genera(): void
    {
        $pdf = app(TripPdfService::class)->generateTripDetailsPdf($this->trip);

        $this->assertStringStartsWith('%PDF', $pdf->output());
    }

    public function test_la_accion_se_puede_evaluar_sin_viaje(): void
    {
        // Al abrir el modal desde el link de la fecha (?tableAction=view_details),
        // Filament evalúa la acción antes de tener el registro.
        $this->assertFalse(TripResource::viewDetailsAction()->isDisabled());
    }

    // ====================== Excel ======================

    public function test_el_excel_mantiene_la_columna_viaja_con_en_los_acompanantes(): void
    {
        $html = app(TripExcelService::class)->generateTripDetailsExcel($this->trip)->view()->render();

        $this->assertStringContainsString('<th>Viaja con</th>', $html);
        $this->assertStringContainsString('Gómez Ana (Menor)', $html);
        $this->assertStringContainsString('Gómez Ana (Mascota)', $html);
    }

    // ====================== Helpers ======================

    /**
     * @param  array{first_name: string, last_name: string, dni: string, phone_number: ?string}  $passenger
     */
    private function ticket(Sale $sale, Seat $seat, Location $origin, Location $destination, array $passenger): Ticket
    {
        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $this->trip->id,
            'seat_id' => $seat->id,
            'passenger_id' => Passenger::create([...$passenger, 'passenger_type' => 'adult'])->id,
            'is_round_trip' => false,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $destination->id,
            'price' => 15000,
        ]);
    }

    private function renderModal(?Trip $trip = null): string
    {
        $trip ??= $this->trip;

        return view('filament.trips.trip-details', [
            'trip' => $trip,
            'passengers' => $trip->getPassengersWithDetails(),
        ])->render();
    }

    private function renderPdf(): string
    {
        $passengers = $this->trip->getPassengersWithDetails();

        return view('pdf.trip-details', [
            'trip' => $this->trip,
            'passengers' => $passengers,
            'passengersCount' => $passengers->count(),
        ])->render();
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertSeeInOrderHtml(string $html, array $needles): void
    {
        $offset = 0;
        foreach ($needles as $needle) {
            $position = strpos($html, $needle, $offset);
            $this->assertNotFalse($position, "No se encontró «{$needle}» en el orden esperado.");
            $offset = $position + strlen($needle);
        }
    }

    private function stop(Location $location): RouteStop
    {
        return RouteStop::where('route_id', $this->route->id)->where('location_id', $location->id)->firstOrFail();
    }

    /**
     * @return list<string>
     */
    private function rowNames(): array
    {
        return $this->trip->fresh()->getPassengersWithDetails()->pluck('name')->values()->all();
    }
}
