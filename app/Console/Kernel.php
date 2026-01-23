<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
/*         // Comando de prueba cada minuto
        $schedule->exec('echo "Scheduler test running at " ' . date('Y-m-d H:i:s'))
            ->everyMinute()
            ->description('Test scheduler exec');
            
        // Comando artisan de prueba cada minuto
        $schedule->command('test:schedule')
            ->everyMinute()
            ->description('Test artisan scheduler'); */
            
        // Limpiar reservas expiradas cada 1 minuto para desarrollo

        // Limpiar reservas expiradas cada 5 minutos
        // php artisan app:cleanup-expired-reservations
        $schedule->command('app:cleanup-expired-reservations')
            ->every5Minutes()
            ->description('Clean up expired seat reservations');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
