<?php

namespace App\Filament\Client\Pages;

use App\Models\Clients;
use App\Support\ClientSession;
use Closure;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class ClientAccess extends Page
{
    protected string $view = 'filament.client.identify';

    public ?string $dni = null;

    /** Vacío para clientes que todavía no eligieron contraseña. */
    public ?string $password = null;

    public static function getNavigationLabel(): string
    {
        return 'Ingresar';
    }

    public function getTitle(): string
    {
        return 'Ingresar';
    }

    public static function getSlug(\Filament\Panel $panel = null): string
    {
        return 'ingresar';
    }

    public function buscar(): void
    {
        // Validaciones estilo Filament: el error vive en la bolsa de Livewire
        // y sale resaltado debajo del campo, no como notificación.
        $this->validate([
            'dni' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (ClientSession::normalizeDni($value) === '') {
                        $fail('El DNI debe ser un número.');
                    }
                },
            ],
            'password' => 'required',
        ], [
            'required' => 'El :attribute es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ], [
            'dni' => 'DNI',
            'password' => 'contraseña',
        ]);

        $dni = ClientSession::normalizeDni($this->dni);

        // Rate limit por DNI e IP: frenar el intento de adivinar el DNI de otros.
        $ipBucket = 'client-identify:ip:' . rawurlencode((string) request()->ip());
        $dniBucket = 'client-identify:dni:' . $dni;

        $ipAttempts = (int) Cache::get($ipBucket, 0);
        $dniAttempts = (int) Cache::get($dniBucket, 0);

        if ($ipAttempts >= 8 || $dniAttempts >= 5) {
            Notification::make()
                ->title('Demasiados intentos. Esperá unos minutos y probá de nuevo.')
                ->danger()
                ->send();

            return;
        }

        $client = Clients::query()
            ->where('dni', $dni)
            ->first();

        if (! $client) {
            $this->countFailedAttempt($ipBucket, $dniBucket);

            $this->reset('password');

            // Error de campo: sale resaltado debajo del DNI, como en los forms.
            $this->addError('dni', 'No encontramos ese DNI en el padrón de clientes. Verificá el número o consultá en boletería si compraste sin dar tu DNI.');

            return;
        }

        // Entra siempre con DNI + contraseña: nadie se mete poniendo el DNI
        // solo. La inicial de todo cliente (alta o reset) es su DNI y la
        // tiene hasheada desde la banda, así que el primer ingreso se hace con
        // el DNI escrito también en la contraseña; después la propia.
        if (! Hash::check((string) $this->password, $client->password)) {
            $this->countFailedAttempt($ipBucket, $dniBucket);

            $this->reset('password');

            $this->addError('password', 'La contraseña es incorrecta. Verificá si es tu DNI (la inicial) o la que elegiste; si la olvidaste, consultá en boletería.');

            return;
        }

        Cache::forget($ipBucket);
        Cache::forget($dniBucket);

        // Identifica y deja la identidad en la sesión (único punto autorizado).
        ClientSession::identifyByDni($dni);

        $this->reset('dni', 'password');

        $this->redirect(\App\Filament\Client\Pages\MyTickets::getUrl(panel: 'clientes'));
    }

    /** Un intento fallido (DNI desconocido o contraseña errónea) alimenta el rate limit. */
    private function countFailedAttempt(string $ipBucket, string $dniBucket): void
    {
        Cache::add($ipBucket, 0, now()->addMinute());
        Cache::add($dniBucket, 0, now()->addMinute());
        Cache::increment($ipBucket);
        Cache::increment($dniBucket);
    }
}
