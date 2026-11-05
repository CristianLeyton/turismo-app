<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Bus;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Ticket;
use App\Models\Trip;
use App\Support\DuplicateTicketNotification;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DuplicateTicketNotificationTest extends TestCase
{
    /**
     * @return Collection<int, array{dni: string, ticket: Ticket, trip: Trip}>
     */
    private function conflicts(): Collection
    {
        $trip = new Trip(['trip_date' => '2026-12-01']);
        $trip->setRelation('schedule', new Schedule(['departure_time' => '08:00']));
        $trip->setRelation('bus', new Bus(['name' => 'Linea 1']));

        $origin = new Location(['name' => 'Tartagal']);
        $destination = new Location(['name' => 'Salta']);

        $adult = new Passenger(['first_name' => 'Ana', 'last_name' => 'Pérez', 'dni' => '30123456']);
        $adult->setRelation('children', collect());

        $parent = new Passenger(['first_name' => 'Luis', 'last_name' => 'Gómez', 'dni' => '30999888']);
        $parent->setRelation('children', collect([new Passenger(['first_name' => 'Nico', 'last_name' => 'Gómez', 'dni' => '45123456'])]));

        $first = new Ticket;
        $first->id = 812;
        $first->setRelation('passenger', $adult);
        $first->setRelation('origin', $origin);
        $first->setRelation('destination', $destination);
        $first->setRelation('seat', new Seat(['seat_number' => '12']));

        $second = new Ticket;
        $second->id = 813;
        $second->setRelation('passenger', $parent);
        $second->setRelation('origin', $origin);
        $second->setRelation('destination', $destination);
        $second->setRelation('seat', null);

        return collect([
            ['dni' => '30123456', 'ticket' => $first, 'trip' => $trip],
            ['dni' => '45123456', 'ticket' => $second, 'trip' => $trip],
        ]);
    }

    public function test_arma_titulo_cuerpo_y_una_accion_por_boleto(): void
    {
        $notification = DuplicateTicketNotification::make($this->conflicts(), 'Volver a la venta');

        $this->assertSame('2 pasajeros ya tienen boleto en este viaje', $notification->getTitle());
        $this->assertSame(
            'Ana Pérez (DNI 30123456) · boleto N°812 · Tartagal → Salta · 01/12/2026 08:00<br>Nico Gómez (DNI 45123456) · boleto N°813 · Tartagal → Salta · 01/12/2026 08:00',
            $notification->getBody(),
        );

        $actions = $notification->getActions();

        $this->assertSame(['ver_boleto_812', 'ver_boleto_813', 'volver'], array_map(fn ($action) => $action->getName(), $actions));

        $this->assertSame('Ver boleto N°812', $actions[0]->getLabel());
        $this->assertSame(TicketResource::getUrl('view', ['record' => 812]), $actions[0]->getUrl());
        $this->assertTrue($actions[0]->shouldOpenUrlInNewTab());
        $this->assertSame(TicketResource::getUrl('view', ['record' => 813]), $actions[1]->getUrl());

        $this->assertSame('Volver a la venta', $actions[2]->getLabel());
        $this->assertNull($actions[2]->getUrl());
        $this->assertTrue($actions[2]->shouldClose());
    }

    public function test_un_boleto_con_dos_dni_tiene_una_sola_accion(): void
    {
        $original = $this->conflicts();
        $conflicts = collect([
            $original[0],
            ['dni' => '45123456', 'ticket' => $original[0]['ticket'], 'trip' => $original[0]['trip']],
        ]);

        $actions = DuplicateTicketNotification::actionsFor($conflicts, 'Volver');

        $this->assertSame(['ver_boleto_812', 'volver'], array_map(fn ($action) => $action->getName(), $actions));
        $this->assertSame('Volver', $actions[1]->getLabel());
    }

    public function test_un_solo_pasajero_usa_el_titulo_en_singular_y_las_filas_marcan_al_menor(): void
    {
        $conflicts = $this->conflicts();

        $this->assertSame(DuplicateTicketNotification::TITLE, DuplicateTicketNotification::title($conflicts->take(1)));

        $rows = DuplicateTicketNotification::rows($conflicts);

        $this->assertFalse($rows[0]['is_child']);
        $this->assertSame('12', $rows[0]['seat']);
        $this->assertSame('Linea 1', $rows[0]['bus']);
        $this->assertTrue($rows[1]['is_child']);
        $this->assertSame('Nico Gómez', $rows[1]['name']);
        $this->assertNull($rows[1]['seat']);
    }

    public function test_la_notificacion_es_persistente(): void
    {
        $notification = DuplicateTicketNotification::make($this->conflicts(), 'Volver');

        $this->assertSame('persistent', $notification->getDuration());
        $this->assertSame('danger', $notification->getStatus());
    }
}
