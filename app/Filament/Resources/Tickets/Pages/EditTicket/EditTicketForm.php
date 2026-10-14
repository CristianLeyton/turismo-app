<?php

namespace App\Filament\Resources\Tickets\Pages\EditTicket;

use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Models\Clients;
use App\Models\PaymentMethod;
use App\Models\Ticket;
use App\Support\LiveValidation;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Livewire\Livewire as LivewireComponent;

/**
 * Formulario de edición de boleto: SOLO campos corregibles sin tocar el
 * inventario (viaje/asiento/fecha se cambian con Reprogramar). Ver
 * EditTicket::save() para el detalle de qué se guarda y en qué historial.
 *
 * La contraseña de confirmación NO vive acá: se pide en el modal de
 * confirmación (ver EditTicket::confirmationFormComponents()).
 */
class EditTicketForm
{
    /** Etiquetas humanas de los campos de auditoría (TicketEdit). */
    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'first_name' => 'Nombre',
            'last_name' => 'Apellido',
            'dni' => 'DNI',
            'phone_number' => 'Teléfono',
            'travels_with_child' => 'Viaja con menor',
            'child_name' => 'Nombre del menor',
            'child_dni' => 'DNI del menor',
            'travels_with_pets' => 'Viaja con mascota',
            'pet_names' => 'Nombres de mascotas',
            'pet_count' => 'Cantidad de mascotas',
            'payment_method' => 'Método de pago',
            'price' => 'Precio',
            default => $field,
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Datos del pasajero')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('last_name')
                            ->label('Apellido')
                            ->required()
                            ->maxLength(60)
                            ->regex('/^[\pL\s\'\-\\.]+$/u')
                            ->validationMessages([
                                'required' => 'El apellido del pasajero es obligatorio.',
                                'max' => 'El apellido no debe superar los :max caracteres.',
                                'regex' => 'El apellido solo puede tener letras, espacios, apóstrofes, guiones y puntos.',
                            ]),
                        TextInput::make('first_name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(60)
                            ->regex('/^[\pL\s\'\-\\.]+$/u')
                            ->validationMessages([
                                'required' => 'El nombre del pasajero es obligatorio.',
                                'max' => 'El nombre no debe superar los :max caracteres.',
                                'regex' => 'El nombre solo puede tener letras, espacios, apóstrofes, guiones y puntos.',
                            ]),
                        TextInput::make('dni')
                            ->label('DNI')
                            ->required()
                            ->numeric()
                            // Se valida al salir del campo (sin apretar Guardar):
                            // el error de cliente baneado aparece en el momento.
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (TextInput $component) => LiveValidation::only($component))
                            ->minValue(1000000)
                            ->maxValue(999999999)
                            ->validationMessages([
                                'required' => 'El DNI del pasajero es obligatorio.',
                                'numeric' => 'El DNI debe ser un número, sin puntos ni guiones.',
                                'min' => 'El DNI no es válido: debe tener al menos 7 dígitos.',
                                'max' => 'El DNI no es válido: no puede tener más de 9 dígitos.',
                            ])
                            ->rule(static fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                                if (! filled($value)) {
                                    return; // el required ya cubre el vacío
                                }

                                // El DNI no es único por boleto (un pasajero puede
                                // comprar todos los boletos que quiera). Lo que se
                                // valida es la prohibición de compra del padron.
                                $banned = Clients::query()
                                    ->where('dni', $value)
                                    ->where('can_buy', false)
                                    ->first();

                                if ($banned) {
                                    $reason = filled($banned->comments) ? " Motivo: {$banned->comments}" : '';
                                    $fail("El cliente {$banned->nombre} {$banned->apellido} (DNI {$banned->dni}) está baneado y no se le pueden vender/editar boletos.{$reason}");
                                }
                            }),
                        TextInput::make('phone_number')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(30)
                            ->columnSpan(1)
                            ->validationMessages([
                                'max' => 'El teléfono no debe superar los :max caracteres.',
                                'regex' => 'El teléfono solo puede tener números, espacios, guiones, puntos y el signo +.',
                            ]),
                    ]),

                Section::make('Acompañante del pasajero')
                    ->columnSpanFull()
                    ->schema([
                        Checkbox::make('travels_with_child')
                            ->label('Viaja con un menor')
                            ->live(),
                        Grid::make(3)
                            ->visible(fn (callable $get) => (bool) $get('travels_with_child'))
                            ->schema([
                                TextInput::make('child_data.last_name')
                                    ->label('Apellido del menor')
                                    ->regex('/^[\pL\s\'\-\\.]+$/u')
                                    ->maxLength(60)
                                    ->validationMessages([
                                        'max' => 'El apellido del menor no debe superar los :max caracteres.',
                                        'regex' => 'El apellido del menor solo puede tener letras, espacios, apóstrofes, guiones y puntos.',
                                    ]),
                                TextInput::make('child_data.first_name')
                                    ->label('Nombre del menor')
                                    ->regex('/^[\pL\s\'\-\\.]+$/u')
                                    ->maxLength(60)
                                    ->validationMessages([
                                        'max' => 'El nombre del menor no debe superar los :max caracteres.',
                                        'regex' => 'El nombre del menor solo puede tener letras, espacios, apóstrofes, guiones y puntos.',
                                    ]),
                                TextInput::make('child_data.dni')
                                    ->label('DNI del menor')
                                    ->numeric()
                                    ->minValue(1000000)
                                    ->maxValue(999999999)
                                    ->validationMessages([
                                        'numeric' => 'El DNI del menor debe ser un número, sin puntos ni guiones.',
                                        'min' => 'El DNI del menor no es válido: debe tener al menos 7 dígitos.',
                                        'max' => 'El DNI del menor no es válido: no puede tener más de 9 dígitos.',
                                    ]),
                            ]),
                        Checkbox::make('travels_with_pets')
                            ->label('Viaja con mascota')
                            ->live(),
                        Grid::make(2)
                            ->visible(fn (callable $get) => (bool) $get('travels_with_pets'))
                            ->schema([
                                TextInput::make('pet_data.pet_names')
                                    ->label('Nombre(s) de mascota(s)')
                                    ->maxLength(120)
                                    ->validationMessages([
                                        'max' => 'Los nombres de las mascotas no deben superar los :max caracteres.',
                                    ]),
                                Select::make('pet_data.pet_count')
                                    ->label('Cantidad de mascotas')
                                    ->options(array_combine(range(1, 5), range(1, 5)))
                                    ->native(false)
                                    ->validationMessages([
                                        'in' => 'La cantidad de mascotas debe estar entre 1 y 5.',
                                    ]),
                            ]),
                    ]),

                // Siempre visible (aunque el tramo comparta precio con la ida):
                // en ese caso los campos se deshabilitan con su valor real,
                // no se ocultan, para que el form nunca quede con datos de otro.
                Section::make('Datos de la venta')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('price')
                                ->label('Precio')
                                ->prefix('$')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(999999)
                                ->disabled(fn (): bool => self::isSharedPriceReturnLeg())
                                ->dehydrated(fn (): bool => ! self::isSharedPriceReturnLeg())
                                ->hint(
                                    fn () => self::isSharedPriceReturnLeg()
                                        ? 'El precio viaja en el boleto de ida'
                                        : null
                                )
                                ->validationMessages([
                                    'numeric' => 'El precio debe ser un número.',
                                    'min' => 'El precio no puede ser negativo.',
                                    'max' => 'El precio no puede ser mayor a 999999.',
                                ]),
                            Select::make('payment_method')
                                ->label('Método de pago')
                                ->native(false)
                                ->searchable()
                                ->options(fn (): array => self::paymentMethodOptions(self::livewireRecord()))
                                ->disabled(fn (): bool => self::isSharedPriceReturnLeg())
                                ->dehydrated(fn (): bool => ! self::isSharedPriceReturnLeg())
                                ->validationMessages([
                                    'in' => 'El método de pago seleccionado no es válido.',
                                ]),
                        ]),
                    ]),

                Section::make('Motivo de la corrección')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('edit_reason')
                            ->label('Motivo (opcional)')
                            ->maxLength(250)
                            ->columnSpanFull()
                            ->helperText('Se guarda en el historial del boleto junto con cada campo cambiado.')
                            ->validationMessages([
                                'max' => 'El motivo no debe superar los :max caracteres.',
                            ]),
                    ]),

                Text::make('El viaje, la fecha y el horario del boleto no se editan acá: para eso está la acción Reprogramar.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Opciones del Select de método de pago: los métodos activos, más el del
     * propio boleto si quedó inactivo o eliminado después de la venta (así el
     * campo nunca se ve vacío y no se pierde el dato histórico).
     *
     * @return array<string, string>
     */
    public static function paymentMethodOptions(?Ticket $record): array
    {
        $options = PaymentMethod::options();
        $current = $record?->payment_method;

        if (filled($current) && ! array_key_exists($current, $options)) {
            $options[$current] = PaymentMethod::label($current).' (inactivo)';
        }

        return $options;
    }

    /**
     * Tramo de vuelta con precio compartido: no se edita acá.
     *
     * Ojo: en esta página los closures del schema NO reciben `$record`
     * (el schema no está atado al modelo y resuelve null), así que el boleto
     * se lee del componente Livewire con livewireRecord().
     */
    private static function isSharedPriceReturnLeg(): bool
    {
        $record = self::livewireRecord();

        return $record instanceof Ticket && EditTicket::isReturnLegWithSharedPrice($record);
    }

    /** Closures de acceso usados por EditTicket (evita duplicar lógica). */
    public static function livewireRecord(): ?Ticket
    {
        $livewire = LivewireComponent::current();

        if ($livewire instanceof EditTicket) {
            $record = $livewire->getRecord();

            return $record instanceof Ticket ? $record : null;
        }

        return null;
    }
}
