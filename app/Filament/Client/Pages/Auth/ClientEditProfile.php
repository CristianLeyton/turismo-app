<?php

namespace App\Filament\Client\Pages\Auth;

use Closure;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

/**
 * Perfil del cliente (panel /clientes, guard `client`): nombre, apellido,
 * teléfono y cambio de contraseña. El DNI es la clave del vínculo con los
 * boletos y no se puede cambiar desde acá.
 */
class ClientEditProfile extends BaseEditProfile
{
    protected static ?string $slug = 'perfil';

    public static function getLabel(): string
    {
        return 'Mi perfil';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->autofocus()
                    ->validationMessages([
                        'required' => 'El nombre es obligatorio.',
                        'max' => 'El nombre no debe exceder los :max caracteres.',
                    ]),

                TextInput::make('apellido')
                    ->label('Apellido')
                    ->required()
                    ->maxLength(255)
                    ->validationMessages([
                        'required' => 'El apellido es obligatorio.',
                        'max' => 'El apellido no debe exceder los :max caracteres.',
                    ]),

                TextInput::make('telefono')
                    ->label('Teléfono')
                    ->tel()
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $telefono = preg_replace('/\s+/', '', (string) $value);

                            if ($telefono === '') {
                                return;
                            }

                            if (! ctype_digit($telefono) || strlen($telefono) < 7 || strlen($telefono) > 12) {
                                $fail('El teléfono debe tener entre 7 y 12 números.');
                            }
                        },
                    ]),

                TextInput::make('dni')
                    ->label('DNI')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('El DNI no se puede cambiar: si está mal, comunicate con nosotros por favor.'),

                $this->getPasswordFormComponent(),

                $this->getPasswordConfirmationFormComponent()
                    ->label('Confirmar nueva contraseña')
                    ->validationMessages([
                        'required' => 'La confirmación de la nueva contraseña es obligatoria.',
                    ]),

                $this->getCurrentPasswordFormComponent()
                    ->label('Contraseña actual')
                    ->belowContent(null)
                    ->validationMessages([
                        'required' => 'La contraseña actual es obligatoria.',
                        'current_password' => 'La contraseña actual es incorrecta.',
                    ]),
            ]);
    }

    /**
     * Igual que la del perfil de Filament, pero con el mínimo de 6 caracteres
     * que pide el panel de clientes al elegir la contraseña.
     */
    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Nueva contraseña')
            ->validationAttribute('la nueva contraseña')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->minLength(6)
            ->autocomplete('new-password')
            ->dehydrated(fn ($state): bool => filled($state))
            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
            ->live(debounce: 500)
            ->same('passwordConfirmation')
            ->validationMessages([
                'min' => 'La nueva contraseña debe tener al menos :min caracteres.',
                'same' => 'Las contraseñas no coinciden.',
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['nombre'] = trim((string) $data['nombre']);
        $data['apellido'] = trim((string) $data['apellido']);

        $telefono = preg_replace('/\s+/', '', (string) ($data['telefono'] ?? ''));
        $data['telefono'] = $telefono !== '' ? $telefono : null;

        if (array_key_exists('password', $data)) {
            $data['must_change_password'] = false;
        }

        return $data;
    }

    /**
     * Tras guardar, vuelve a llenar el formulario con lo persistido en la
     * base: el modo SPA de Livewire puede servir después la página con el
     * estado del HTML horneado antes del guardado.
     */
    protected function afterSave(): void
    {
        $this->fillForm();
    }
}
