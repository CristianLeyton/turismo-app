<?php

namespace Tests\Unit;

use App\Models\PaymentMethod;
use App\Models\TicketEdit;
use App\Models\User;
use App\Support\TicketEditHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lectura del historial de ediciones de un boleto: las filas de la base
 * (una por campo) se agrupan por edición y los valores se muestran legibles.
 */
class TicketEditHistoryTest extends TestCase
{
    use RefreshDatabase;

    /** Fila de edición en memoria (sin tocar la base). */
    private function edit(
        User $user,
        string $field,
        string $label,
        ?string $old,
        ?string $new,
        ?string $reason = null,
        string $at = '2026-10-13 10:00:00',
    ): TicketEdit {
        return (new TicketEdit)->forceFill([
            'user_id' => $user->id,
            'field' => $field,
            'field_label' => $label,
            'old_value' => $old,
            'new_value' => $new,
            'reason' => $reason,
            'created_at' => $at,
        ]);
    }

    public function test_agrupa_por_edicion_y_ordena_de_la_mas_reciente_a_la_mas_antigua(): void
    {
        $user = User::factory()->create(['name' => 'Agustina Magali', 'username' => 'agustina']);

        $edits = collect([
            $this->edit($user, 'first_name', 'Nombre', 'Pedro', 'Juan', 'Se cargó mal el nombre', '2026-10-13 10:00:00'),
            $this->edit($user, 'price', 'Precio', '10000', '12500', 'Se cargó mal el nombre', '2026-10-13 10:00:00'),
            $this->edit($user, 'dni', 'DNI', '30000111', '30111222', null, '2026-10-12 09:00:00'),
        ]);

        $groups = TicketEditHistory::groups($edits);

        $this->assertCount(2, $groups, 'Dos filas del mismo momento y motivo son UNA edición.');

        // La más reciente primero y con sus dos campos juntos.
        $primera = $groups->first();
        $this->assertSame('Agustina Magali', $primera['user']->name);
        $this->assertSame('2026-10-13 10:00:00', $primera['at']->format('Y-m-d H:i:s'));
        $this->assertSame('Se cargó mal el nombre', $primera['reason']);
        $this->assertCount(2, $primera['changes']);
        $this->assertSame(['Nombre', 'Precio'], array_column($primera['changes'], 'label'));

        $segunda = $groups->last();
        $this->assertSame('2026-10-12 09:00:00', $segunda['at']->format('Y-m-d H:i:s'));
        $this->assertNull($segunda['reason']);
        $this->assertCount(1, $segunda['changes']);
    }

    public function test_muestra_los_valores_legibles(): void
    {
        PaymentMethod::create(['code' => 'efectivo', 'label' => 'Efectivo', 'is_active' => true]);
        PaymentMethod::clearCache();

        $user = User::factory()->create();

        $edits = collect([
            $this->edit($user, 'price', 'Precio', '10000', '12500'),
            $this->edit($user, 'payment_method', 'Método de pago', 'efectivo', null),
            $this->edit($user, 'travels_with_child', 'Viaja con menor', 'No', 'Sí'),
        ]);

        $groups = TicketEditHistory::groups($edits);
        $cambios = collect($groups->first()['changes'])->keyBy('label');

        $this->assertSame('$10.000', $cambios['Precio']['from']);
        $this->assertSame('$12.500', $cambios['Precio']['to']);
        $this->assertSame('Efectivo', $cambios['Método de pago']['from'], 'El código interno se muestra con su etiqueta.');
        $this->assertSame('—', $cambios['Método de pago']['to'], 'Sin valor nuevo se muestra un guion.');
        $this->assertSame('No', $cambios['Viaja con menor']['from']);
        $this->assertSame('Sí', $cambios['Viaja con menor']['to']);
    }

    public function test_resumen_con_y_sin_ediciones(): void
    {
        $user = User::factory()->create();

        $this->assertSame(
            '2 campos corregidos en 2 ediciones.',
            TicketEditHistory::summary(collect([
                $this->edit($user, 'dni', 'DNI', '1', '2', null, '2026-10-12 09:00:00'),
                $this->edit($user, 'price', 'Precio', '1', '2', null, '2026-10-13 09:00:00'),
            ])),
        );

        $this->assertSame(
            '1 campo corregido en 1 edición.',
            TicketEditHistory::summary(collect([
                $this->edit($user, 'dni', 'DNI', '1', '2'),
            ])),
        );

        $this->assertSame('0 campos corregidos en 0 ediciones.', TicketEditHistory::summary(collect()));
        $this->assertSame('0 campos corregidos en 0 ediciones.', TicketEditHistory::summary(null));
    }
}
