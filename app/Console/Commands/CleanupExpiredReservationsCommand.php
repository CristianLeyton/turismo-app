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
        $this->info('Dispatching cleanup job for expired reservations...');
        
        // Dispatch the job to handle the cleanup
        CleanupExpiredReservationsJob::dispatch();
        
        $this->info('Cleanup job dispatched successfully!');
        
        return Command::SUCCESS;
    }
}
