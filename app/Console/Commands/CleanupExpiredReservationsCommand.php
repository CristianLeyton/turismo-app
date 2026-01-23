<?php

namespace App\Console\Commands;

use App\Jobs\CleanupExpiredReservationsJob;
use Illuminate\Console\Command;

class CleanupExpiredReservationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-expired-reservations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired seat reservations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Cleaning up expired reservations...');
        
        try {
            $deletedCount = \App\Models\SeatReservation::cleanupExpired();
            
            if ($deletedCount > 0) {
                $this->info("Successfully deleted {$deletedCount} expired reservations");
            } else {
                $this->info('No expired reservations found');
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error cleaning up expired reservations: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
