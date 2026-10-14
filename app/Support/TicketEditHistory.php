<?php

namespace App\Support;

use App\Models\PaymentMethod;
use App\Models\TicketEdit;
use Illuminate\Support\Collection;

/**
 * Lectura del historial de ediciones de un boleto (`ticket_edits`).
 *
 * En la base se guarda UNA FILA POR CAMPO cambiado. Para mostrarlo se agrupa
 * por edición (mismo usuario, mismo momento y mismo motivo) y se formatean los
 * valores para que se lean como en el resto del panel: el precio como $15.000
 * y el método de pago con su etiqueta, no con el código interno.
 *
 * Lo usan la vista del boleto (tickets/partials/edit-history) y el detalle del
 * Historial general (ticket-audit-detail).
 */
class TicketEditHistory
{
    /**
     * Agrupa las filas de `ticket_edits` en ediciones legibles, de la más
     * reciente a la más antigua.
     *
     * @param  Collection<int, TicketEdit>|null  $edits
     * @return Collection<int, array{user: \App\Models\User|null, at: \Illuminate\Support\Carbon|null, reason: string|null, changes: array<int, array{field: string, label: string, from: string, to: string}>}>
     */
    public static function groups(?Collection $edits): Collection
    {
        if ($edits === null || $edits->isEmpty()) {
            return collect();
        }

        return $edits
            ->sortBy('id')
            ->groupBy(fn (TicketEdit $edit): string => implode('|', [
                (string) $edit->user_id,
                $edit->created_at?->format('Y-m-d H:i:s') ?? '',
                (string) $edit->reason,
            ]))
            ->map(function (Collection $rows): array {
                /** @var TicketEdit $first */
                $first = $rows->first();

                return [
                    'user' => $first->user,
                    'at' => $first->created_at,
                    'reason' => filled($first->reason) ? (string) $first->reason : null,
                    'changes' => $rows
                        ->map(fn (TicketEdit $edit): array => [
                            'field' => $edit->field,
                            'label' => filled($edit->field_label) ? (string) $edit->field_label : $edit->field,
                            'from' => self::display($edit->field, $edit->old_value),
                            'to' => self::display($edit->field, $edit->new_value),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc(fn (array $group): int => $group['at']?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * Resumen para los encabezados: "3 campos corregidos en 2 ediciones."
     *
     * @param  Collection<int, TicketEdit>|null  $edits
     */
    public static function summary(?Collection $edits): string
    {
        $fields = $edits?->count() ?? 0;
        $groups = self::groups($edits)->count();

        return $fields.' '.($fields === 1 ? 'campo corregido' : 'campos corregidos')
            .' en '.$groups.' '.($groups === 1 ? 'edición' : 'ediciones').'.';
    }

    /**
     * Valor legible de un campo del historial (código interno → etiqueta).
     */
    public static function display(string $field, ?string $value): string
    {
        if (! filled($value)) {
            return '—';
        }

        return match ($field) {
            TicketEdit::FIELD_PRICE => '$'.number_format((float) $value, 0, ',', '.'),
            TicketEdit::FIELD_PAYMENT_METHOD => PaymentMethod::label($value),
            default => $value,
        };
    }
}
