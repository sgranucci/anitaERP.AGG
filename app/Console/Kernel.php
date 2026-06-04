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

        $schedule->command('arca:solicitar-caea-quincenal')
            ->dailyAt('06:30')
            ->when(fn () => config('arca.caea.pedido_automatico', true));

        $schedule->command('arca:monitorear-conectividad')
            ->everyFiveMinutes()
            ->withoutOverlapping(8)
            ->when(fn () => filter_var(config('arca.monitor_conectividad.habilitado', true), FILTER_VALIDATE_BOOLEAN));

        $schedule->command('prestamo:recordatorios')->dailyAt('07:30');

        $schedule->command('gastronomia:auditoria-anita-diaria')
            ->dailyAt((string) config('gastronomia.auditoria_anita_diaria.hora', '06:30'))
            ->runInBackground()
            ->withoutOverlapping(180)
            ->when(fn () => (bool) config('gastronomia.auditoria_anita_diaria.habilitada', true));
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
