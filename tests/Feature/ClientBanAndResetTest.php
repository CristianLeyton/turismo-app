<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Models\Clients;
use App\Models\Setting;
use App\Models\User;
use App\Support\Permissions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Clientes: "Banear" pide motivo obligatorio y lo guarda en `comments`;
 * "Resetear contraseña" solo con el panel de clientes activo y con textos
 * reales (spec client-admin).
 */
class ClientBanAndResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::flushCache();
        Permissions::syncToDatabase();
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    // ====================== Banear ======================

    public function test_banear_con_motivo_guarda_el_motivo_y_deja_sin_compra(): void
    {
        $client = $this->client(['can_buy' => true]);

        Livewire::test(ManageClients::class)
            ->callTableAction('toggleBan', $client, data: ['comments' => 'No se presentó a tres viajes'])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Cliente baneado');

        $client->refresh();
        $this->assertFalse($client->can_buy);
        $this->assertSame('No se presentó a tres viajes', $client->comments);
    }

    public function test_banear_sin_motivo_da_error_y_no_banea(): void
    {
        $client = $this->client(['can_buy' => true, 'comments' => null]);

        Livewire::test(ManageClients::class)
            ->callTableAction('toggleBan', $client, data: ['comments' => ''])
            ->assertHasTableActionErrors(['comments' => 'required']);

        $this->assertTrue($client->fresh()->can_buy);
    }

    public function test_el_mensaje_de_motivo_faltante_esta_en_voseo(): void
    {
        $client = $this->client(['can_buy' => true, 'comments' => null]);

        $component = Livewire::test(ManageClients::class)
            ->callTableAction('toggleBan', $client, data: ['comments' => '']);

        $this->assertContains('Ingresá el motivo del baneo.', $component->errors()->all());
    }

    public function test_el_motivo_viene_precargado_con_el_comentario_guardado(): void
    {
        $client = $this->client(['can_buy' => true, 'comments' => 'Debe un pasaje']);

        Livewire::test(ManageClients::class)
            ->mountTableAction('toggleBan', $client)
            ->assertTableActionDataSet(['comments' => 'Debe un pasaje']);
    }

    public function test_habilitar_no_pide_motivo_y_conserva_el_comentario(): void
    {
        $client = $this->client(['can_buy' => false, 'comments' => 'Impago reiterado']);

        $component = Livewire::test(ManageClients::class)
            ->mountTableAction('toggleBan', $client);

        // Solo confirmación: el modal no trae el campo del motivo.
        $this->assertTrue($component->instance()->getMountedTableAction()->isConfirmationRequired());
        $component->assertDontSee('Motivo del baneo');

        $component->callMountedTableAction()
            ->assertHasNoTableActionErrors()
            ->assertNotified('Cliente habilitado');

        $client->refresh();
        $this->assertTrue($client->can_buy);
        $this->assertSame('Impago reiterado', $client->comments);
    }

    public function test_sin_permiso_de_banear_no_se_ve_la_accion(): void
    {
        $client = $this->client(['can_buy' => true]);
        $this->actingAs($this->userWith(['clients.view_any', 'clients.view']));

        Livewire::test(ManageClients::class)
            ->assertTableActionHidden('toggleBan', $client);
    }

    public function test_banear_y_resetear_estan_en_el_menu_de_celular_y_en_escritorio(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');

        $recordActions = collect(Livewire::test(ManageClients::class)->instance()->getTable()->getRecordActions());

        $mobileMenu = $recordActions->first(fn ($action): bool => $action instanceof ActionGroup
            && str_contains($action->getExtraAttributes()['class'] ?? '', 'md:hidden'));
        $this->assertNotNull($mobileMenu, 'Falta el menú de tres puntitos para celular.');

        $mobileNames = collect($mobileMenu->getActions())->map(fn ($action): string => $action->getName())->all();
        $this->assertContains('toggleBan', $mobileNames);
        $this->assertContains('resetPassword', $mobileNames);

        $desktopNames = $recordActions
            ->filter(fn ($action): bool => $action instanceof Action
                && str_contains($action->getExtraAttributes()['class'] ?? '', 'hidden md:inline-flex'))
            ->map(fn (Action $action): string => $action->getName())
            ->all();
        $this->assertContains('toggleBan', $desktopNames);
        $this->assertContains('resetPassword', $desktopNames);
    }

    // ====================== Resetear contraseña ======================

    public function test_con_el_panel_apagado_no_se_ve_resetear_contrasena(): void
    {
        $client = $this->client();

        Livewire::test(ManageClients::class)
            ->assertTableActionHidden('resetPassword', $client);
    }

    public function test_con_el_panel_encendido_se_ve_y_resetea_al_dni(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
        $client = $this->client(['password' => 'mi-clave-propia', 'must_change_password' => false]);

        Livewire::test(ManageClients::class)
            ->assertTableActionVisible('resetPassword', $client)
            ->callTableAction('resetPassword', $client)
            ->assertNotified('Contraseña reseteada');

        $client->refresh();
        $this->assertTrue(Hash::check($client->dni, $client->password));
        $this->assertTrue($client->must_change_password);
    }

    public function test_sin_permiso_de_resetear_no_se_ve_aunque_el_panel_este_encendido(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
        $client = $this->client();
        $this->actingAs($this->userWith(['clients.view_any', 'clients.view']));

        Livewire::test(ManageClients::class)
            ->assertTableActionHidden('resetPassword', $client);
    }

    public function test_los_textos_del_reseteo_tienen_caracteres_reales(): void
    {
        Setting::set(Setting::PANEL_CLIENTES_ACTIVO, '1');
        $withPassword = $this->client(['password' => 'mi-clave-propia', 'must_change_password' => false]);
        $withoutPassword = $this->client(['dni' => '40111222', 'nombre' => 'Beto']);

        $page = Livewire::test(ManageClients::class);

        $description = $page->mountTableAction('resetPassword', $withPassword)
            ->instance()->getMountedTableAction()->getModalDescription();
        $this->assertStringNotContainsString('\u', $description);
        $this->assertStringContainsString('¿Querés resetear la contraseña de Ana García?', $description);
        $this->assertStringContainsString('va a tener que elegir una nueva', $description);

        $description = Livewire::test(ManageClients::class)
            ->mountTableAction('resetPassword', $withoutPassword)
            ->instance()->getMountedTableAction()->getModalDescription();
        $this->assertStringNotContainsString('\u', $description);
        $this->assertStringContainsString('todavía no eligió su propia contraseña: ya entra con su DNI.', $description);

        Livewire::test(ManageClients::class)->callTableAction('resetPassword', $withPassword);
        $body = collect(session('filament.notifications', []))->pluck('body')->implode(' ');
        $this->assertStringNotContainsString('\u', $body);
        $this->assertStringContainsString('elige una contraseña nueva en el próximo ingreso', $body);
    }

    // ====================== Helpers ======================

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function client(array $attributes = []): Clients
    {
        return Clients::create(array_merge([
            'dni' => '30111222',
            'nombre' => 'Ana',
            'apellido' => 'García',
            'can_buy' => true,
        ], $attributes));
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}
