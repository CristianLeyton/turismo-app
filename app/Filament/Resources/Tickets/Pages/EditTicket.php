<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\Pages\EditTicket\EditTicketForm;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketEdit;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Edición de datos de un boleto (correción de errroes de venta).
 *
 * - Permiso: `tickets.edit` (TicketPolicy::edit + canAccess). Nadie lo trae
 *   por defecto; se otorga a un rol desde la matriz y recién ahí aparece la
 *   acción y la ruta funciona.
 * - Alcance: SOLO datos del pasajero (nombre, apellido, DNI, teléfono), el
 *   acompañante (menor/mascota) y los datos económicos del boleto (precio y
 *   método de pago). NO toca viaje, asiento, fecha ni horario: eso es
 *   reprogramación (RescheduleTicket + tickets.reschedule).
 * - Auditoría: una fila por campo cambiado en `ticket_edits` con valor
 *   viejo, valor nuevo, motivo (opcional) y usuario.
 * - Confirmación: el modal de "Guardar cambios" muestra el resumen y, si
 *   Configuración lo exige, pide la contraseña ahí mismo. Si la edición no
 *   se guarda (contraseña, baneado, sin cambios o error), el modal se cierra
 *   y se emite una notificación con el motivo.
 */
class EditTicket extends ResourcePage
{
    use InteractsWithRecord;

    protected static string $resource = TicketResource::class;

    protected static ?string $breadcrumb = 'Editar boleto';

    protected ?string $heading = 'Editar boleto';

    protected static ?string $title = 'Editar boleto';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return Auth::check() && (bool) Auth::user()?->can('tickets.edit');
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();

