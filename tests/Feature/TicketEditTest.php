<?php

namespace Tests\Feature;

use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Bus;
use App\Models\Clients;
use App\Models\Location;
use App\Models\Passenger;
use App\Models\PaymentMethod;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Sale;
use App\Models\Schedule;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketEdit;
use App\Models\Trip;
use App\Models\User;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Support\Exceptions\Cancel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Edición de boletos (corrección de errores de venta).
 *
 * Cubre: permiso `tickets.edit` (nadie lo trae por defecto), el guard
 * server-side de la página, la auditoría en `ticket_edits`, el DNI sin
 * unique (el pasajero puede tener muchos boletos) con validación de cliente
 * baneado, la confirmación con contraseña opt-in (Configuración) y que la
 * edición NO toque viaje/asiento.
 */
class TicketEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;

    protected Bus $bus;

    protected Location $oran;

    protected Location $salta;

    protected Schedule $schedule;

    protected PaymentMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        // La caché de Setting es estática dentro del proceso de PHPUnit: sin
        // esto, una prueba que activa la contraseña de edición la "hereda"
        // a las siguientes aunque la base se refresque.
        Setting::flushCache();

        Permissions::syncToDatabase();

        $this->seller = User::factory()->create([
            'password' => Hash::make('secreto123'),
        ])->assignRole(Permissions::ROLE_SELLER);
        $this->method = PaymentMethod::create(['label' => 'Efectivo', 'is_active' => true]);
        $this->oran = Location::create(['name' => 'Orán', 'is_active' => true]);
        $this->salta = Location::create(['name' => 'Salta', 'is_active' => true]);
        $this->bus = Bus::create(['name' => 'Linea 1', 'plate' => 'AAA111', 'seat_count' => 4, 'floors' => 1]);
        foreach ([1, 2, 3, 4] as $n) {
            Seat::create(['bus_id' => $this->bus->id, 'seat_number' => (string) $n, 'is_active' => true, 'floor' => '1']);
        }
        $route = Route::create(['name' => 'Orán - Salta T', 'bus_id' => $this->bus->id, 'is_active' => true]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->oran->id, 'stop_order' => 1]);
        RouteStop::create(['route_id' => $route->id, 'location_id' => $this->salta->id, 'stop_order' => 2]);
        $this->schedule = Schedule::create([
            'route_id' => $route->id,
            'name' => 'Mañana',
            'departure_time' => '08:00',
            'arrival_time' => '12:00',
            'is_active' => true,
        ]);
    }

    /** Vende un boleto "normal" (con viaje y asiento) para el seller. */
    protected function createTicket(): Ticket
    {
        $trip = Trip::findOrCreateForBooking($this->schedule->id, '2030-01-01', $this->oran->id, $this->salta->id)['trip'];
        $passenger = Passenger::create([
            'first_name' => 'Pedro',
            'last_name' => 'Gomez',
            'dni' => '30000111',
            'phone_number' => '3875550001',
            'passenger_type' => 'adult',
        ]);
        $sale = Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]);

        return Ticket::create([
            'sale_id' => $sale->id,
            'trip_id' => $trip->id,
            'passenger_id' => $passenger->id,
            'seat_id' => Seat::where('bus_id', $this->bus->id)->where('seat_number', '1')->first()->id,
            'price' => 10000,
            'payment_method' => $this->method->code,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Permiso
    // ------------------------------------------------------------------

    public function test_nadie_tiene_tickets_edit_por_defecto(): void
    {
        foreach ([Permissions::ROLE_ADMIN, Permissions::ROLE_SELLER] as $roleName) {
            $user = User::factory()->create()->assignRole($roleName);

            $this->assertFalse(
                $user->can('tickets.edit'),
                "El rol {$roleName} no debería tener tickets.edit por defecto",
            );
        }
    }

    public function test_el_permiso_esta_en_el_catalogo_pero_no_en_defaults(): void
    {
        $this->assertContains('tickets.edit', Permissions::all());
        $this->assertNotContains('tickets.edit', Permissions::defaultsFor(Permissions::ROLE_ADMIN));
        $this->assertNotContains('tickets.edit', Permissions::defaultsFor(Permissions::ROLE_SELLER));
    }

    public function test_sin_permiso_falla_el_acceso(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->seller)
            ->get(TicketResource::getUrl('edit', ['record' => $ticket]))
            ->assertForbidden();

        // Y la policy `edit` rechaza explícitamente sin el permiso
        $this->assertFalse($this->seller->can('edit', $ticket));
    }

    public function test_con_permiso_se_accede_a_la_pagina(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        $this->actingAs($this->seller)
            ->get(TicketResource::getUrl('edit', ['record' => $ticket]))
            ->assertSuccessful();
    }

    // ------------------------------------------------------------------
    // DNI: sin unique, con validación de cliente baneado
    // ------------------------------------------------------------------

    public function test_el_dni_de_un_pasajero_no_es_unico_por_boleto(): void
    {
        // Mismo DNI en dos boletos de COMPRAS DISTINTAS es válido: un pasajero
        // puede comprar tantos boletos como quiera.
        $ticket1 = $this->createTicket();

        $passenger2 = Passenger::create([
            'first_name' => 'Pedro',
            'last_name' => 'Gomez',
            'dni' => '30000111', // mismo DNI
            'phone_number' => '3875550001',
            'passenger_type' => 'adult',
        ]);
        $trip = Trip::findOrCreateForBooking($this->schedule->id, '2030-01-02', $this->oran->id, $this->salta->id)['trip'];
        $sale2 = Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]);
        $ticket2 = Ticket::create([
            'sale_id' => $sale2->id,
            'trip_id' => $trip->id,
            'passenger_id' => $passenger2->id,
            'seat_id' => Seat::where('bus_id', $this->bus->id)->where('seat_number', '2')->first()->id,
            'price' => 10000,
            'payment_method' => $this->method->code,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
        ]);

        $this->seller->givePermissionTo('tickets.edit');

        // Editar el segundo boleto SIN cambios de DNI (mantiene su DNI repetido)
        // debe funcionar: no hay constraint unique de pasajero por DNI.
        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket2->getKey()])
            ->set('data.phone_number', '3875559999')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('3875559999', (string) $passenger2->fresh()->phone_number);
        $this->assertSame('30000111', (string) $passenger2->fresh()->dni);
    }

    public function test_no_se_puede_editar_un_boleto_con_dni_de_cliente_baneado(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        Clients::create([
            'dni' => '30000111',
            'nombre' => 'Pedro',
            'apellido' => 'Gomez',
            'telefono' => '3875550001',
            'can_buy' => false,
            'comments' => 'Impago reiterado',
        ]);

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.payment_method', $this->method->code)
            ->call('save')
            ->assertNotified();

        // No se guardó ni auditó nada
        $this->assertSame(0, TicketEdit::where('ticket_id', $ticket->id)->count(), 'No debe auditar (ni guardar) con el DNI baneado.');
        $this->assertSame($ticket->payment_method, $ticket->fresh()->payment_method);
    }

    public function test_cliente_no_baneado_con_dni_existente_se_puede_editar(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        // Cliente del padrón HABILITADO con ese DNI: la edición sigue normal.
        Clients::create([
            'dni' => '30000111',
            'nombre' => 'Pedro',
            'apellido' => 'Gomez',
            'telefono' => '3875550001',
            'can_buy' => true,
        ]);

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.payment_method', $this->method->code)
            ->set('data.first_name', 'Juan Carlos')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Juan Carlos', $ticket->fresh()->passenger->first_name);
    }

    // ------------------------------------------------------------------
    // Llenado del formulario (precio y método de pago)
    // ------------------------------------------------------------------

    public function test_al_abrir_la_edicion_trae_el_precio_y_el_metodo_de_pago(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        $component = Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()]);

        $this->assertEquals(10000, (float) $component->get('data.price'));
        $this->assertSame((string) $ticket->payment_method, (string) $component->get('data.payment_method'));

        // Y la página real (HTTP) hidrata el form con el precio del boleto:
        // el snapshot de Livewire viaja con el estado que consumen los inputs.
        $this->actingAs($this->seller)
            ->get(TicketResource::getUrl('edit', ['record' => $ticket]))
            ->assertSuccessful()
            ->assertSee('price&quot;:10000', false);
    }

    public function test_un_metodo_de_pago_eliminado_sigue_visible_y_no_se_pierde(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();
        $code = (string) $ticket->payment_method;

        // El método se elimina después de la venta: el boleto histórico debe
        // seguir mostrándolo y editarlo no debe borrarlo.
        $this->method->delete();
        PaymentMethod::clearCache();

        $component = Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()]);

        $this->assertSame($code, (string) $component->get('data.payment_method'));

        // El código del boleto sigue entre las opciones (marcado como inactivo),
        // así el Select no queda vacío ni falla la validación `in`.
        $options = \App\Filament\Resources\Tickets\Pages\EditTicket\EditTicketForm::paymentMethodOptions($ticket);
        $this->assertArrayHasKey($code, $options);
        $this->assertStringContainsString('inactivo', $options[$code]);

        $component->set('data.first_name', 'Otro Nombre')->call('save')->assertHasNoErrors();

        $this->assertSame('Otro Nombre', $ticket->fresh()->passenger->first_name);
        $this->assertSame($code, (string) $ticket->fresh()->payment_method);
        $this->assertDatabaseMissing('ticket_edits', [
            'ticket_id' => $ticket->id,
            'field' => 'payment_method',
        ]);
    }

    // ------------------------------------------------------------------
    // Vista del boleto: tarjeta "Detalles de venta"
    // ------------------------------------------------------------------

    public function test_la_vista_del_boleto_muestra_los_detalles_de_venta(): void
    {
        $ticket = $this->createTicket();

        $this->actingAs($this->seller)
            ->get(TicketResource::getUrl('view', ['record' => $ticket]))
            ->assertSuccessful()
            ->assertSee('Detalles de venta')
            ->assertSee('N° Venta')
            ->assertSee('Fecha de venta')
            ->assertSee('Método de pago')
            ->assertSee('Vendedor')
            ->assertSee('Efectivo')
            ->assertSee('10.000');
    }

    public function test_la_vista_del_boleto_muestra_el_historial_de_ediciones(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        // Edición real desde la página: nombre + precio, con motivo.
        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.first_name', 'Juan Carlos')
            ->set('data.price', '12500')
            ->set('data.edit_reason', 'Se cargó mal el nombre en ventanilla')
            ->call('save');

        $response = $this->actingAs($this->seller)
            ->get(TicketResource::getUrl('view', ['record' => $ticket]));

        $response->assertOk();
        $response->assertSee('Historial de ediciones');
        $response->assertSee('2 campos corregidos en 1 edición.');
        $response->assertSee('Realizado por:');
        $response->assertSee($this->seller->name);
        $response->assertSee('Se cargó mal el nombre en ventanilla');
        $response->assertSee('Antes');
        $response->assertSee('Después');
        $response->assertSee('$12.500');
    }

    // ------------------------------------------------------------------
    // Guardado y auditoría
    // ------------------------------------------------------------------

    public function test_editar_pasajero_precio_y_metodo_queda_auditado(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();
        $metodoOriginal = $ticket->payment_method;
        $tripOriginal = $ticket->trip_id;
        $seatOriginal = $ticket->seat_id;
        $nuevoMetodo = PaymentMethod::create(['label' => 'Transferencia', 'is_active' => true]);

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.payment_method', $nuevoMetodo->code)
            ->set('data.first_name', 'Juan Carlos')
            ->set('data.dni', '30111222')
            ->set('data.phone_number', '3875550009')
            ->set('data.price', '12500')
            ->set('data.edit_reason', 'Se cargó mal el nombre en ventanilla')
            ->call('save')
            ->assertHasNoErrors();

        $ticket->refresh();

        // Datos actualizados
        $this->assertSame('Juan Carlos', $ticket->passenger->first_name);
        $this->assertSame('30111222', (string) $ticket->passenger->dni);
        $this->assertSame('3875550009', (string) $ticket->passenger->phone_number);
        $this->assertSame(12500.0, (float) $ticket->price);
        $this->assertSame($nuevoMetodo->code, $ticket->payment_method);

        // Auditoría: una fila por campo cambiado, con viejo/nuevo/motivo/usuario
        $edits = TicketEdit::where('ticket_id', $ticket->id)->orderBy('id')->get();
        $this->assertSame(['first_name', 'dni', 'phone_number', 'price', 'payment_method'], $edits->pluck('field')->all());
        $this->assertSame('Se cargó mal el nombre en ventanilla', $edits->first()->reason);
        $this->assertSame($this->seller->id, $edits->first()->user_id);

        $price = $edits->firstWhere('field', 'price');
        $this->assertSame('10000', $price->old_value);
        $this->assertSame('12500', $price->new_value);

        $pm = $edits->firstWhere('field', 'payment_method');
        $this->assertSame($metodoOriginal, $pm->old_value);
        $this->assertSame($nuevoMetodo->code, $pm->new_value);

        // La identificación de viaje NO se toca desde la edición
        $this->assertSame($tripOriginal, $ticket->trip_id);
        $this->assertSame($seatOriginal, $ticket->seat_id);
    }

    public function test_sin_cambios_no_graba_nada(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.payment_method', $ticket->payment_method)
            ->call('save')
            ->assertNotified();

        $this->assertSame(0, TicketEdit::where('ticket_id', $ticket->id)->count());
        $this->assertSame(10000.0, (float) $ticket->fresh()->price);
    }

    public function test_desmarcar_acompanante_borra_hijos_y_audita(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();
        $passenger = $ticket->passenger;

        $child = $passenger->children()->create([
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'dni' => '55555555',
            'passenger_type' => 'child',
        ]);
        $ticket->update(['travels_with_child' => true]);
        $childId = $child->id;

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.travels_with_child', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('passengers', ['id' => $childId]);
        $this->assertDatabaseHas('ticket_edits', [
            'ticket_id' => $ticket->id,
            'field' => 'travels_with_child',
            'old_value' => 'Sí',
            'new_value' => 'No',
        ]);
        $this->assertFalse((bool) $ticket->fresh()->travels_with_child);
    }

    // ------------------------------------------------------------------
    // Protección con contraseña (opt-in vía Configuración)
    // ------------------------------------------------------------------

    public function test_sin_setting_no_pide_contrasena(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.first_name', 'Sin Pass')
            ->call('save');

        // View-snapshot errors can appear for hidden fields: nada de esto debe
        // bloquear el guardado real (el pasajero queda editado).
        $this->assertSame('Sin Pass', $ticket->fresh()->passenger->first_name);
    }

    public function test_con_setting_contraseña_incorrecta_bloquea_guardado(): void
    {
        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_EDIT, '1');
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.first_name', 'Nueva Clave')
            ->call('save', 'clave-incorrecta')
            ->assertNotified('Contraseña incorrecta');

        $this->assertSame('Pedro', $ticket->fresh()->passenger->first_name);
        $this->assertSame(0, TicketEdit::where('ticket_id', $ticket->id)->count());
    }

    public function test_con_setting_contraseña_correcta_permite_guardar(): void
    {
        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_EDIT, '1');
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.first_name', 'Password OK')
            ->call('save', 'secreto123');

        $this->assertSame('Password OK', $ticket->fresh()->passenger->first_name);
        $this->assertSame(1, TicketEdit::where('ticket_id', $ticket->id)->count());
    }

    public function test_la_contrasena_se_pide_en_el_modal_y_no_en_el_form(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        $component = Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()]);

        // Sin el setting, el modal no pide nada.
        $this->assertSame([], $component->instance()->confirmationFormComponents());

        Setting::set(Setting::REQUIRE_PASSWORD_TICKET_EDIT, '1');

        $component = Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()]);

        $fields = $component->instance()->confirmationFormComponents();
        $this->assertCount(1, $fields, 'El modal debe pedir la contraseña.');
        $this->assertSame('confirm_password', $fields[0]->getName());

        // La acción de guardado es la que monta el modal con esos campos.
        $actionNames = array_map(fn ($action) => $action->getName(), $component->instance()->getFormActions());
        $this->assertContains('save_edit', $actionNames);

        // Y NO vive en el formulario de la página: se pidió solo en el modal.
        foreach ($component->instance()->form->getFlatComponents(withHidden: true) as $field) {
            $this->assertStringNotContainsString('confirm_password', (string) $field->getStatePath());
        }
    }

    public function test_si_falla_la_edicion_se_cierra_el_modal_y_se_notifica_el_problema(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        $component = Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.first_name', 'Nombre Nuevo');

        // Fallo real de escritura: la auditoría no puede guardar su fila.
        TicketEdit::saving(function (): void {
            throw new \RuntimeException('La base de datos rechazó el cambio');
        });

        $cancelled = false;

        try {
            $component->instance()->saveFromConfirmation('', Action::make('save_edit'));
        } catch (Cancel $exception) {
            $cancelled = true;
        } finally {
            TicketEdit::flushEventListeners();
        }

        $this->assertTrue($cancelled, 'El modal debe cerrarse cuando la edición falla.');
        $component->assertNotified('No se pudo guardar la edición');

        // Y nada quedó a medio guardar (rollback del pasajero).
        $this->assertSame('Pedro', $ticket->fresh()->passenger->first_name);
        $this->assertSame(0, TicketEdit::where('ticket_id', $ticket->id)->count());
    }

    public function test_sin_cambios_el_modal_se_cierra_con_aviso(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        $component = Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()]);

        $cancelled = false;

        try {
            $component->instance()->saveFromConfirmation('', Action::make('save_edit'));
        } catch (Cancel $exception) {
            $cancelled = true;
        }

        $this->assertTrue($cancelled, 'Sin cambios el modal debe cerrarse.');
        $component->assertNotified('No hay cambios para guardar');
        $this->assertSame(0, TicketEdit::where('ticket_id', $ticket->id)->count());
    }

    public function test_los_errores_de_validacion_se_notifican_y_marcan_el_campo(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.first_name', '')
            ->call('save')
            ->assertNotified('No se pudo guardar la edición')
            ->assertHasErrors(['data.first_name' => 'El nombre del pasajero es obligatorio.']);
    }

    public function test_el_dni_se_valida_al_completarlo_sin_guardar(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        Clients::create([
            'dni' => '30111222',
            'nombre' => 'Ana',
            'apellido' => 'Perez',
            'telefono' => '3875550002',
            'can_buy' => false,
            'comments' => 'Impago reiterado',
        ]);

        $component = Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            // Simula completar el DNI y salir del campo (wire:model.blur).
            ->set('data.dni', '30111222')
            ->assertHasErrors(['data.dni']);

        $this->assertStringContainsString('está baneado', (string) $component->errors()->first('data.dni'));

        // Con un DNI válido el error desaparece, todo sin apretar Guardar.
        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.dni', '30000111')
            ->assertHasNoErrors(['data.dni']);

        $this->assertSame('30000111', (string) $ticket->fresh()->passenger->dni);
        $this->assertSame(0, TicketEdit::where('ticket_id', $ticket->id)->count());
    }

    // ------------------------------------------------------------------
    // Casos límite
    // ------------------------------------------------------------------

    public function test_ticket_pendiente_sin_viaje_se_puede_editar(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();
        $ticket->forceFill(['trip_id' => null, 'seat_id' => null])->save();

        Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $ticket->getKey()])
            ->set('data.first_name', 'Nombre Nuevo')
            ->call('save');

        $this->assertSame('Nombre Nuevo', $ticket->fresh()->passenger->first_name);
        $this->assertDatabaseHas('ticket_edits', [
            'ticket_id' => $ticket->id,
            'field' => 'first_name',
        ]);
    }

    public function test_tramo_de_vuelta_no_expone_precio_ni_metodo(): void
    {
        $this->seller->givePermissionTo('tickets.edit');
        $ticket = $this->createTicket();

        $returnLeg = Ticket::create([
            'sale_id' => $ticket->sale_id,
            'passenger_id' => $ticket->passenger_id,
            'price' => 0,
            'payment_method' => $ticket->payment_method,
            'is_round_trip' => true,
            'is_return_leg' => true,
            'origin_location_id' => $this->salta->id,
            'destination_location_id' => $this->oran->id,
        ]);

        $this->assertTrue(EditTicket::isReturnLegWithSharedPrice($returnLeg));
        $this->assertFalse(EditTicket::isReturnLegWithSharedPrice($ticket));
    }

    public function test_tramo_de_vuelta_muestra_el_precio_de_la_ida_sin_guardarlo(): void
    {
        $this->seller->givePermissionTo('tickets.edit');

        // Venta de ida y vuelta del mismo pasajero: la ida lleva el precio y
        // la vuelta queda en 0 (el precio "viaja" en el boleto de ida).
        $passenger = Passenger::create([
            'first_name' => 'Pedro',
            'last_name' => 'Gomez',
            'dni' => '30000111',
            'phone_number' => '3875550001',
            'passenger_type' => 'adult',
        ]);
        $sale = Sale::create(['user_id' => $this->seller->id, 'sale_date' => now()]);

        $ida = Ticket::create([
            'sale_id' => $sale->id,
            'passenger_id' => $passenger->id,
            'price' => 15000,
            'payment_method' => $this->method->code,
            'is_round_trip' => true,
            'is_return_leg' => false,
            'origin_location_id' => $this->oran->id,
            'destination_location_id' => $this->salta->id,
        ]);
        $vuelta = Ticket::create([
            'sale_id' => $sale->id,
            'passenger_id' => $passenger->id,
            'price' => 0,
            'payment_method' => $this->method->code,
            'is_round_trip' => true,
            'is_return_leg' => true,
            'origin_location_id' => $this->salta->id,
            'destination_location_id' => $this->oran->id,
        ]);

        $this->assertTrue(EditTicket::isReturnLegWithSharedPrice($vuelta));

        $component = Livewire::actingAs($this->seller)
            ->test(EditTicket::class, ['record' => $vuelta->getKey()]);

        // El campo muestra el precio real de la venta (el de la ida), no $0.
        $this->assertEquals(15000, (float) $component->get('data.price'));

        // Y ese valor no se guarda en la vuelta: sigue en 0 y sin auditoría.
        $component->set('data.first_name', 'Cambiado')->call('save')->assertHasNoErrors();

        $this->assertSame('Cambiado', $vuelta->fresh()->passenger->first_name);
        $this->assertSame(0.0, (float) $vuelta->fresh()->price);
        $this->assertSame(15000.0, (float) $ida->fresh()->price);
        $this->assertDatabaseMissing('ticket_edits', [
            'ticket_id' => $vuelta->id,
            'field' => 'price',
        ]);
    }
}
