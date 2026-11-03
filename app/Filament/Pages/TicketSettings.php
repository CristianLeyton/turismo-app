<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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

    protected static ?string $navigationLabel = 'Parametros';

    protected static ?string $title = 'Parametros';

    protected ?string $heading = 'Parametros';

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
     * @return array<string, mixed>
     */
    protected function getFormData(): array
    {
        return [
            'require_password_ticket_delete' => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE),
            'require_password_ticket_reschedule' => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE),
            'require_password_ticket_edit' => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_EDIT),
            'venta_limite_horas' => Setting::ventaLimiteHoras(),
            'permitir_acompanantes' => Setting::permiteAcompanantes(),
            'descarga_automatica_pdf' => Setting::descargaAutomaticaPdf(),
            'whatsapp_mensaje' => Setting::whatsappMensaje(),
            'service_weekdays' => array_map('strval', Setting::serviceWeekdays()),
            'service_exceptions' => array_map(fn (array $exception): array => [
                'date' => $exception['date'],
                'has_service' => $exception['has_service'] ? '1' : '0',
                'reason' => $exception['reason'],
            ], Setting::serviceExceptions()),
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

                        Toggle::make('require_password_ticket_edit')
                            ->label('Pedir contraseña al editar boletos')
                            ->helperText('Antes de guardar los cambios de una edición de boleto, se pedirá la clave del admin que ejecuta la acción. Si la clave no coincide, nada se modifica.')
                            ->default(false),
                    ]),

                Section::make('Venta con acompañante')
                    ->description('Mostrar u ocultar los datos de acompañantes al vender. Es un parámetro visual: no cambia las reglas de venta, sólo qué campos ve el vendedor.')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('permitir_acompanantes')
                            ->label('Vender boletos con acompañante')
                            /* ->helperText('Activado: en el paso Pasajeros aparecen los switches "¿Viaja con un menor?" y "¿Viaja con mascotas?" con sus datos. Desactivado: esos switches no se muestran.') */
                            ->default(true),
                    ]),

                Section::make('Emisión de boletos al finalizar la venta')
                    ->description('Cómo entrega el sistema el PDF de los boletos al finalizar una venta o una reprogramación.')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('descarga_automatica_pdf')
                            ->label('Descargar PDF automáticamente al vender')
                            ->live()
                            ->default(true)
                            ->helperText('Activado: el PDF se descarga solo, como hasta ahora. Desactivado: en vez de descargarse, se abre la página de previsualización del boleto con un link compartible (por WhatsApp o el que sea) y el botón para descargar el PDF.'),

                        TextInput::make('whatsapp_mensaje')
                            ->label('Texto del mensaje de WhatsApp')
                            ->placeholder(Setting::WHATSAPP_MENSAJE_DEFAULT)
                            ->helperText('Texto que lleva el mensaje cuando se comparte el link del boleto por WhatsApp. Usá {link} donde quieras que aparezca la URL.')
                            ->visible(fn (Get $get): bool => ! (bool) $get('descarga_automatica_pdf')),
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
                Section::make('Días de servicio')
                    ->description('Fechas en las que hay viajes. En las fechas sin servicio no se puede vender, reprogramar, asignar fecha desde el panel de clientes ni crear viajes, salvo quien tenga el permiso "Vender en días sin servicio" (se habilita por rol; ningún rol lo trae por defecto). El panel de clientes nunca permite elegirlas. Los boletos y viajes ya existentes no se modifican.')
                    ->columnSpanFull()
                    ->schema([
                        CheckboxList::make('service_weekdays')
                            ->label('Días de la semana con servicio')
                            ->options([
                                '1' => 'Lunes',
                                '2' => 'Martes',
                                '3' => 'Miércoles',
                                '4' => 'Jueves',
                                '5' => 'Viernes',
                                '6' => 'Sábado',
                                '7' => 'Domingo',
                            ])
                            ->columns(4),

                        Repeater::make('service_exceptions')
                            ->label('Excepciones por fecha')
                            ->helperText('Una excepción manda sobre el día de la semana: "Con servicio" habilita una fecha (por ejemplo, un domingo especial) y "Sin servicio" la bloquea (por ejemplo, un feriado).')
                            ->addActionLabel('Agregar excepción')
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->columns(3)
                            ->schema([
                                DatePicker::make('date')
                                    ->label('Fecha')
                                    ->required()
                                    ->distinct()
                                    ->validationMessages([
                                        'required' => 'Ingrese la fecha.',
                                        'distinct' => 'Hay más de una excepción para la misma fecha.',
                                    ]),

                                Select::make('has_service')
                                    ->label('Tipo')
                                    ->options([
                                        '1' => 'Con servicio',
                                        '0' => 'Sin servicio',
                                    ])
                                    ->default('0')
                                    ->selectablePlaceholder(false)
                                    ->required(),

                                TextInput::make('reason')
                                    ->label('Motivo')
                                    ->placeholder('Opcional, por ejemplo "Navidad"')
                                    ->maxLength(100),
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
            Setting::REQUIRE_PASSWORD_TICKET_EDIT,
            ($data['require_password_ticket_edit'] ?? false) ? '1' : '0',
        );

        Setting::set(
            Setting::VENTA_LIMITE_HORAS,
            (string) max(0, (int) ($data['venta_limite_horas'] ?? Setting::VENTA_LIMITE_HORAS_DEFAULT)),
        );

        Setting::set(
            Setting::PERMITIR_ACOMPANANTES,
            ($data['permitir_acompanantes'] ?? true) ? '1' : '0',
        );

        Setting::set(
            Setting::DESCARGA_AUTOMATICA_PDF,
            ($data['descarga_automatica_pdf'] ?? true) ? '1' : '0',
        );

        // Mensaje vacío = volver al texto por defecto.
        Setting::set(
            Setting::WHATSAPP_MENSAJE,
            filled($data['whatsapp_mensaje'] ?? null) ? trim($data['whatsapp_mensaje']) : null,
        );

        $weekdays = array_values(array_unique(array_map('intval', $data['service_weekdays'] ?? [])));
        sort($weekdays);
        Setting::set(Setting::SERVICE_WEEKDAYS, json_encode($weekdays));

        $exceptions = collect($data['service_exceptions'] ?? [])
            ->filter(fn (array $exception): bool => filled($exception['date'] ?? null))
            ->map(fn (array $exception): array => [
                'date' => Carbon::parse($exception['date'])->format('Y-m-d'),
                'has_service' => (bool) (int) ($exception['has_service'] ?? 0),
                'reason' => filled($exception['reason'] ?? null) ? trim($exception['reason']) : null,
            ])
            ->sortBy('date')
            ->values()
            ->all();
        Setting::set(Setting::SERVICE_EXCEPTIONS, json_encode($exceptions));

        Notification::make()
            ->title('Configuración guardada')
            ->body('Los cambios se aplicaron correctamente.')
            ->success()
            ->send();

        // Recargar el formulario con lo persistido (normalizado).
        $this->form->fill($this->getFormData());
    }
}
