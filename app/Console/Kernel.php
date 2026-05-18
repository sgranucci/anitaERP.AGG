<?php

namespace App\Console;

use App\Support\Interbanking\InterbankingCalendarioSync;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected $commands = [
        'App\Console\Commands\LeeCotizacionApi',
    ];

    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('cotizacion:leeapi')->daily()->at('06:00');
        $schedule->command('interbanking:persistir-saldos-diarios')->daily()->at('07:15');

        $schedule->command('interbanking:persistir-transferencias', ['--dias' => 14])
            ->hourly()
            ->weekdays()
            ->when(fn () => InterbankingCalendarioSync::debeSincronizarHorario());

        $schedule->command('interbanking:persistir-transferencias', ['--dias' => 60])
            ->dailyAt('08:00')
            ->when(fn () => InterbankingCalendarioSync::debeSincronizarDiario());
        $schedule->command('padron-iibb-tasa:purge')->monthlyOn(10, '03:00');
        $schedule->command('padron-iibb-arba:purge')->monthlyOn(10, '03:05');
        $schedule->command('padron-iibb-caba:purge')->monthlyOn(10, '03:10');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
