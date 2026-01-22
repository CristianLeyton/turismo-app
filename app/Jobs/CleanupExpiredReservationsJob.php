<?php

namespace App\Jobs;

use App\Models\SeatReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredReservationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $deletedCount = SeatReservation::cleanupExpired();
            
            if ($deletedCount > 0) {
                Log::info("CleanupExpiredReservationsJob: Se eliminaron {$deletedCount} reservas expiradas");
            }
        } catch (\Exception $e) {
            Log::error("CleanupExpiredReservationsJob: Error al limpiar reservas expiradas - " . $e->getMessage());
        }
    }
}
