<?php

namespace App\Filament\Client\Pages;

use App\Support\ClientSession;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

/**
 * Primera vez en el panel: la contraseña inicial es el DNI (pública para la
 * boletería), así que obligamos a elegir una propia antes de mostrar nada.
 * Se llega por el middleware EnsureClientIdentified, no desde el menú.
 */
class SetPassword extends Page
{
    protected string $view = 'filament.client.set-password';

    public ?string $nuevaClave = null;

    public ?string $nombreApp = null;

    public ?string $nuevaClaveConfirmacion = null;

    public function mount(): void
    {
        // El default de una propiedad de clase no admite llamadas a
        // funciones (parse error "constant expression"): se resuelve acá.
        $this->nombreApp = (string) env('APP_NAME', '');
    }

    public static function getNavigationLabel(): string
    {
        return 'Elegir contraseña';
    }

    public function getTitle(): string
    {
        // Primera vez: recibimos al cliente por su nombre en la app.
        $nombre = ClientSession::current()?->nombre;

        return filled($nombre)
            ? '¡Bienvenido a' . $this->nombreApp . ' , ' . $nombre . '!'
            : '¡Bienvenido a '. $this->nombreApp . '!';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('salir')
                ->label('Salir')
                ->icon('heroicon-m-arrow-left-on-rectangle')
                ->color('gray')
                ->action(function () {
                    ClientSession::logout();

                    $this->redirect(ClientAccess::getUrl(panel: 'clientes'));
                }),
        ];
    }

    public static function getSlug(\Filament\Panel $panel = null): string
    {
        return 'clave';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function guardar(): void
    {
        $client = ClientSession::current();

        if ($client === null || ! $client->needsPasswordSetup()) {
            $this->redirect(\App\Filament\Client\Pages\MyTickets::getUrl(panel: 'clientes'));

            return;
        }

        // Validaciones estilo Filament (mínimo 6 y que coincidan): el error
        // sale resaltado debajo del campo, no como notificación.
        $this->validate([
            'nuevaClave' => 'required|min:6',
            'nuevaClaveConfirmacion' => 'required|same:nuevaClave',
        ], [
            'required' => 'Es obligatorio.',
            'min' => 'Debe tener al menos 6 caracteres.',
            'same' => 'Las contraseñas no coinciden: volvelas a escribir.',
        ], [
            'nuevaClave' => 'La contraseña nueva',
            'nuevaClaveConfirmacion' => 'La repetición',
        ]);

        $nueva = (string) $this->nuevaClave;

        try {
            $client->update([
                'password' => $nueva, // cast 'hashed': guarda bcrypt
                'must_change_password' => false,
            ]);
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title('No se pudo guardar la contraseña.')
                ->body('Ocurrió un problema: ' . $e->getMessage() . '. Probá de nuevo o consultá en boletería.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $this->reset('nuevaClave', 'nuevaClaveConfirmacion');

        Notification::make()
            ->title('¡Contraseña guardada!')
            ->body('De ahora en más entrás con tu DNI y esta contraseña.')
            ->success()
            ->send();

        $this->redirect(\App\Filament\Client\Pages\MyTickets::getUrl(panel: 'clientes'));
    }
}
