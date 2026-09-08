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
                TextInput::make('name')
                    ->label('Nombre')
                    ->disabled(fn() => !Auth::user()?->is_admin)
                    ->maxLength(255)
                    ->required()
                    ->validationMessages([
                        'required' => 'El nombre es obligatorio.',
                        'max' => 'El nombre no debe exceder los :max caracteres.',
                    ]),
                TextInput::make('surname')
                    ->label('Apellido')
                    ->disabled(fn() => !Auth::user()?->is_admin)
                    ->maxLength(255)
                    ->required()
                    ->validationMessages([
                        'required' => 'El apellido es obligatorio.',
                        'max' => 'El apellido no debe exceder los :max caracteres.',
                    ]),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->disabled(fn() => !Auth::user()?->is_admin)
                    ->maxLength(255)
                    ->email()
                    ->required()
                    ->validationMessages([
                        'required' => 'El correo electrónico es obligatorio.',
                        'email' => 'Ingrese un correo electrónico válido.',
                        'max' => 'El correo electrónico no debe exceder los :max caracteres.',
                    ]),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->disabled(fn() => !Auth::user()?->is_admin)
                    ->maxLength(255)
                    ->tel()
                    ->validationMessages([
                        'max' => 'El teléfono no debe exceder los :max caracteres.',
                    ]),
                TextInput::make('username')
                    ->label('Nombre de usuario')
                    ->disabled(fn() => !Auth::user()?->is_admin)
                    ->maxLength(255)
                    ->required()
                    ->validationMessages([
                        'required' => 'El nombre de usuario es obligatorio.',
                        'max' => 'El nombre de usuario no debe exceder los :max caracteres.',
                    ]),
            ]);
    }
}
