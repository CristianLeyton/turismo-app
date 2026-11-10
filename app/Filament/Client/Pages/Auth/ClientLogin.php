<?php

namespace App\Filament\Client\Pages\Auth;

use App\Support\ClientSession;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Ingreso al panel de clientes (guard `client`): DNI + contraseña.
 *
 * El límite de intentos, "Recordarme" y la regeneración de la sesión son los
 * del login de Filament. El mensaje de error es el mismo para un DNI
 * desconocido y una contraseña incorrecta, para no revelar quién está en el
 * padrón.
 */
class ClientLogin extends Login
{
    /**
     * Mismo filtro que la respuesta del ingreso: un cliente ya autenticado
     * que reaparece en el login tampoco debe ser devuelto al panel de
     * administración por una URL prevista ajena.
     */
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->to(ClientSession::intendedLoginUrl(Filament::getUrl()));
        }

        $this->form->fill();
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Panel de clientes';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Ingresá con tu DNI';
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('dni')
            ->label('DNI')
            ->required()
            ->inputMode('numeric')
            ->autocomplete('username')
            ->autofocus()
            ->validationMessages([
                'required' => 'El DNI es obligatorio.',
            ]);
    }

    /**
     * @return array{dni: string, password: string|null}
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'dni' => ClientSession::normalizeDni($data['dni'] ?? null),
            'password' => $data['password'] ?? null,
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.dni' => 'El DNI o la contraseña son incorrectos.',
        ]);
    }
}
