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

        $schedule->command('bitacora-acceso:purge')
            ->dailyAt('03:20')
            ->withoutOverlapping(60)
            ->when(fn () => (bool) config('bitacora_acceso.habilitado', false));

        $schedule->command('gastronomia:purge-anita-caches')
            ->dailyAt((string) config('gastronomia.anita_storage_cache_purge.hora', '03:40'))
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/purge-anita-caches-schedule.log'))
            ->when(fn () => (bool) config('gastronomia.anita_storage_cache_purge.habilitado', true));

        $schedule->command('arca:solicitar-caea-quincenal')
            ->dailyAt('06:30')
            ->when(fn () => config('arca.caea.pedido_automatico', true));

        $schedule->command('arca:auditar-proveedores-facturas-apocrifas')
            ->dailyAt((string) config('arca_wsapoc.auditoria_nocturna.hora', '05:30'))
            ->runInBackground()
            ->withoutOverlapping(240)
            ->appendOutputTo(storage_path('logs/arca-wsapoc-auditoria-schedule.log'))
            ->when(fn () => filter_var(config('arca_wsapoc.auditoria_nocturna.habilitada', true), FILTER_VALIDATE_BOOLEAN));

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

        $ventanaAuditoriaOc = max(1, (int) config('ordencompra_anita.auditoria_diaria.ventana_dias', 7));
        $schedule->command('ordencompra:auditoria-anita-diaria', [
            '--desde' => Carbon::today()->subDays($ventanaAuditoriaOc - 1)->toDateString(),
            '--hasta' => Carbon::today()->toDateString(),
        ])
            ->dailyAt((string) config('ordencompra_anita.auditoria_diaria.hora', '07:50'))
            ->runInBackground()
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/ordencompra-anita-auditoria-schedule.log'))
            ->when(fn () => (bool) config('ordencompra_anita.auditoria_diaria.habilitada', true));

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

        // Auto-sanado del Informe Z desde el proceso (idempotente): regenera solo las jornadas cuyo
        // recomputo desde las órdenes del proceso coincide con lo contabilizado (Z desactualizado por
        // órdenes tardías). Corre antes de la conciliación para que el mail de la mañana ya esté sano.
        $diasAtrasZ = max(1, (int) config('gastronomia.regenerar_z_desde_proceso.dias_atras', 2));
        $schedule->command('gastronomia:regenerar-z-desde-proceso', [
            '--fecha-desde' => Carbon::today()->subDays($diasAtrasZ)->toDateString(),
            '--fecha-hasta' => Carbon::yesterday()->toDateString(),
            '--aplicar',
        ])
            ->dailyAt((string) config('gastronomia.regenerar_z_desde_proceso.hora', '07:45'))
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/regenerar-z-desde-proceso-schedule.log'))
            ->when(fn () => (bool) config('gastronomia.regenerar_z_desde_proceso.habilitado', true));

        // Auditoría mensual por medio de cobro (Z ↔ contabilizado, ERP sin ctamov): mes a la fecha, mail diario.
        // fecha-hasta = hoy − dias_atras (default 2): a las 09:15 el día de ayer aún no tiene asiento cerrado.
        $diasAtrasMedios = max(1, (int) config('gastronomia.auditoria_medios_mensual.dias_atras', 2));
        $fechaHastaMedios = Carbon::today()->subDays($diasAtrasMedios);
        $fechaDesdeMedios = $fechaHastaMedios->copy()->startOfMonth();
        $schedule->command('gastronomia:control-mensual-medios', [
            '--fecha-desde' => $fechaDesdeMedios->toDateString(),
            '--fecha-hasta' => $fechaHastaMedios->toDateString(),
            '--empresas' => implode(',', (array) config('gastronomia.auditoria_medios_mensual.empresas_ids', [1, 2, 3])),
            '--enviar-mail',
        ])
            ->dailyAt((string) config('gastronomia.auditoria_medios_mensual.hora', '09:15'))
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/auditoria-medios-mensual-schedule.log'))
            ->when(fn () => (bool) config('gastronomia.auditoria_medios_mensual.habilitada', true));

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

        $schedule->command('contable:procesar-cierres-periodo')
            ->cron('*/'.$intervaloMin.' * * * *')
            ->withoutOverlapping(10);

        $intervaloMailFacturas = max(1, (int) config('precarga_comprobante_mail.intervalo_minutos', 5));
        $schedule->command('compras:ingestar-facturas-mail')
            ->cron('*/'.$intervaloMailFacturas.' * * * *')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/ingesta-facturas-mail-schedule.log'))
            ->when(fn () => (bool) config('precarga_comprobante_mail.habilitada', false));

        $intervaloBatchFacturas = max(1, (int) config('precarga_comprobante_batch_ia.intervalo_minutos', 5));
        $schedule->command('compras:ingestar-facturas-batch-ia')
            ->cron('*/'.$intervaloBatchFacturas.' * * * *')
            ->withoutOverlapping(30)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/ingesta-facturas-batch-ia-schedule.log'))
            ->when(fn () => (bool) config('precarga_comprobante_batch_ia.habilitada', false));

        $schedule->command('queue:verificar-pico')
            ->everyFiveMinutes()
            ->withoutOverlapping(4)
            ->when(fn () => (bool) config('queue.verificacion_pico.habilitada', true));

        $schedule->command('gastronomia:actualizar-costo-mensual-catalogo')
            ->dailyAt((string) config('gastronomia.costo_mensual_catalogo.hora', '07:00'))
            ->runInBackground()
            ->withoutOverlapping(240)
            ->appendOutputTo(storage_path('logs/costo-mensual-catalogo-schedule.log'))
            ->when(fn () => (bool) config('gastronomia.costo_mensual_catalogo.habilitado', true));

        // Cierre de mes: última pasada del costo catálogo (lista 5000+mes) tras la jornada.
        $schedule->command('gastronomia:actualizar-costo-mensual-catalogo')
            ->lastDayOfMonth((string) config('gastronomia.costo_mensual_catalogo.hora_ultimo_dia_mes', '23:30'))
            ->runInBackground()
            ->withoutOverlapping(240)
            ->appendOutputTo(storage_path('logs/costo-mensual-catalogo-schedule.log'))
            ->when(fn () => (bool) config('gastronomia.costo_mensual_catalogo.habilitado', true));

        // Solicitudes de pago — generación de hijas por cuota (Anita p-controlsolpm).
        // Cron armado pero DESACTIVADO por defecto (SOLICITUDPAGO_GENERAR_CUOTAS_HABILITADO=false).
        $horariosCuotasSp = config('solicitudpago.generar_cuotas.horarios', ['08:00', '14:00', '18:00']);
        if (! is_array($horariosCuotasSp) || $horariosCuotasSp === []) {
            $horariosCuotasSp = ['08:00', '14:00', '18:00'];
        }
        foreach ($horariosCuotasSp as $horaCuotaSp) {
            $horaCuotaSp = trim((string) $horaCuotaSp);
            if ($horaCuotaSp === '') {
                continue;
            }
            $schedule->command('solicitudpago:generar-cuotas')
                ->dailyAt($horaCuotaSp)
                ->runInBackground()
                ->withoutOverlapping(60)
                ->appendOutputTo(storage_path('logs/solicitudpago-generar-cuotas-schedule.log'))
                ->when(fn () => (bool) config('solicitudpago.generar_cuotas.habilitado', false));
        }
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
