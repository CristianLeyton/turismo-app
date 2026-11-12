<?php

namespace App\Filament\Client\Pages;

use App\Support\ClientSession;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Throwable;

/**
 * Primera vez en el panel: la contraseña inicial es el DNI, así que
 * obligamos a elegir una propia antes de mostrar nada.
 * Se llega por el middleware EnsureClientPasswordIsSet, no desde el menú.
 */
class SetPassword extends Page
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?string $nombreApp = null;

    public function mount(): void
    {
        // El default de una propiedad de clase no admite llamadas a
        // funciones (parse error "constant expression"): se resuelve acá.
        $this->nombreApp = (string) config('app.name');

        $this->form->fill();
    }

    /**
     * Ancho de lectura acotado: en escritorio la bienvenida no se estira
     * a toda la pantalla.
     */
    public function getMaxContentWidth(): Width|string|null
    {
        return Width::ThreeExtraLarge;
    }

    public static function getNavigationLabel(): string
    {
        return 'Elegir contraseña';
    }

    public function getTitle(): string
    {
        // Primera vez: recibimos al cliente por su nombre en la app.
        $nombre = ClientSession::current()?->nombre;

        $saludo = trim('¡Bienvenido a '.$this->nombreApp);

        return filled($nombre)
            ? $saludo.', '.$nombre.'!'
            : $saludo.'!';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('salir')
                ->label('Salir')
                ->icon('heroicon-m-arrow-left-on-rectangle')
                ->color('gray')
                ->action(function () {
                    ClientSession::logout();

                    $this->redirect(filament()->getPanel('clientes')->getLoginUrl());
                }),
        ];
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'clave';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.client.set-password'),

            Form::make([EmbeddedSchema::make('form')])
                ->livewireSubmitHandler('guardar')
                ->footer([
                    Actions::make([
                        Action::make('guardar')
                            ->label('Guardar contraseña')
                            ->submit('guardar'),
                    ]),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('password')
                    ->label('Contraseña nueva')
                    ->helperText('Mínimo 6 caracteres.')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->required()
                    ->minLength(6)
                    ->same('passwordConfirmation')
                    ->validationAttribute('la contraseña nueva')
                    ->validationMessages([
                        'required' => 'Elegí una contraseña.',
                        'min' => 'Tiene que tener al menos :min caracteres.',
                        'same' => 'Las contraseñas no coinciden: volvé a escribirlas.',
                    ]),

                TextInput::make('passwordConfirmation')
                    ->label('Repetí la contraseña nueva')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->required()
                    ->dehydrated(false)
                    ->validationAttribute('la repetición')
                    ->validationMessages([
                        'required' => 'Repetí la contraseña para confirmarla.',
                    ]),
            ])
            ->statePath('data');
    }

    public function guardar(): void
    {
        $client = ClientSession::current();

        if ($client === null || ! $client->needsPasswordSetup()) {
            $this->redirect(MyTickets::getUrl(panel: 'clientes'));

            return;
        }

        // Valida (mínimo 6 y que coincidan): el error sale debajo del campo.
        $data = $this->form->getState();

        try {
            $client->update([
                'password' => (string) $data['password'], // cast 'hashed': guarda bcrypt
                'must_change_password' => false,
            ]);
        } catch (Throwable $e) {
            // Queda registrado para el personal; el cliente no ve el detalle técnico.
            report($e);

            Notification::make()
                ->title('No se pudo guardar la contraseña')
                ->body('Ocurrió un problema inesperado. Probá de nuevo en unos minutos o comunicate con boletería.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $this->form->fill();

        Notification::make()
            ->title('¡Contraseña guardada!')
            ->body('De ahora en más entrás con tu DNI y esta contraseña.')
            ->success()
            ->send();

        $this->redirect(MyTickets::getUrl(panel: 'clientes'));
    }
}
