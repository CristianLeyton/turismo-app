<?php

namespace App\Filament\Pages\Auth;

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
                $this->getNameFormComponent(),
                TextInput::make('surname')
                    ->label('Apellido')
                    ->disabled(fn() => !Auth::user()?->is_admin)
                    ->maxLength(255)
                    ->nullable()
                    ->validationMessages([
                        'max' => 'El apellido no debe exceder los :max caracteres.',
                    ]),

                TextInput::make('username')
                    ->label('Nombre de usuario')
                    ->minLength(3)
                    ->maxLength(255)
                    ->required()
                    ->unique()
                    ->validationMessages([
                        'min' => 'El nombre de usuario debe tener al menos :min caracteres.',
                        'required' => 'El nombre de usuario es obligatorio.',
                        'max' => 'El nombre de usuario no debe exceder los :max caracteres.',
                        'unique' => 'El nombre de usuario ya está en uso.',
                    ]),
                TextInput::make('phone')
                    ->tel()
                    ->label('Número de teléfono')
                    ->maxLength(15)
                    ->nullable()
                    ->validationMessages([
                        'max' => 'El número de teléfono no debe exceder los :max caracteres.',
                    ]),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }
}
