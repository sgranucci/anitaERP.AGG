<?php

namespace App\Console;

use App\Support\Interbanking\InterbankingCalendarioSync;
use Carbon\Carbon;
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

        $horaReclasificacionCheques = (string) config('caja.reclasificar_cheques_diferidos_hora', '06:30');
        $schedule->command('caja:reclasificar-cheques-diferidos')
            ->dailyAt($horaReclasificacionCheques)
            ->runInBackground()
            ->withoutOverlapping(30);

        $schedule->command('interbanking:persistir-saldos-diarios')->daily()->at('07:15');

        $diasMovimientos = max(1, min(60, (int) config('interbanking.movimientos_sync_dias_ventana', 14)));
        $schedule->command('interbanking:persistir-movimientos', ['--dias' => $diasMovimientos])
            ->dailyAt('07:30')
            ->runInBackground()
            ->withoutOverlapping(120);

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

        $schedule->command('rendicion-gastronomia:auditoria-anita')
            ->dailyAt((string) config('rendicion_gastronomia_anita.auditoria_diaria.hora', '07:00'))
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/rendicion-gastronomia-auditoria-schedule.log'))
            ->when(fn () => (bool) config('rendicion_gastronomia_anita.auditoria_diaria.habilitada', true));

        $ventanaAuditoriaCom = max(1, (int) config('recepcion_proveedor.auditoria_asientos_com_diaria.ventana_dias', 7));
        $schedule->command('recepcion-proveedor:auditoria-asientos-com', [
            '--desde' => Carbon::today()->subDays($ventanaAuditoriaCom - 1)->toDateString(),
            '--hasta' => Carbon::today()->toDateString(),
        ])
            ->dailyAt((string) config('recepcion_proveedor.auditoria_asientos_com_diaria.hora', '07:45'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->when(fn () => (bool) config('recepcion_proveedor.auditoria_asientos_com_diaria.habilitada', true));

        $schedule->command('rendicion-estacionamiento:auditoria-anita')
            ->dailyAt((string) config('rendicion_estacionamiento_anita.auditoria_diaria.hora', '07:30'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->when(fn () => (bool) config('rendicion_estacionamiento_anita.auditoria_diaria.habilitada', false));

        $schedule->command('gastronomia:conciliacion-diaria-reporte', [
            '--fecha-desde' => Carbon::yesterday()->toDateString(),
            '--fecha-hasta' => Carbon::yesterday()->toDateString(),
            '--enviar-mail',
            '--requiere-jornada-cerrada',
        ])
            ->dailyAt((string) config('gastronomia.conciliacion_diaria_reporte.hora', '08:00'))
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/conciliacion-diaria-schedule.log'))
            ->when(fn () => (bool) config('gastronomia.conciliacion_diaria_reporte.habilitada', true));

        $schedule->command('gastronomia:cierre-jornada-waitry-automatico', ['--enviar-mail'])
            ->dailyAt((string) config('gastronomia.cierre_jornada_automatico.hora', '09:00'))
            ->runInBackground()
            ->withoutOverlapping(240)
            ->appendOutputTo(storage_path('logs/cierre-jornada-waitry-automatico-schedule.log'))
            ->when(fn () => (bool) config('gastronomia.cierre_jornada_automatico.habilitado', false));

        $intervaloMin = max(5, (int) config('contable_cierre.job_intervalo_minutos', 15));
        $schedule->command('contable:procesar-aperturas-periodo')
            ->cron('*/'.$intervaloMin.' * * * *')
            ->withoutOverlapping(10);

        $schedule->command('queue:verificar-pico')
            ->everyFiveMinutes()
            ->withoutOverlapping(4)
            ->when(fn () => (bool) config('queue.verificacion_pico.habilitada', true));

        $schedule->command('gastronomia:actualizar-costo-mensual-catalogo')
            ->lastDayOfMonth((string) config('gastronomia.costo_mensual_catalogo.hora', '23:30'))
            ->runInBackground()
            ->withoutOverlapping(240)
            ->when(fn () => (bool) config('gastronomia.costo_mensual_catalogo.habilitado', true));
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
