<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\BrandPalette;
use BackedEnum;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Página Configuración > Parámetros (solo quien tiene `settings.manage`).
 *
 * Agrupa los parámetros del sistema en pestañas según a qué afectan: Venta,
 * Boleto (la sección de emisión solo con `settings.ticket_emission`), Calendario
 * (solo con `settings.calendar`), Seguridad (solo con `settings.security`),
 * Clientes (esta solo con el permiso `settings.client_panel`) y
 * Apariencia (solo con `settings.appearance`). Un parámetro nuevo lleva una constante en
 * Setting, su campo en la pestaña que corresponde y su entrada en TAB_FIELDS
 * (ver la sección "Parámetros" del skill filament-ui).
 */
class TicketSettings extends Page
{
    /**
     * Campos de cada pestaña. Alimenta el badge de errores y la notificación
     * de guardado fallido, así ambos nombran siempre las mismas pestañas.
     *
     * @var array<string, list<string>>
     */
    public const TAB_FIELDS = [
        'Venta' => ['permitir_acompanantes', 'venta_limite_horas', 'selectores_detallados', 'observaciones_activas', 'precio_fijo_activo', 'precio_fijo_boleto', 'precio_fijo_seguro_menor'],
        'Boleto' => ['descarga_automatica_pdf', 'whatsapp_mensaje', 'contact_lines'],
        'Calendario' => ['service_weekdays', 'service_exceptions'],
        'Seguridad' => ['require_password_ticket_delete', 'require_password_ticket_reschedule', 'require_password_ticket_edit'],
        'Clientes' => ['panel_clientes_activo', 'client_advance_notice_hours'],
        'Apariencia' => ['primary_color'],
    ];

    /** Permiso para ver la pestaña Clientes y prender o apagar el panel. */
    public const CLIENT_PANEL_PERMISSION = 'settings.client_panel';

    /** Permiso para ver la pestaña Apariencia y cambiar el color principal. */
    public const APPEARANCE_PERMISSION = 'settings.appearance';

    /** Permiso para ver y cambiar el estilo de los selectores de origen y destino. */
    public const SALE_SELECTORS_PERMISSION = 'settings.sale_selectors';

    /** Permiso para ver y prender o apagar las observaciones de la venta. */
    public const SALE_OBSERVATIONS_PERMISSION = 'settings.sale_observations';

    /** Permiso para ver y cambiar la emisión al finalizar la venta (PDF automático y mensaje de WhatsApp). */
    public const TICKET_EMISSION_PERMISSION = 'settings.ticket_emission';

    /** Permiso para ver y cambiar el precio fijo de los boletos. */
    public const FIXED_PRICE_PERMISSION = 'settings.fixed_price';

    /** Permiso para ver la pestaña Calendario y cambiar los días de servicio y sus excepciones. */
    public const CALENDAR_PERMISSION = 'settings.calendar';

    /** Permiso para ver la pestaña Seguridad y cambiar la confirmación de contraseña. */
    public const SECURITY_PERMISSION = 'settings.security';

    /** Tope de los montos del precio fijo (mismo máximo que el precio a mano). */
    private const FIXED_PRICE_MAX = 999999;

    private const PRIMARY_COLOR_INVALID_MESSAGE = 'Elegí un color válido en formato #RRGGBB (por ejemplo, #10288c).';

    public const WHATSAPP_MENSAJE_MAX = 300;

    private const PHONE_INVALID_MESSAGE = 'Ingresá un teléfono válido (solo números, espacios, guiones, paréntesis y +).';

    /** @var array<string, mixed> */
    public ?array $data = [];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Cog6Tooth;

    protected static ?string $navigationLabel = 'Parámetros';

    protected static ?string $title = 'Parámetros';

