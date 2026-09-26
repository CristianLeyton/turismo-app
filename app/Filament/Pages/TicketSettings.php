<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use UnitEnum;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

/**
 * Página de configuración del sistema (solo administradores).
 *
 * Hoy expone los parámetros de confirmación de contraseña para acciones
 * sensibles sobre boletos; agregar un nuevo parámetro = una constante en
 * Setting + un Toggle acá.
 */
class TicketSettings extends Page
{
    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;

    protected static ?string $navigationLabel = 'Configuración';

    protected static ?string $title = 'Configuración';

    protected ?string $heading = 'Configuración';

    protected static string | UnitEnum | null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 100;

    public static function canAccess(): bool
    {
        return Auth::check() && (bool) Auth::user()->is_admin;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill($this->getFormData());
    }

    /**
     * Valores actuales de los parámetros para el formulario.
     *
     * @return array<string, bool>
     */
    protected function getFormData(): array
    {
        return [
            'require_password_ticket_delete' => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE),
            'require_password_ticket_reschedule' => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Confirmación de contraseña')
                    ->description('Exigir la contraseña del admin logueado antes de ejecutar acciones sensibles sobre boletos.')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('require_password_ticket_delete')
                            ->label('Pedir contraseña al borrar boletos')
                            ->helperText('Antes de eliminar (o eliminar definitivamente) un boleto, se pedirá la clave del admin que ejecuta la acción. Si la clave no coincide, el boleto no se borra.')
                            ->default(false),

                        Toggle::make('require_password_ticket_reschedule')
                            ->label('Pedir contraseña al reprogramar boletos')
                            ->helperText('Antes de confirmar una reprogramación, se pedirá la clave del admin que ejecuta la acción. Si la clave no coincide, el boleto no se mueve.')
                            ->default(false),
                    ]),
            ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): \Filament\Schemas\Components\Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment('left')
                    ->fullWidth(false),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar cambios')
                ->color('primary')
                ->icon('heroicon-m-check')
                ->action(fn () => $this->save()),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set(
            Setting::REQUIRE_PASSWORD_TICKET_DELETE,
            ($data['require_password_ticket_delete'] ?? false) ? '1' : '0',
        );

        Setting::set(
            Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE,
            ($data['require_password_ticket_reschedule'] ?? false) ? '1' : '0',
        );

        Notification::make()
            ->title('Configuración guardada')
            ->body('Los cambios se aplicaron correctamente.')
            ->success()
            ->send();

        // Recargar el formulario con lo persistido (normalizado).
        $this->form->fill($this->getFormData());
    }
}