        $this->form->fill($this->formDataFor($this->getRecord()));
    }

    public function getTitle(): string
    {
        return 'Editar boleto';
    }

    /**
     * Estado inicial del form desde el boleto (y su pasajero / acompañante).
     *
     * @return array<string, mixed>
     */
    protected function formDataFor(Ticket $ticket): array
    {
        $passenger = $ticket->passenger;
        $child = $passenger?->children->first();
        $isReturnLegWithSharedPrice = self::isReturnLegWithSharedPrice($ticket);

        return [
            'last_name' => $passenger?->last_name,
            'first_name' => $passenger?->first_name,
            'dni' => $passenger?->dni,
            'phone_number' => $passenger?->phone_number,
            'travels_with_child' => (bool) $child,
            'child_data' => $child ? [
                'last_name' => $child->last_name,
                'first_name' => $child->first_name,
                'dni' => $child->dni,
            ] : null,
            'travels_with_pets' => (bool) $ticket->travels_with_pets,
            'pet_data' => $ticket->travels_with_pets ? [
                'pet_names' => $ticket->pet_names,
                'pet_count' => $ticket->pet_count,
            ] : null,
            // Tramo de vuelta con precio compartido: el campo de precio se
            // muestra deshabilitado con el precio real (el de la ida, que es
            // `display_price`) para que no parezca que el boleto vale $0.
            // No se guarda: el campo está dehydrated(false) en ese caso.
            'price' => $isReturnLegWithSharedPrice
                ? (float) $ticket->display_price
                : $ticket->price,
            'payment_method' => $ticket->payment_method,
            'edit_reason' => null,
        ];
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function form(Schema $schema): Schema
    {
        return EditTicketForm::configure($schema);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): \Filament\Schemas\Components\Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->footer([
                $this->getFormActionsContentComponent(),
            ]);
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save_edit')
                ->label('Guardar cambios')
                ->color('success')
                ->icon('heroicon-m-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Guardar cambios del boleto')
                ->modalDescription(fn () => $this->confirmationSummary())
                ->modalSubmitActionLabel('Confirmar y guardar')
                ->form(fn (): array => $this->confirmationFormComponents())
                ->action(function (array $data, Action $action): void {
                    $this->saveFromConfirmation($data['confirm_password'] ?? null, $action);
                }),

            Action::make('cancel_edit')
                ->label('Cancelar')
                ->color('gray')
                ->url(fn () => TicketResource::getUrl('view', ['record' => $this->getRecord()->getKey()])),
        ];
    }

    /**
     * Campos del modal de confirmación: solo la contraseña, y solo cuando
     * Configuración exige proteger la edición de boletos. Se valida en el
     * propio modal (required) antes de ejecutar la acción.
     *
     * @return array<int, TextInput>
     */
    public function confirmationFormComponents(): array
    {
        if (! Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_EDIT)) {
            return [];
        }

        return [
            TextInput::make('confirm_password')
                ->label('Tu contraseña')
                ->password()
                ->revealable()
                ->required()
                ->autofocus()
                ->helperText('Confirmá tu clave para guardar los cambios.')
                ->validationMessages([
                    'required' => 'Ingresá tu contraseña para confirmar los cambios.',
                ]),
        ];
    }

    /**
     * Ejecuta el guardado desde el modal: si no se guardó nada, cierra el
     * modal (la notificación con el motivo ya quedó enviada).
     */
    public function saveFromConfirmation(?string $password = null, ?Action $action = null): void
    {
        if (! $this->save($password)) {
            $action?->cancel();
        }
    }

    public function getFormActionsContentComponent(): \Filament\Schemas\Components\Component
    {
        return Actions::make($this->getFormActions())
            ->alignment('left')
            ->fullWidth(false);
    }

    /**
     * Resumen de cambios para el modal de confirmación: lista los campos que
     * difieren entre el estado del form y el boleto actual.
     */
    protected function confirmationSummary(): string
    {
        try {
            $data = $this->form->getRawState();
        } catch (\Throwable) {
            return 'Se guardarán los cambios del formulario.';
        }

        $ticket = $this->getRecord();
        $labels = [];
        $labels['first_name'] = 'Nombre';
        $labels['last_name'] = 'Apellido';
        $labels['dni'] = 'DNI';
        $labels['phone_number'] = 'Teléfono';

        $details = [];

        // Pasajero
        foreach (['first_name', 'last_name', 'dni', 'phone_number'] as $field) {
            $old = (string) ($ticket->passenger?->{$field} ?? '');
            $new = (string) ($data[$field] ?? $old);
            if ($old !== $new) {
                $details[] = "{$labels[$field]}: \"$old\" → \"$new\"";
            }
        }

        // Menor
        $child = $ticket->passenger?->children->first();
        $childWas = (bool) $child;
        $childWill = (bool) ($data['travels_with_child'] ?? false);
        if ($childWas !== $childWill) {
            $details[] = 'Viaja con menor: '.($childWill ? 'Sí' : 'No');
        } elseif ($childWill) {
            $childNameOld = trim("{$child->last_name} {$child->first_name}");
            $childNameNew = trim(($data['child_data']['last_name'] ?? '').' '.($data['child_data']['first_name'] ?? ''));
            if ($childNameOld !== $childNameNew) {
                $details[] = "Menor: \"$childNameOld\" → \"$childNameNew\"";
            }
            $cdnOld = (string) ($child->dni ?? '');
            $cdnNew = (string) ($data['child_data']['dni'] ?? '');
            if ($cdnOld !== $cdnNew) {
                $details[] = "DNI del menor: \"$cdnOld\" → \"$cdnNew\"";
            }
        }

        // Mascota
        $petWas = (bool) $ticket->travels_with_pets;
        $petWill = (bool) ($data['travels_with_pets'] ?? false);
        if ($petWas !== $petWill) {
            $details[] = 'Viaja con mascota: '.($petWill ? 'Sí' : 'No');
        } elseif ($petWill) {
            $pnOld = (string) ($ticket->pet_names ?? '');
            $pnNew = trim((string) ($data['pet_data']['pet_names'] ?? ''));
            if ($pnOld !== $pnNew) {
                $details[] = "Mascotas: \"$pnOld\" → \"$pnNew\"";
            }
        }

        // Venta (no en tramo de vuelta con precio compartido)
        if (! self::isReturnLegWithSharedPrice($ticket)) {
            $old = (float) $ticket->price;
            $new = (float) ($data['price'] ?? $old);
            if ($old !== $new) {
                $details[] = 'Precio: $'.number_format($old, 0, ',', '.').' → $'.number_format($new, 0, ',', '.');
            }

            $pmOld = (string) $ticket->payment_method;
            $pmNew = (string) ($data['payment_method'] ?? $pmOld);
            if ($pmOld !== $pmNew) {
                $pmOldLabel = \App\Models\PaymentMethod::label($pmOld);
                $pmNewLabel = \App\Models\PaymentMethod::label($pmNew);
                $details[] = "Método de pago: \"$pmOldLabel\" → \"$pmNewLabel\"";
            }
        }

        if ($details === []) {
            return 'Sin cambios para guardar.';
        }

        return "Vas a guardar estos cambios:\n• ".implode("\n• ", $details);
    }

    /**
     * ¿El tramo de vuelta comparte el precio con el boleto de ida?
     * En las ventas de ida y vuelta el precio viaja en el boleto de ida y la
     * vuelta queda en 0: mostrarlo/editarlo acá duplicaría el dato.
     */
    public static function isReturnLegWithSharedPrice(Ticket $ticket): bool
    {
        return $ticket->isPartOfRoundTrip()
            && is_null($ticket->return_trip_id)
            && (float) $ticket->price === 0.0;
    }

    /**
     * Guarda la edición.
     *
     * Devuelve false cuando NO se guardó nada: en ese caso ya se envió una
     * notificación explicando el problema (y quien llama cierra el modal).
     */
    public function save(?string $password = null): bool
    {
        $this->authorizeAccess();

        try {
            return $this->performSave($password);
        } catch (ValidationException $exception) {
            // Los errores de validación se pintan en cada campo y, como el
            // modal se cierra, además se resumen en la notificación.
            foreach ($exception->errors() as $field => $messages) {
                foreach ((array) $messages as $message) {
                    $this->addError($field, (string) $message);
                }
            }

            $this->notifyProblem(
                'No se pudo guardar la edición',
                'Revisá los datos marcados en rojo. '.collect($exception->errors())->flatten()->take(3)->implode(' '),
            );

            return false;
        } catch (\Throwable $problem) {
            report($problem);

            $detail = trim((string) $problem->getMessage());

            $this->notifyProblem(
                'No se pudo guardar la edición',
                $detail !== '' ? mb_substr($detail, 0, 300) : 'Ocurrió un error inesperado al guardar los cambios.',
            );

            return false;
        }
    }

    /**
     * Guardado propiamente dicho (sin manejo de errores: eso es de save()).
     */
    protected function performSave(?string $password): bool
    {
        /** @var Ticket $ticket */
        $ticket = $this->getRecord();
        $data = $this->form->getState();

        // Protección con contraseña (opt-in vía Configuración). El campo vive
        // en el modal de confirmación; acá se revalida server-side para que un
        // llamado directo a save() no pueda saltearla.
        if (Setting::getBool(Setting::REQUIRE_PASSWORD_TICKET_EDIT)
            && ! Hash::check((string) $password, (string) (Auth::user()?->password ?? ''))) {
            $this->notifyProblem(
                'Contraseña incorrecta',
                'La contraseña no coincide con tu usuario. No se guardó ningún cambio.',
            );

            return false;
        }

        $reason = trim((string) ($data['edit_reason'] ?? '')) ?: null;
        $passenger = $ticket->passenger;
        $child = $passenger?->children->first();
        $isReturnLegWithSharedPrice = self::isReturnLegWithSharedPrice($ticket);

        // 0. Defensa server-side: el pasajero (titular y menor) no puede ser
        // un cliente baneado (can_buy = false). Espeja la regla del form DNI.
        $dnis = collect([$data['dni'] ?? null, $data['child_data']['dni'] ?? null])
            ->filter(fn ($dni) => filled($dni))
            ->unique()
            ->values();

        if ($dnis->isNotEmpty()) {
            $banned = \App\Models\Clients::query()
                ->whereIn('dni', $dnis->all())
                ->where('can_buy', false)
                ->get();

            if ($banned->isNotEmpty()) {
                $details = $banned
                    ->map(fn ($c) => "{$c->nombre} {$c->apellido} (DNI {$c->dni})".(filled($c->comments) ? " - Motivo: {$c->comments}" : ''))
                    ->implode('; ');

                $this->notifyProblem(
                    'No se puede guardar la edición',
                    'Hay pasajeros con prohibición de compra: '.$details,
                    icon: 'heroicon-m-shield-exclamation',
                );

                return false;
            }
        }

        $changes = [];

        // ---- 1. Datos del pasajero ----
        $passengerChanges = [];
        foreach (['first_name', 'last_name', 'dni', 'phone_number'] as $field) {
            $old = (string) ($passenger?->{$field} ?? '');
            $new = (string) ($data[$field] ?? $old);
            if ($old !== $new) {
                $passengerChanges[$field] = $new;
                $changes[] = [$field, $old, $new];
            }
        }

        // ---- 2. Menor acompañante ----
        $childData = $data['child_data'] ?? [];
        $childFlagOld = (bool) $child;
        $childFlagNew = (bool) ($data['travels_with_child'] ?? false);

        if ($childFlagNew) {
            $childNameOld = $child ? trim("{$child->last_name} {$child->first_name}") : null;
            $childNameNew = trim(
                ($childData['last_name'] ?? '').' '.($childData['first_name'] ?? '')
            );

            if ($childNameNew !== '' && ($child === null || $childNameOld !== $childNameNew)) {
                $changes[] = ['child_name', $childNameOld, $childNameNew];
            }

            $childDniOld = $child?->dni;
            $childDniNew = filled($childData['dni'] ?? null) ? (string) $childData['dni'] : null;

            if ($childDniNew !== null && (string) ($childDniOld ?? '') !== $childDniNew) {
                $changes[] = ['child_dni', $childDniOld, $childDniNew];
            }
        }

        if ($childFlagOld !== $childFlagNew) {
            $changes[] = ['travels_with_child', $childFlagOld ? 'Sí' : 'No', $childFlagNew ? 'Sí' : 'No'];
        }

        // ---- 3. Mascota ----
        $petData = $data['pet_data'] ?? [];
        $petFlagOld = (bool) $ticket->travels_with_pets;
        $petFlagNew = (bool) ($data['travels_with_pets'] ?? false);

        if ($petFlagNew) {
            $petNamesOld = (string) ($ticket->pet_names ?? '');
            $petNamesNew = trim((string) ($petData['pet_names'] ?? ''));

            if ($petNamesNew !== $petNamesOld) {
                $changes[] = ['pet_names', $petNamesOld, $petNamesNew];
            }

            $petCountOld = $ticket->pet_count;
            $petCountNew = filled($petData['pet_count'] ?? null) ? (int) $petData['pet_count'] : null;

            if ((string) ($petCountOld ?? '') !== (string) ($petCountNew ?? '')) {
                $changes[] = ['pet_count', $petCountOld, $petCountNew];
            }
        }

        if ($petFlagOld !== $petFlagNew) {
            $changes[] = ['travels_with_pets', $petFlagOld ? 'Sí' : 'No', $petFlagNew ? 'Sí' : 'No'];
        }

        // ---- 4. Datos económicos (no en tramos de vuelta con precio compartido) ----
        if (! $isReturnLegWithSharedPrice) {
            $priceNew = $data['price'] ?? null;

            if ($priceNew !== null && (float) $priceNew !== (float) $ticket->price) {
                $changes[] = ['price', $ticket->price, $priceNew];
            }

            $paymentNew = $data['payment_method'] ?? null;

            if (filled($paymentNew) && (string) $ticket->payment_method !== (string) $paymentNew) {
                $changes[] = ['payment_method', $ticket->payment_method, $paymentNew];
            }
        }

        if ($changes === []) {
            Notification::make()
                ->title('No hay cambios para guardar')
                ->body('Modificá al menos un campo o cancelá la edición.')
                ->warning()
                ->send();

            return false;
        }

        DB::transaction(function () use ($ticket, $passenger, $changes, $passengerChanges, $child, $childFlagNew, $childData, $petFlagNew, $petData, $isReturnLegWithSharedPrice, $data, $reason) {
            // Pasajero titular.
            if ($passengerChanges !== []) {
                $passenger->update($passengerChanges);
            }

            // Menor acompañante: crear / actualizar / eliminar según el flag.
            $childPayload = [
                'first_name' => trim((string) ($childData['first_name'] ?? '')),
                'last_name' => trim((string) ($childData['last_name'] ?? '')),
                'dni' => filled($childData['dni'] ?? null) ? $childData['dni'] : null,
                'parent_passenger_id' => $passenger->id,
                'passenger_type' => 'child',
            ];

            if ($childFlagNew && filled($childPayload['first_name']) && filled($childPayload['last_name'])) {
                if ($child) {
                    $child->update($childPayload);
                } else {
                    $passenger->children()->create($childPayload);
                }
            } elseif (! $childFlagNew) {
                // Desmarcó el acompañante: se borran los pasajeros hijos.
                $passenger->children()->delete();
            }

            // Boleto.
            $ticket->refresh();
            $ticket->travels_with_child = $childFlagNew;
            $ticket->travels_with_pets = $petFlagNew;
            $ticket->pet_names = $petFlagNew ? trim((string) ($petData['pet_names'] ?? '')) ?: null : null;
            $ticket->pet_count = $petFlagNew ? (filled($petData['pet_count'] ?? null) ? (int) $petData['pet_count'] : null) : null;

            if (! $isReturnLegWithSharedPrice) {
                if (filled($data['payment_method'] ?? null)) {
                    $ticket->payment_method = $data['payment_method'];
                }
                if (filled($data['price'] ?? null)) {
                    $ticket->price = $data['price'];
                }
            }
            $ticket->save();

            // Auditoría: una fila por campo cambiado.
            foreach ($changes as [$field, $old, $new]) {
                TicketEdit::create([
                    'ticket_id' => $ticket->id,
                    'field' => $field,
                    'field_label' => EditTicketForm::fieldLabel($field),
                    'old_value' => $old === null ? null : (string) $old,
                    'new_value' => $new === null ? null : (string) $new,
                    'reason' => $reason,
                    'user_id' => Auth::id(),
                ]);
            }
        });

        Notification::make()
            ->title('Boleto actualizado')
            ->body('Los cambios quedaron registrados en el historial del boleto.')
            ->success()
            ->send();

        $this->redirect(TicketResource::getUrl('view', ['record' => $ticket->getKey()]));

        return true;
    }

    /**
     * Notificación de error/persistente para los casos en que la edición no
     * se guarda. El body siempre explica el problema concreto.
     */
    protected function notifyProblem(string $title, string $body, string $icon = 'heroicon-m-exclamation-triangle'): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->danger()
            ->persistent()
            ->send();
    }
}
