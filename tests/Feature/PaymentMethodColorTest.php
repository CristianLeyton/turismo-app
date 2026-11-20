<?php

namespace Tests\Feature;

use App\Filament\Resources\PaymentMethods\Pages\ManagePaymentMethods;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentMethodColorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un método con un color fijo (los de la migración: Efectivo verde y
     * Transferencia celeste ya existen).
     */
    private function methodWithColor(string $label, string $color): PaymentMethod
    {
        return PaymentMethod::create(['label' => $label, 'color' => $color, 'is_active' => true]);
    }

    public function test_cada_color_de_la_paleta_resuelve_su_color_de_filament_y_sus_clases(): void
    {
        foreach (PaymentMethod::PALETTE as $key => $entry) {
            $method = $this->methodWithColor("Método {$key}", $key);

            $expected = $entry['filament'] === null ? $key : constant(Color::class.'::'.$entry['filament']);

            $this->assertSame($expected, PaymentMethod::color($method->code), $key);
            $this->assertSame($entry['classes'], PaymentMethod::badgeClasses($method->code), $key);
        }
    }

    public function test_la_paleta_no_tiene_rojo_gris_ni_el_color_de_la_empresa(): void
    {
        foreach (['danger', 'gray', 'primary', 'red'] as $excluded) {
            $this->assertArrayNotHasKey($excluded, PaymentMethod::PALETTE);
        }

        foreach (PaymentMethod::PALETTE as $entry) {
            $this->assertStringNotContainsString('red-', $entry['classes']);
            $this->assertStringNotContainsString('gray-', $entry['classes']);
            $this->assertStringNotContainsString('primary-', $entry['classes']);
        }
    }

    public function test_un_color_desconocido_se_muestra_en_gris(): void
    {
        $method = PaymentMethod::create(['label' => 'Viejo', 'is_active' => true]);
        PaymentMethod::query()->whereKey($method->id)->update(['color' => 'danger']);
        PaymentMethod::clearCache();

        $this->assertSame('gray', PaymentMethod::color($method->code));
        $this->assertSame(PaymentMethod::NEUTRAL_BADGE_CLASSES, PaymentMethod::badgeClasses($method->code));
        $this->assertSame('gray', PaymentMethod::color('no-existe'));
        $this->assertSame(PaymentMethod::NEUTRAL_BADGE_CLASSES, PaymentMethod::badgeClasses(null));
    }

    public function test_el_color_sugerido_es_el_primero_libre(): void
    {
        // Efectivo (success) y Transferencia (info) vienen de la migración.
        $this->assertSame('warning', PaymentMethod::suggestedColor());
    }

    public function test_crear_sin_color_asigna_el_sugerido(): void
    {
        $posnet = PaymentMethod::create(['label' => 'Posnet', 'is_active' => true]);
        $orden = PaymentMethod::create(['label' => 'Orden de compra', 'is_active' => true]);

        $this->assertSame('warning', $posnet->fresh()->color);
        $this->assertSame('teal', $orden->fresh()->color);
    }

    public function test_crear_con_un_color_valido_lo_respeta(): void
    {
        $method = PaymentMethod::create(['label' => 'Posnet', 'color' => 'rose', 'is_active' => true]);

        $this->assertSame('rose', $method->fresh()->color);
    }

    public function test_crear_con_un_color_fuera_de_la_paleta_asigna_el_sugerido(): void
    {
        $method = PaymentMethod::create(['label' => 'Posnet', 'color' => 'gray', 'is_active' => true]);

        $this->assertSame('warning', $method->fresh()->color);
    }

    public function test_con_la_paleta_completa_sugiere_el_menos_usado(): void
    {
        foreach (array_keys(PaymentMethod::PALETTE) as $key) {
            if (! in_array($key, ['success', 'info'], true)) {
                $this->methodWithColor("Método {$key}", $key);
            }
        }

        // Segundo verde y segundo celeste: el menos usado pasa a ser ámbar.
        $this->methodWithColor('Otro verde', 'success');
        $this->methodWithColor('Otro celeste', 'info');

        $this->assertSame('warning', PaymentMethod::suggestedColor());

        $this->methodWithColor('Otro ámbar', 'warning');
        $this->assertSame('teal', PaymentMethod::suggestedColor());
    }

    public function test_los_metodos_eliminados_no_ocupan_su_color(): void
    {
        $this->methodWithColor('Posnet', 'warning')->delete();

        $this->assertSame('warning', PaymentMethod::suggestedColor());
    }

    // ====================== Migración de los métodos existentes ======================

    public function test_la_migracion_colorea_los_metodos_grises_sin_tocar_los_que_tienen_color(): void
    {
        $now = now();
        foreach ([
            ['code' => 'posnet_viejo', 'label' => 'Posnet viejo', 'sort_order' => 3, 'deleted_at' => $now],
            ['code' => 'posnet', 'label' => 'Posnet', 'sort_order' => 4, 'deleted_at' => null],
            ['code' => 'orden', 'label' => 'Orden de compra', 'sort_order' => 5, 'deleted_at' => null],
            ['code' => 'oncologico', 'label' => 'Paciente oncológico', 'sort_order' => 6, 'deleted_at' => null],
        ] as $row) {
            \Illuminate\Support\Facades\DB::table('payment_methods')->insert($row + [
                'color' => 'gray', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $migration = require database_path('migrations/2026_11_06_225201_assign_palette_colors_to_payment_methods.php');
        $migration->up();

        $colors = \Illuminate\Support\Facades\DB::table('payment_methods')->pluck('color', 'code')->all();

        $this->assertSame('success', $colors['cash']);
        $this->assertSame('info', $colors['transfer']);
        // Primero los vigentes, en orden; después el eliminado.
        $this->assertSame('warning', $colors['posnet']);
        $this->assertSame('teal', $colors['orden']);
        $this->assertSame('indigo', $colors['oncologico']);
        $this->assertSame('orange', $colors['posnet_viejo']);

        // Idempotente: correrla otra vez no cambia nada.
        $migration->up();
        $this->assertSame($colors, \Illuminate\Support\Facades\DB::table('payment_methods')->pluck('color', 'code')->all());
    }

    // ====================== Métodos de pago en el panel ======================

    private function actingAsSuperAdmin(): void
    {
        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    public function test_crear_trae_el_color_sugerido_y_lo_guarda(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ManagePaymentMethods::class)
            ->mountAction(CreateAction::class)
            ->assertActionDataSet(['color' => 'warning'])
            ->setActionData(['label' => 'Posnet'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('payment_methods', ['label' => 'Posnet', 'color' => 'warning']);
    }

    public function test_editar_cambia_el_color(): void
    {
        $this->actingAsSuperAdmin();
        $posnet = $this->methodWithColor('Posnet', 'warning');

        Livewire::test(ManagePaymentMethods::class)
            ->callTableAction(EditAction::class, $posnet, data: ['label' => 'Posnet', 'color' => 'indigo'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('indigo', $posnet->fresh()->color);
        $this->assertSame(Color::Indigo, PaymentMethod::color($posnet->code));
    }

    public function test_un_color_fuera_de_la_paleta_no_se_guarda(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(ManagePaymentMethods::class)
            ->callAction(CreateAction::class, data: ['label' => 'Posnet', 'color' => 'danger'])
            ->assertHasActionErrors(['color' => 'in']);

        $this->assertDatabaseMissing('payment_methods', ['label' => 'Posnet']);
    }

    public function test_las_opciones_de_color_se_ven_como_badges(): void
    {
        foreach (PaymentMethodResource::colorOptions() as $key => $html) {
            $this->assertStringContainsString(PaymentMethod::PALETTE[$key]['classes'], $html);
            $this->assertStringContainsString(e(PaymentMethod::PALETTE[$key]['label']), $html);
        }
    }

    public function test_la_tabla_muestra_cada_metodo_como_badge_de_su_color(): void
    {
        $this->actingAsSuperAdmin();
        $posnet = $this->methodWithColor('Posnet', 'rose');

        Livewire::test(ManagePaymentMethods::class)
            ->assertCanSeeTableRecords([$posnet])
            ->assertTableColumnExists('label', fn (TextColumn $column): bool => $column->isBadge() && $column->record($posnet)->getColor($posnet->label) === Color::Rose, $posnet);
    }

    public function test_el_resumen_de_la_venta_usa_el_color_del_metodo(): void
    {
        $indigo = $this->methodWithColor('Orden de compra', 'indigo');

        $state = [
            'passengers' => [[
                'first_name' => 'Ana',
                'last_name' => 'Pérez',
                'dni' => '30111222',
                'payment_method' => $indigo->code,
            ]],
        ];

        $html = view('tickets.summary', [
            'get' => fn (string $key): mixed => $state[$key] ?? null,
            'section' => 'passengers',
        ])->render();

        $this->assertStringContainsString(PaymentMethod::PALETTE['indigo']['classes'], $html);
        $this->assertStringContainsString('Orden de compra', $html);
    }

    public function test_la_tabla_de_pagos_usa_el_color_del_metodo(): void
    {
        $this->actingAsSuperAdmin();
        $posnet = $this->methodWithColor('Posnet', 'teal');
        $payment = \App\Models\Payment::create([
            'user_id' => User::factory()->vendedor()->create()->id,
            'amount' => 1000,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]);
        // El enum viejo de SQLite solo acepta cash/transfer: se apunta al método por query.
        \Illuminate\Support\Facades\DB::table('payments')->whereKey($payment->id)->update(['payment_method' => $posnet->code]);
        $payment->refresh();

        Livewire::test(ManagePayments::class)
            ->assertTableColumnExists('payment_method', fn (TextColumn $column): bool => $column->record($payment)->getColor($posnet->code) === Color::Teal, $payment);
    }
}
