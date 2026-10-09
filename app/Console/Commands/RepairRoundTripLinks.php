<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

/**
 * Repara los vínculos de las ventas de ida y vuelta (pasaje diferido).
 *
 * Las ventas emitidas como "ida sin fecha + vuelta con fecha" quedaban con la
 * ida sin `return_trip_id`, aunque el tramo de vuelta ya tuviera viaje
 * asignado. Sin ese link, el detalle y el PDF no conocen la vuelta y la
 * validación de orden de la reprogramación no puede comparar la ida contra
 * ella (por eso se podía fechar la ida después de la vuelta).
 *
 * El comando es idempotente: sólo escribe cuando el valor está desfasado y,
 * al leer el viaje de vuelta desde el boleto hermano (`is_return_leg`), no
 * depende del propio `return_trip_id` que está reparando.
 *
 * También reporta los tramos invertidos (ida posterior a la vuelta), que son
 * una inconsistencia de fechas que se corrige reprogramando (no se toca acá).
 */
class RepairRoundTripLinks extends Command
{
    protected $signature = 'tickets:repair-round-trip-links
                            {--dry-run : Reporta lo que haría sin escribir en la base de datos}';

    protected $description = 'Liga los boletos de ida de un ida y vuelta con el viaje de su tramo de vuelta y reporta los tramos invertidos';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $repaired = 0;
        $alreadyOk = 0;
        $withoutReturnTrip = 0;
        $inverted = [];
        $scanned = 0;

        Ticket::query()
            ->where('is_round_trip', true)
            ->where('is_return_leg', false)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(200, function ($outboundTickets) use (
                $dryRun,
                &$repaired,
                &$alreadyOk,
                &$withoutReturnTrip,
                &$inverted,
                &$scanned,
            ): void {
                foreach ($outboundTickets as $outbound) {
                    $scanned++;

                    $returnLeg = Ticket::query()
                        ->where('sale_id', $outbound->sale_id)
                        ->where('passenger_id', $outbound->passenger_id)
                        ->where('is_return_leg', true)
                        ->whereNull('deleted_at')
                        ->first();

                    if (! $returnLeg || $returnLeg->trip_id === null) {
                        // Vuelta inexistente o todavía pendiente: el NULL es correcto.
                        $withoutReturnTrip++;

                        continue;
                    }

                    $expectedReturnTripId = (int) $returnLeg->trip_id;

                    if ((int) ($outbound->return_trip_id ?? 0) !== $expectedReturnTripId) {
                        if (! $dryRun) {
                            $outbound->forceFill(['return_trip_id' => $expectedReturnTripId])->save();
                        }

                        $repaired++;
                        $this->line(($dryRun ? '[dry-run] ' : '') . "Boleto {$outbound->id}: return_trip_id " .
                            ($outbound->return_trip_id ?? 'NULL') . " → {$expectedReturnTripId}");
                    } else {
                        $alreadyOk++;
                    }

                    // Tramos invertidos: la vuelta no puede salir antes que la ida.
                    $outboundDate = $outbound->trip?->trip_date;
                    $returnDate = $returnLeg->trip?->trip_date;

                    if ($outboundDate && $returnDate && $returnDate->lt($outboundDate)) {
                        $inverted[] = [
                            'ticket' => $outbound->id,
                            'ida' => $outboundDate->format('d/m/Y'),
                            'vuelta' => $returnDate->format('d/m/Y'),
                        ];
                    }
                }
            });

        $this->info('Boletos de ida revisados: ' . $scanned);
        $this->info(($dryRun ? 'Vínculos a reparar: ' : 'Vínculos reparados: ') . $repaired);
        $this->line('Ya correctos: ' . $alreadyOk);
        $this->line('Sin viaje de vuelta (NULL correcto): ' . $withoutReturnTrip);

        if ($inverted !== []) {
            $this->newLine();
            $this->warn('Tramos invertidos detectados (la vuelta sale antes que la ida). Corregilos reprogramando el boleto:');
            $this->table(['Boleto', 'Ida', 'Vuelta'], $inverted);
        }

        $this->newLine();
        $this->info($dryRun
            ? 'Simulación finalizada: no se escribió nada (quitá --dry-run para aplicar).'
            : 'Reparación finalizada.');

        return self::SUCCESS;
    }
}
