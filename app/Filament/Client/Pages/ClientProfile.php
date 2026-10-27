<?php

namespace App\Filament\Client\Pages;

use App\Support\ClientSession;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Mi perfil (panel de clientes): datos personales y contraseña.
 *
 * Mismo mecanismo que el EditProfile del panel de administración: form
 * Filament real (estado `data`), validaciones nativas resaltadas debajo de
 * cada campo y una sola acción «Guardar cambios».
 *
 * - Editables: nombre, apellido, teléfono (con rules de Filament).
 * - Read-only: el DNI (es la clave que une clientes ↔ pasajeros ↔ boletos).
 * - Contraseña: si se escribe una nueva, pide la actual (validada contra la
 *   del cliente o, si todavía no eligió una, contra su DNI; si la olvidó,
 *   resuelve boletería con el action admin «Resetear contraseña»).
 */
class ClientProfile extends Page
{
    protected string $view = 'filament.client.profile';

    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'Mi perfil';
    }

    public function getTitle(): string
    {
        return 'Mi perfil';
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-user-circle';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getSlug(\Filament\Panel $panel = null): string
    {
        return 'perfil';
    }

    public function mount(): void
    {
        // Sólo los cuatro datos que el form usa: ni la contraseña ni flags
        // internos pueden filtrarse al estado Livewire de la página.
        $client = ClientSession::current();

        $this->form->fill($client ? [
            'nombre' => $client->nombre,
            'apellido' => $client->apellido,
            'telefono' => $client->telefono,
            'dni' => $client->dni,
        ] : []);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            // Igual que el perfil de administración: labels a la izquierda.
            ->inlineLabel(true)
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mis datos')
                    ->columns(2)
                    ->components([
                        TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->autofocus()
                            ->validationMessages([
                                'required' => 'El nombre es obligatorio.',
                                'max' => 'El nombre no debe exceder los :max caracteres.',
                            ]),

                        TextInput::make('apellido')
                            ->label('Apellido')
                            ->required()
                            ->validationMessages([
                                'required' => 'El apellido es obligatorio.',
                                'max' => 'El apellido no debe exceder los :max caracteres.',
                            ]),

                        TextInput::make('telefono')
                            ->label('Teléfono')
                            ->tel()
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    // Espacios de más no invalidan: se limpian antes.
                                    $telefono = preg_replace('/\s+/', '', (string) $value);

                                    if ($telefono === '') {
                                        return;
                                    }

                                    if (! ctype_digit($telefono) || strlen($telefono) < 7 || strlen($telefono) > 12) {
                                        $fail('El teléfono debe tener entre 7 y 12 números.');
                                    }
                                },
                            ])
                            ->validationMessages([
                                'required' => 'El teléfono es obligatorio.',
                            ]),

                        // Read-only: el DNI es la clave del vínculo con los boletos.
                        TextInput::make('dni')
                            ->label('DNI')
                            ->disabled()
                            ->readOnly()
                            ->dehydrated(false)
                            ->helperText('El DNI no se puede cambiar: si está mal, consultá en boletería.'),
                    ]),

                Section::make('Cambiar contraseña')
                    ->components([
                        $this->getNuevaClaveFormComponent(),

                        $this->getNuevaClaveConfirmacionFormComponent(),

                        $this->getClaveActualFormComponent(),
                    ]),
            ]);
    }

    protected function getNuevaClaveFormComponent(): TextInput
    {
        return TextInput::make('nuevaClave')
            ->label('Nueva contraseña')
            ->validationAttribute('la nueva contraseña')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->minLength(6)
            ->autocomplete('new-password')
            // No alcanza con "nueva" vacía: el cambio es optativo.
            ->dehydrated(fn ($state): bool => filled($state))
            // Live para que la confirmación y la actual aparezcan al momento.
            ->live(debounce: 500)
            ->same('nuevaClaveConfirmacion')
            ->validationMessages([
                'min' => 'La nueva contraseña debe tener al menos :min caracteres.',
                'same' => 'Las contraseñas no coinciden.',
            ]);
    }

    protected function getNuevaClaveConfirmacionFormComponent(): TextInput
    {
        return TextInput::make('nuevaClaveConfirmacion')
            ->label('Confirmar nueva contraseña')
            ->validationAttribute('la confirmación de la nueva contraseña')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('new-password')
            ->required()
            ->visible(fn (Get $get): bool => filled($get('nuevaClave')))
            ->dehydrated(false)
            ->validationMessages([
                'required' => 'La confirmación de la nueva contraseña es obligatoria.',
                'same' => 'Las contraseñas no coinciden.',
            ]);
    }

    protected function getClaveActualFormComponent(): TextInput
    {
        return TextInput::make('claveActual')
            ->label('Contraseña actual')
            ->validationAttribute('la contraseña actual')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->visible(fn (Get $get): bool => filled($get('nuevaClave')))
            ->dehydrated(false)
            // No hay auth guard del lado cliente: la actual se contrasta a
            // mano contra el password del cliente (o su DNI, la inicial).
            ->rules([
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $client = ClientSession::current();

                    if ($client === null) {
                        return;
                    }

                    if ($client->hasPersonalPassword()) {
                        $actualOk = Hash::check((string) $value, $client->password);
                    } else {
                        $actualOk = $client->dni !== '' && Hash::check((string) $value, Hash::make($client->dni));
                    }

                    if (! $actualOk) {
                        $fail('La contraseña actual es incorrecta.');
                    }
                },
            ])
            ->validationMessages([
                'required' => 'La contraseña actual es obligatoria.',
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([$this->getSaveFormAction()])
                            ->alignment(Alignment::Start),
                    ]),
            ]);
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label('Guardar cambios')
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    public function save(): void
    {
        $client = ClientSession::current();

        if ($client === null) {
            return;
        }

        // El form valida y muestra los errores debajo de cada campo:
        // si algo falla, getState() corta acá mismo.
        $data = $this->form->getState();

        $update = [
            'nombre' => trim((string) $data['nombre']),
            'apellido' => trim((string) $data['apellido']),
            'telefono' => ($telefono = preg_replace('/\s+/', '', (string) ($data['telefono'] ?? ''))) !== ''
                ? $telefono
                : null,
        ];

        if (filled($data['nuevaClave'] ?? null)) {
            $update['password'] = (string) $data['nuevaClave']; // cast 'hashed': guarda bcrypt
            $update['must_change_password'] = false;
        }

        try {
            $client->update($update);
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('No se pudo guardar el perfil.')
                ->body('Ocurrió un problema: ' . $e->getMessage() . '. Probá de nuevo en unos minutos.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        // Contraseñas fuera del estado, como en el EditProfile del admin.
        $this->data['claveActual'] = null;
        $this->data['nuevaClave'] = null;
        $this->data['nuevaClaveConfirmacion'] = null;

        Notification::make()
            ->title('Perfil actualizado.')
            ->success()
            ->send();
    }
}
