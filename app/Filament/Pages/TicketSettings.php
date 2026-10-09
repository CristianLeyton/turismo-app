<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

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

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 100;

    public static function canAccess(): bool
    {
        return Auth::check() && (bool) Auth::user()?->can('settings.manage');
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
     * @return array<string, bool|int>
     */
    protected function getFormData(): array
    {
        return [
            'require_password_ticket_delete' => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE),
            'require_password_ticket_reschedule' => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE),
            'venta_limite_horas' => Setting::ventaLimiteHoras(),
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

                Section::make('Venta fuera de término')
                    ->description('Plazo para vender un pasaje de un colectivo que YA SALIÓ. Pasado el plazo, sólo puede emitirlo quien tenga el permiso "Vender después del límite de salida" (se habilita por rol en Roles y permisos; ningún rol lo trae por defecto).')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('venta_limite_horas')
                            ->label('Horas de venta después de la salida')
                            ->helperText('El plazo corre desde la salida del colectivo en la parada donde sube el pasajero. Ejemplo: con 5 horas, un viaje que salió a las 08:00 se puede vender hasta las 13:00. 0 = sin límite.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(720)
                            ->required()
                            ->default(Setting::VENTA_LIMITE_HORAS_DEFAULT)
                            ->suffix('horas')
                            ->validationMessages([
                                'required' => 'Ingrese la cantidad de horas.',
                                'numeric' => 'La cantidad de horas debe ser un número.',
                                'min' => 'La cantidad de horas no puede ser negativa.',
                            ]),
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

        Setting::set(
            Setting::VENTA_LIMITE_HORAS,
            (string) max(0, (int) ($data['venta_limite_horas'] ?? Setting::VENTA_LIMITE_HORAS_DEFAULT)),
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
