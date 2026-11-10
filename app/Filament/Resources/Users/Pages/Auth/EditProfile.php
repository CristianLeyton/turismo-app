<?php

namespace App\Filament\Resources\Users\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Nombre
                $this->getNameFormComponent()
                    ->label('Nombre')
                    ->disabled()
                    ->validationMessages([
                        'required' => 'El nombre es obligatorio.',
                        'max' => 'El nombre no debe exceder los :max caracteres.',
                    ]),

                // Apellido
                TextInput::make('surname')
                    ->label('Apellido')
                    ->maxLength(255)
                    ->disabled()
                    ->validationMessages([
                        'max' => 'El apellido no debe exceder los :max caracteres.',
                    ]),

                /*                 // Correo electrónico
                $this->getEmailFormComponent()
                    ->label('Correo electrónico')
                    ->validationMessages([
                        'required' => 'El correo electrónico es obligatorio.',
                        'email' => 'Ingrese un correo electrónico válido.',
                        'max' => 'El correo electrónico no debe exceder los :max caracteres.',
                    ]),

                // Teléfono
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel()
                    ->maxLength(255)
                    ->validationMessages([
                        'max' => 'El teléfono no debe exceder los :max caracteres.',
                    ]), */

                // Nombre de usuario
                TextInput::make('username')
                    ->label('Nombre de usuario')
                    ->maxLength(255)
                    ->required()
                    ->validationMessages([
                        'required' => 'El nombre de usuario es obligatorio.',
                        'max' => 'El nombre de usuario no debe exceder los :max caracteres.',
                    ]),

                // Cambiar contraseña
                $this->getPasswordFormComponent()
                    ->label('Nueva contraseña')
                    ->validationMessages([
                        'required' => 'La nueva contraseña es obligatoria.',
                        'min' => 'La nueva contraseña debe tener al menos :min caracteres.',
                        'same' => 'Las contraseñas no coinciden.',
                    ]),

                $this->getPasswordConfirmationFormComponent()
                    ->label('Confirmar nueva contraseña')
                    ->validationMessages([
                        'required' => 'La confirmación de la nueva contraseña es obligatoria.',
                        'same' => 'Las contraseñas no coinciden.',
                    ]),

                // Contraseña actual: se solicita al cambiar contraseña
                // o al cambiar el correo electrónico.
                $this->getCurrentPasswordFormComponent()
                    ->label('Contraseña actual')
                    ->validationMessages([
                        'required' => 'La contraseña actual es obligatoria.',
                        'current_password' => 'La contraseña actual es incorrecta.',
                    ]),

                // Roles: solo lectura
                TextInput::make('roles')
                    ->label('Roles')
                    ->formatStateUsing(
                        fn () => Auth::user()
                            ?->roles
                            ?->pluck('name')
                            ->implode(', ') ?? ''
                    )
                    ->readOnly()
                    ->dehydrated(false),
            ]);
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