    protected ?string $heading = 'Parámetros';

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
     * Valores actuales de los parámetros para el formulario. Las excepciones
     * de fechas ya pasadas no se cargan: no afectan nada y fallarían la
     * validación de fecha mínima.
     *
     * @return array<string, mixed>
     */
    protected function getFormData(): array
    {
        $today = today()->format('Y-m-d');

        return [
            'require_password_ticket_delete' => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_DELETE),
            'require_password_ticket_reschedule' => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE),
            'require_password_ticket_edit' => Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_EDIT),
            'venta_limite_horas' => Setting::ventaLimiteHoras(),
            'permitir_acompanantes' => Setting::permiteAcompanantes(),
            'descarga_automatica_pdf' => Setting::descargaAutomaticaPdf(),
            'whatsapp_mensaje' => Setting::whatsappMensaje(),
            'contact_lines' => array_map(fn (array $line): array => [
                'name' => $line['name'],
                'phone' => $line['phone'],
            ], Setting::contactLines()),
            'service_weekdays' => array_map('strval', Setting::serviceWeekdays()),
            'service_exceptions' => array_values(array_map(fn (array $exception): array => [
                'date' => $exception['date'],
                'has_service' => $exception['has_service'] ? '1' : '0',
                'reason' => $exception['reason'],
            ], array_filter(
                Setting::serviceExceptions(),
                fn (array $exception): bool => $exception['date'] >= $today,
            ))),
            'panel_clientes_activo' => Setting::panelClientesActivo(),
            'client_advance_notice_hours' => Setting::clientAdvanceNoticeHours(),
            'selectores_detallados' => Setting::selectoresDetallados(),
            'observaciones_activas' => Setting::observacionesActivas(),
            'precio_fijo_activo' => Setting::precioFijoActivo(),
            'precio_fijo_boleto' => $this->storedAmount(Setting::PRECIO_FIJO_BOLETO),
            'precio_fijo_seguro_menor' => $this->storedAmount(Setting::PRECIO_FIJO_SEGURO_MENOR),
            'primary_color' => Setting::primaryColor(),
        ];
    }

    /**
     * ¿Quien usa la página puede ver la pestaña Clientes y cambiar el panel?
     */
    protected function canManageClientPanel(): bool
    {
        return (bool) Auth::user()?->can(self::CLIENT_PANEL_PERMISSION);
    }

    /**
     * ¿Quien usa la página puede ver la pestaña Apariencia y cambiar el color?
     */
    protected function canManageAppearance(): bool
    {
        return (bool) Auth::user()?->can(self::APPEARANCE_PERMISSION);
    }

    /**
     * ¿Quien usa la página puede cambiar el estilo de los selectores de origen y destino?
     */
    protected function canManageSaleSelectors(): bool
    {
        return (bool) Auth::user()?->can(self::SALE_SELECTORS_PERMISSION);
    }

    /**
     * ¿Quien usa la página puede prender o apagar las observaciones de la venta?
     */
    protected function canManageSaleObservations(): bool
    {
        return (bool) Auth::user()?->can(self::SALE_OBSERVATIONS_PERMISSION);
    }

    /**
     * Monto guardado del precio fijo, o null si nunca se cargó: así el campo
     * aparece vacío (y no en $0) la primera vez que se prende.
     */
    protected function storedAmount(string $key): ?int
    {
        $value = Setting::getInt($key, -1);

        return $value < 0 ? null : $value;
    }

    /**
     * ¿Quien usa la página puede prender o apagar el precio fijo y cambiar sus montos?
     */
    protected function canManageFixedPrice(): bool
    {
        return (bool) Auth::user()?->can(self::FIXED_PRICE_PERMISSION);
    }

    /**
     * ¿Quien usa la página puede cambiar cómo se entregan los boletos al finalizar la venta?
     */
    protected function canManageTicketEmission(): bool
    {
        return (bool) Auth::user()?->can(self::TICKET_EMISSION_PERMISSION);
    }

    /**
     * ¿Quien usa la página puede ver la pestaña Calendario y cambiar los días de servicio?
     */
    protected function canManageCalendar(): bool
    {
        return (bool) Auth::user()?->can(self::CALENDAR_PERMISSION);
    }

    /**
     * ¿Quien usa la página puede ver la pestaña Seguridad y cambiar la confirmación de contraseña?
     */
    protected function canManageSecurity(): bool
    {
        return (bool) Auth::user()?->can(self::SECURITY_PERMISSION);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('parametros')
                    ->persistTabInQueryString('pestana')
                    ->columnSpanFull()
                    ->tabs([
                        $this->tab('Venta', Heroicon::OutlinedShoppingCart, $this->saleComponents()),
                        $this->tab('Boleto', Heroicon::OutlinedTicket, $this->ticketComponents()),
                        $this->tab('Calendario', Heroicon::OutlinedCalendarDays, $this->calendarComponents())
                            ->visible(fn (): bool => $this->canManageCalendar()),
                        $this->tab('Seguridad', Heroicon::OutlinedLockClosed, $this->securityComponents())
                            ->visible(fn (): bool => $this->canManageSecurity()),
                        $this->tab('Clientes', Heroicon::OutlinedUsers, $this->clientComponents())
                            ->visible(fn (): bool => $this->canManageClientPanel()),
                        $this->tab('Apariencia', Heroicon::OutlinedSwatch, $this->appearanceComponents())
                            ->visible(fn (): bool => $this->canManageAppearance()),
                    ]),
            ]);
    }

    /**
     * Pestaña con badge rojo que cuenta los campos con error de la pestaña.
     *
     * @param  array<Component|\Filament\Forms\Components\Field>  $components
     */
    protected function tab(string $label, Heroicon $icon, array $components): Tab
    {
        return Tab::make($label)
            ->icon($icon)
            ->badge(fn (): ?int => $this->countTabErrors($label) ?: null)
            ->badgeColor('danger')
            ->schema($components);
    }

    /**
     * @return array<Component>
     */
    protected function saleComponents(): array
    {
        return [
            Section::make('Venta con acompañante')
                ->columnSpanFull()
                ->schema([
                    Toggle::make('permitir_acompanantes')
                        ->label('Vender boletos con acompañante')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Solo muestra u oculta los campos de menor y mascotas al vender. No cambia las reglas de venta.')
                        ->default(true),
                ]),

            Section::make('Venta fuera de término')
                ->description('Plazo para vender un pasaje de un colectivo que ya salió.')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('venta_limite_horas')
                        ->label('Horas de venta después de la salida')
                        ->helperText('0 = sin límite.')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Cuenta desde la salida en la parada donde sube el pasajero. Ej.: con 5 horas, un viaje que salió a las 08:00 se vende hasta las 13:00. Quien tenga el permiso «Vender después del límite de salida» puede vender igual.')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(720)
                        ->required()
                        ->default(Setting::VENTA_LIMITE_HORAS_DEFAULT)
                        ->suffix('horas')
                        ->validationMessages([
                            'required' => 'Ingresá la cantidad de horas.',
                            'numeric' => 'La cantidad de horas tiene que ser un número.',
                            'integer' => 'La cantidad de horas tiene que ser un número entero.',
                            'min' => 'La cantidad de horas no puede ser negativa.',
                            'max' => 'La cantidad de horas no puede ser mayor a :max.',
                        ]),
                ]),

            Section::make('Selectores de origen y destino')
                ->columnSpanFull()
                ->visible(fn (): bool => $this->canManageSaleSelectors())
                ->schema([
                    Toggle::make('selectores_detallados')
                        ->label('Selectores de origen y destino detallados')
                        ->helperText('Apagado, origen y destino se eligen de una lista simple.')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Prendido, cada parada se muestra numerada según el recorrido, los destinos se agrupan por sentido ("Hacia Salta") y la lista tiene buscador cuando hay más de 10 paradas. No cambia qué paradas se pueden vender.')
                        ->default(false),
                ]),

            Section::make('Observaciones')
                ->columnSpanFull()
                ->visible(fn (): bool => $this->canManageSaleObservations())
                ->schema([
                    Toggle::make('observaciones_activas')
                        ->label('Observaciones en la venta')
                        ->helperText('Apagado, no se cargan ni se muestran observaciones; las ya cargadas se conservan.')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Prendido, la venta y "Editar boleto" ofrecen una nota interna de hasta 280 caracteres. Se ve en la lista de pasajeros del viaje (modal, PDF y Excel), en el detalle del boleto y en la auditoría; nunca en el boleto del pasajero. En el modal y el PDF del viaje reemplaza a la columna Teléfono.')
                        ->default(false),
                ]),

            Section::make('Precio de los boletos')
                ->columnSpanFull()
                ->visible(fn (): bool => $this->canManageFixedPrice())
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    Toggle::make('precio_fijo_activo')
                        ->label('Precio fijo activo')
                        ->helperText('Prendido, el precio se calcula solo y nadie lo puede cambiar al vender ni al editar un boleto.')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Precio = precio del boleto + seguro si viaja con un menor, por 2 si es ida y vuelta. Los boletos ya vendidos no cambian. Apagado, el precio se carga a mano como siempre.')
                        ->live()
                        ->default(false)
                        ->columnSpanFull(),

                    $this->fixedPriceAmountInput('precio_fijo_boleto', 'Precio del boleto', 'el precio del boleto', 1),

                    $this->fixedPriceAmountInput('precio_fijo_seguro_menor', 'Seguro para menores', 'el seguro para menores', 0)
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Se suma por cada tramo en que el pasajero viaja con un menor. 0 = no se cobra seguro.'),
                ]),
        ];
    }

    /**
     * Monto del precio fijo: obligatorio y en pesos enteros solo mientras el
     * precio fijo está prendido (oculto, no se valida ni se pisa al guardar).
     */
    protected function fixedPriceAmountInput(string $name, string $label, string $article, int $min): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix('$')
            ->integer()
            ->minValue($min)
            ->maxValue(self::FIXED_PRICE_MAX)
            ->required(fn (Get $get): bool => (bool) $get('precio_fijo_activo'))
            ->visible(fn (Get $get): bool => (bool) $get('precio_fijo_activo'))
            ->validationMessages([
                'required' => "Ingresá {$article}.",
                'numeric' => 'El monto tiene que ser un número.',
                'integer' => 'El monto tiene que ser un número entero, sin centavos.',
                'min' => $min === 0 ? 'El monto no puede ser negativo.' : 'El monto tiene que ser de al menos $:min.',
                'max' => 'El monto no puede ser mayor a $999.999.',
            ]);
    }

    /**
     * @return array<Component>
     */
    protected function ticketComponents(): array
    {
        return [
            Section::make('Emisión al finalizar la venta')
                ->columnSpanFull()
                ->visible(fn (): bool => $this->canManageTicketEmission())
                ->schema([
                    Toggle::make('descarga_automatica_pdf')
                        ->label('Descargar PDF automáticamente al vender')
                        ->helperText('Desactivado: se abre la vista previa del boleto con un link para compartir.')
                        ->live()
                        ->default(true),

                    TextInput::make('whatsapp_mensaje')
                        ->label('Texto del mensaje de WhatsApp')
                        ->placeholder(Setting::WHATSAPP_MENSAJE_DEFAULT)
                        ->helperText('Usá {link} donde va la URL del boleto.')
                        ->maxLength(self::WHATSAPP_MENSAJE_MAX)
                        ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                            if (filled($value) && ! str_contains((string) $value, '{link}')) {
                                $fail('El mensaje tiene que incluir {link} para que lleve la URL del boleto.');
                            }
                        })
                        ->validationMessages([
                            'max' => 'El mensaje no puede tener más de :max caracteres.',
                        ])
                        ->visible(fn (Get $get): bool => ! (bool) $get('descarga_automatica_pdf')),
                ]),

            Section::make('Líneas de contacto')
                ->description('Aparecen en todos los boletos, en este orden.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('contact_lines')
                        ->hiddenLabel()
                        ->addActionLabel('Agregar línea')
                        ->defaultItems(0)
                        ->maxItems(Setting::CONTACT_LINES_MAX)
                        ->columns(2)
                        ->validationMessages([
                            'max' => 'Podés cargar como máximo :max líneas.',
                        ])
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre (opcional)')
                                ->placeholder('Ej.: Orán')
                                ->maxLength(30)
                                ->validationMessages([
                                    'max' => 'El nombre no puede tener más de :max caracteres.',
                                ]),

                            TextInput::make('phone')
                                ->label('Teléfono')
                                ->placeholder('Ej.: 3878-804506')
                                ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Cargalo como se marca desde un celular. Para clientes de otras provincias, con +54 9. En el boleto se puede tocar para llamar.')
                                ->tel()
                                ->required()
                                ->maxLength(20)
                                ->regex('/^\+?[\d\s\-()]{6,20}$/')
                                ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                                    if (strlen(preg_replace('/\D+/', '', (string) $value) ?? '') < 6) {
                                        $fail(self::PHONE_INVALID_MESSAGE);
                                    }
                                })
                                ->validationMessages([
                                    'required' => 'Ingresá el teléfono.',
                                    'max' => 'El teléfono no puede tener más de :max caracteres.',
                                    'regex' => self::PHONE_INVALID_MESSAGE,
                                ]),
                        ]),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    protected function calendarComponents(): array
    {
        return [
            Section::make('Días de servicio')
                ->description('En fechas sin servicio no se vende ni se crean viajes. Los boletos ya vendidos no cambian.')
                ->columnSpanFull()
                ->schema([
                    CheckboxList::make('service_weekdays')
                        ->label('Días de la semana con servicio')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Quien tenga el permiso «Vender en días sin servicio» puede operar igual. El panel de clientes nunca permite elegir esas fechas.')
                        ->options([
                            '1' => 'Lunes',
                            '2' => 'Martes',
                            '3' => 'Miércoles',
                            '4' => 'Jueves',
                            '5' => 'Viernes',
                            '6' => 'Sábado',
                            '7' => 'Domingo',
                        ])
                        ->columns(4)
                        ->required()
                        ->validationMessages([
                            'required' => 'Elegí al menos un día con servicio.',
                        ]),

                    Repeater::make('service_exceptions')
                        ->label('Excepciones por fecha')
                        ->helperText('Una excepción manda sobre el día de la semana.')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Ej.: «Con servicio» para un domingo especial, «Sin servicio» para un feriado.')
                        ->addActionLabel('Agregar excepción')
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->columns(3)
                        ->schema([
                            DatePicker::make('date')
                                ->label('Fecha')
                                ->displayFormat('d/m/Y')
                                ->minDate(fn (): string => today()->format('Y-m-d'))
                                ->required()
                                ->distinct()
                                ->validationMessages([
                                    'required' => 'Ingresá la fecha.',
                                    'distinct' => 'Hay más de una excepción para la misma fecha.',
                                    'after_or_equal' => 'La fecha no puede ser anterior a hoy.',
                                ]),

                            Select::make('has_service')
                                ->label('Tipo')
                                ->options([
                                    '1' => 'Con servicio',
                                    '0' => 'Sin servicio',
                                ])
                                ->default('0')
                                ->selectablePlaceholder(false)
                                ->required()
                                ->validationMessages([
                                    'required' => 'Elegí el tipo.',
                                ]),

                            TextInput::make('reason')
                                ->label('Motivo')
                                ->placeholder('Opcional, por ejemplo "Navidad"')
                                ->maxLength(100)
                                ->validationMessages([
                                    'max' => 'El motivo no puede tener más de :max caracteres.',
                                ]),
                        ]),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    protected function securityComponents(): array
    {
        return [
            Section::make('Confirmación de contraseña')
                ->description('Se pide la contraseña de quien hace la acción. Si no coincide, la acción no se ejecuta.')
                ->columnSpanFull()
                ->schema([
                    Toggle::make('require_password_ticket_delete')
                        ->label('Al borrar boletos')
                        ->default(false),

                    Toggle::make('require_password_ticket_reschedule')
                        ->label('Al reprogramar boletos')
                        ->default(false),

                    Toggle::make('require_password_ticket_edit')
                        ->label('Al editar boletos')
                        ->default(false),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    protected function clientComponents(): array
    {
        return [
            Section::make('Panel de clientes')
                ->columnSpanFull()
                ->schema([
                    Toggle::make('panel_clientes_activo')
                        ->label('Panel de clientes activo')
                        ->helperText('Desactivado, los pasajeros no pueden entrar a ver sus boletos ni elegir la fecha de un boleto sin fecha.')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Activado, el ingreso del panel de administración muestra el acceso para pasajeros y el logo del link del boleto lleva al ingreso de clientes.')
                        ->default(false)
                        ->live(),

                    TextInput::make('client_advance_notice_hours')
                        ->label('Anticipación mínima para elegir fecha')
                        ->helperText('Los pasajeros solo pueden elegir horarios que salgan con al menos estas horas de anticipación. 0 = sin mínimo.')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Cuenta desde la salida del origen del servicio. Solo afecta al panel de clientes y es el número que dice el aviso del PDF de los boletos sin fecha.')
                        ->visible(fn (Get $get): bool => (bool) $get('panel_clientes_activo'))
                        ->integer()
                        ->minValue(0)
                        ->maxValue(Setting::CLIENT_ADVANCE_NOTICE_HOURS_MAX)
                        ->required()
                        ->default(Setting::CLIENT_ADVANCE_NOTICE_HOURS_DEFAULT)
                        ->suffix('horas')
                        ->validationMessages([
                            'required' => 'Ingresá la cantidad de horas.',
                            'numeric' => 'La cantidad de horas tiene que ser un número.',
                            'integer' => 'La cantidad de horas tiene que ser un número entero.',
                            'min' => 'La cantidad de horas no puede ser negativa.',
                            'max' => 'La cantidad de horas no puede ser mayor a :max.',
                        ]),
                ]),
        ];
    }

    /**
     * @return array<Component>
     */
    protected function appearanceComponents(): array
    {
        return [
            Section::make('Color de la empresa')
                ->columnSpanFull()
                ->schema([
                    ColorPicker::make('primary_color')
                        ->label('Color principal')
                        ->hex()
                        ->live()
                        ->required()
                        ->regex('/^#[0-9a-fA-F]{6}$/')
                        ->default(Setting::PRIMARY_COLOR_DEFAULT)
                        ->helperText('Se aplica en todo el sistema, el link del boleto y los PDF.')
                        ->hintIcon(Heroicon::OutlinedInformationCircle, tooltip: 'Botones y links usan este color exacto; los tonos más claros y más oscuros se generan solos. Sobre un color claro, el texto pasa a oscuro para que se lea.')
                        ->validationMessages([
                            'required' => 'Elegí el color principal.',
                            'regex' => self::PRIMARY_COLOR_INVALID_MESSAGE,
                        ]),

                    // Vista previa con el color elegido (sin guardar): el
                    // sitio sigue con el color guardado hasta "Guardar cambios".
                    View::make('filament.settings.palette-preview')
                        ->viewData(function (Get $get): array {
                            $color = (string) $get('primary_color');
                            $isValid = preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1;

                            return [
                                'palette' => $isValid ? BrandPalette::fromHexAsHex($color) : [],
                                'textColor' => $isValid ? BrandPalette::textColorOn($color) : null,
                            ];
                        }),
                ]),
        ];
    }

    /**
     * Cantidad de campos de la pestaña que tienen error de validación.
     */
    protected function countTabErrors(string $tab): int
    {
        return count(array_filter(
            $this->getErrorBag()->keys(),
            fn (string $key): bool => $this->fieldBelongsToTab($key, $tab),
        ));
    }

    /**
     * Pestañas que tienen al menos uno de los campos con error, en orden.
     *
     * @param  list<string>  $errorKeys
     * @return list<string>
     */
    protected function tabsWithErrors(array $errorKeys): array
    {
        return array_values(array_filter(
            array_keys(self::TAB_FIELDS),
            fn (string $tab): bool => collect($errorKeys)->contains(fn (string $key): bool => $this->fieldBelongsToTab($key, $tab)),
        ));
    }

    protected function fieldBelongsToTab(string $errorKey, string $tab): bool
    {
        foreach (self::TAB_FIELDS[$tab] as $field) {
            if ($errorKey === "data.{$field}" || str_starts_with($errorKey, "data.{$field}.")) {
                return true;
            }
        }

        return false;
    }

    /**
     * El guardado es todo o nada: si falla, se avisa que no se guardó ningún
     * cambio y en qué pestañas están los errores (Filament abre solo la del
     * primero, las demás muestran el badge).
     */
    protected function onValidationError(ValidationException $exception): void
    {
        parent::onValidationError($exception);

        $tabs = $this->tabsWithErrors(array_keys($exception->errors()));

        Notification::make()
            ->title('No se guardaron los cambios')
            ->body($tabs === []
                ? 'Revisá los campos marcados.'
                : 'Revisá los campos marcados en '.collect($tabs)->join(', ', ' y ').'.')
            ->danger()
            ->send();
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

    public function getFormContentComponent(): Component
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

        // Sin el permiso de calendario las excepciones no se reescriben: no se
        // limpia ninguna vencida y la notificación no debe decir que se quitaron.
        $canManageCalendar = $this->canManageCalendar();
        $today = today()->format('Y-m-d');
        $expiredExceptionsCount = $canManageCalendar ? count(array_filter(
            Setting::serviceExceptions(),
            fn (array $exception): bool => $exception['date'] < $today,
        )) : 0;

        // Sin el permiso, la pestaña está oculta: la confirmación de contraseña
        // queda como estaba aunque el pedido la traiga.
        if ($this->canManageSecurity()) {
            foreach ([
                Setting::REQUIRE_PASSWORD_TICKET_DELETE => 'require_password_ticket_delete',
                Setting::REQUIRE_PASSWORD_TICKET_RESCHEDULE => 'require_password_ticket_reschedule',
                Setting::REQUIRE_PASSWORD_TICKET_EDIT => 'require_password_ticket_edit',
            ] as $key => $field) {
                if (array_key_exists($field, $data)) {
                    Setting::set($key, $data[$field] ? '1' : '0');
                }
            }
        }

        Setting::set(
            Setting::VENTA_LIMITE_HORAS,
            (string) max(0, (int) ($data['venta_limite_horas'] ?? Setting::VENTA_LIMITE_HORAS_DEFAULT)),
        );

        Setting::set(
            Setting::PERMITIR_ACOMPANANTES,
            ($data['permitir_acompanantes'] ?? true) ? '1' : '0',
        );

        // Sin el permiso, la sección está oculta: la emisión queda como estaba
        // aunque el pedido la traiga (o no la traiga, que antes la reactivaba).
        if ($this->canManageTicketEmission() && array_key_exists('descarga_automatica_pdf', $data)) {
            Setting::set(
                Setting::DESCARGA_AUTOMATICA_PDF,
                $data['descarga_automatica_pdf'] ? '1' : '0',
            );
        }

        // Oculto (descarga automática activada o sin el permiso), el campo no
        // llega en el estado: se conserva el mensaje guardado. Vacío = texto por defecto.
        if ($this->canManageTicketEmission() && array_key_exists('whatsapp_mensaje', $data)) {
            Setting::set(
                Setting::WHATSAPP_MENSAJE,
                filled($data['whatsapp_mensaje']) ? trim($data['whatsapp_mensaje']) : null,
            );
        }

        $contactLines = collect($data['contact_lines'] ?? [])
            ->map(fn (array $line): array => [
                'name' => filled($line['name'] ?? null) ? trim((string) $line['name']) : null,
                'phone' => trim((string) ($line['phone'] ?? '')),
            ])
            ->filter(fn (array $line): bool => $line['phone'] !== '')
            ->take(Setting::CONTACT_LINES_MAX)
            ->values()
            ->all();
        Setting::set(Setting::CONTACT_LINES, json_encode($contactLines));

        // Sin el permiso, la pestaña está oculta: el calendario queda como
        // estaba aunque el pedido lo traiga.
        if ($canManageCalendar && array_key_exists('service_weekdays', $data)) {
            $weekdays = array_values(array_unique(array_map('intval', $data['service_weekdays'] ?? [])));
            sort($weekdays);
            Setting::set(Setting::SERVICE_WEEKDAYS, json_encode($weekdays));
        }

        if ($canManageCalendar && array_key_exists('service_exceptions', $data)) {
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
        }

        // Sin el permiso, la pestaña está oculta: el color queda como estaba
        // aunque el pedido lo traiga.
        if ($this->canManageAppearance() && array_key_exists('primary_color', $data)) {
            Setting::set(Setting::PRIMARY_COLOR, strtolower((string) $data['primary_color']));
        }

        // Sin el permiso, la pestaña está oculta: el parámetro queda como
        // estaba aunque el pedido lo traiga.
        if ($this->canManageClientPanel() && array_key_exists('panel_clientes_activo', $data)) {
            Setting::set(
                Setting::PANEL_CLIENTES_ACTIVO,
                $data['panel_clientes_activo'] ? '1' : '0',
            );
        }

        // Oculto (panel apagado en el formulario) no llega en el estado: el
        // valor guardado se conserva. Mismo permiso que la pestaña.
        if ($this->canManageClientPanel() && array_key_exists('client_advance_notice_hours', $data)) {
            Setting::set(
                Setting::CLIENT_ADVANCE_NOTICE_HOURS,
                (string) max(0, (int) $data['client_advance_notice_hours']),
            );
        }

        // Sin el permiso, la sección está oculta: el parámetro queda como
        // estaba aunque el pedido lo traiga.
        if ($this->canManageSaleSelectors() && array_key_exists('selectores_detallados', $data)) {
            Setting::set(
                Setting::SELECTORES_DETALLADOS,
                $data['selectores_detallados'] ? '1' : '0',
            );
        }

        if ($this->canManageSaleObservations() && array_key_exists('observaciones_activas', $data)) {
            Setting::set(
                Setting::OBSERVACIONES_VENTA,
                $data['observaciones_activas'] ? '1' : '0',
            );
        }

        // Sin el permiso, la sección está oculta: el precio fijo queda como
        // estaba aunque el pedido lo traiga. Con el interruptor apagado los
        // montos están ocultos, no llegan en el estado y se conservan.
        if ($this->canManageFixedPrice() && array_key_exists('precio_fijo_activo', $data)) {
            Setting::set(
                Setting::PRECIO_FIJO_ACTIVO,
                $data['precio_fijo_activo'] ? '1' : '0',
            );

            foreach ([Setting::PRECIO_FIJO_BOLETO => 'precio_fijo_boleto', Setting::PRECIO_FIJO_SEGURO_MENOR => 'precio_fijo_seguro_menor'] as $key => $field) {
                if ($data['precio_fijo_activo'] && array_key_exists($field, $data)) {
                    Setting::set($key, (string) (int) $data[$field]);
                }
            }
        }

        $body = 'Los cambios se aplicaron correctamente.';

        if ($expiredExceptionsCount === 1) {
            $body .= ' Se quitó 1 excepción de una fecha que ya pasó.';
        } elseif ($expiredExceptionsCount > 1) {
            $body .= " Se quitaron {$expiredExceptionsCount} excepciones de fechas que ya pasaron.";
        }

        Notification::make()
            ->title('Configuración guardada')
            ->body($body)
            ->success()
            ->send();

        // Recargar el formulario con lo persistido (normalizado).
        $this->form->fill($this->getFormData());
    }
}
