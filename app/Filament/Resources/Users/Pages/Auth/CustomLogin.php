<?php

namespace app\Filament\Resources\Users\Pages\Auth;

use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;

class CustomLogin extends Login
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    protected function getEmailFormComponent(): \Filament\Schemas\Components\Component
    {
        return TextInput::make('username')
            ->label(__('Nombre de usuario'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'username' => $data['username'] ?? null,
            'password' => $data['password'] ?? null,
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'data.username' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
