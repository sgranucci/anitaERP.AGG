<?php

namespace App\Console;

use App\Support\Configuracion\EntornoEmpresaSupport;
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
        $horaCotizacion = (string) config('cotizacion.hora_command', '11:00');
        $schedule->command('cotizacion:leeapi')->dailyAt($horaCotizacion);

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

        // Tras sync IB: reintentar conciliar OP de propuestas ejecutadas
        $schedule->command('compras:bridge-bancario-propuestas', ['--dias' => 14])
            ->dailyAt('08:20')
            ->when(fn () => (bool) config('propuesta_pago.bridge_bancario_habilitado', false))
            ->runInBackground()
            ->withoutOverlapping(30);
        // Red de seguridad: si el worker dedicado de supervisor no está levantado,
        // igual se drenan las importaciones de padrones encoladas desde la pantalla.
        // Con el worker activo esta corrida no encuentra trabajos y sale enseguida.
        $schedule->command('queue:work', [
                'database',
                '--queue' => (string) config('padrones_iibb.cola', 'padrones'),
                '--stop-when-empty',
                '--tries' => 1,
                '--timeout' => (int) config('padrones_iibb.job_timeout', 7200),
            ])
            ->everyMinute()
            ->runInBackground()
            // Expiración corta a propósito: si el proceso muere sin liberar el
            // candado, el drenado se recupera en minutos y no en horas. Que dos
            // workers se solapen no es problema: la cola bloquea cada job.
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/queue-padrones-schedule.log'));

        $schedule->command('padron-iibb-tasa:purge')->monthlyOn(10, '03:00');
        $schedule->command('padron-iibb-arba:purge')->monthlyOn(10, '03:05');
        $schedule->command('padron-iibb-caba:purge')->monthlyOn(10, '03:10');

        // Descarga DFE ARBA + encola import: último día del mes, padrón del mes siguiente.
        $horaArbaSync = (string) config('padrones_iibb.arba.sync_hora', '22:00');
        $periodoArbaSync = (string) config('padrones_iibb.arba.sync_periodo', 'siguiente');
        $schedule->command('padron-iibb-arba:sincronizar', [
                '--periodo' => $periodoArbaSync,
            ])
            ->lastDayOfMonth($horaArbaSync)
            ->runInBackground()
            ->withoutOverlapping(7200)
            ->appendOutputTo(storage_path('logs/padron-iibb-arba-sincronizar-schedule.log'))
            ->when(fn () => (bool) config('padrones_iibb.arba.sync_habilitado', true));

        // Salvo ARBA, los padrones se bajan a mano con clave fiscal. Se avisa antes
        // de que arranque el mes y en los primeros días, que es cuando todavía se
        // puede cargar sin haber facturado con la tasa de descarte.
        $schedule->command('padron-iibb:alertar-vencidos')
            ->cron('0 8 1,3,5,26,28 * *')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/padron-iibb-alertar-vencidos.log'))
            ->when(fn () => (bool) config('padrones_iibb.alertar_vencidos', true));

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

        // Reemplazo firmante: vence_el = último día inclusive → restaura a las 00:05 del día siguiente.
        $schedule->command('configuracion:restaurar-reemplazos-firmante-vencidos')
            ->dailyAt((string) config('arbolaprobacion.reemplazo_firmante_vencidos.hora', '00:05'))
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/arbol-reemplazo-firmante-vencidos.log'));

        $schedule->command('compras:conciliar-cc-proveedor')
            ->dailyAt((string) config('compras.conciliacion_cc_proveedor.hora', '07:40'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/compras-cc-conciliacion-schedule.log'))
            ->when(fn () => (bool) config('compras.conciliacion_cc_proveedor.habilitada', true));

        $schedule->command('compras:alertas-ordencompra-abiertas')
            ->dailyAt((string) config('compras.oc_alertas_abiertas.hora', '08:15'))
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/compras-oc-alertas-abiertas-schedule.log'))
            ->when(fn () => (bool) config('compras.oc_alertas_abiertas.habilitado', true));

        $schedule->command('compras:alertas-contratos-vencimiento')
            ->dailyAt((string) config('compras.contratos_vencimiento.hora', '08:30'))
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/compras-contratos-vencimiento-schedule.log'))
            ->when(fn () => (bool) config('compras.contratos_vencimiento.habilitado', true));

        $schedule->command('contable:verificar-saldos-cuenta-mes', ['--mail' => true])
            ->dailyAt((string) config('contable.saldos_cuenta_mes.integridad_diaria.hora', '06:10'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/contable-saldos-integridad-schedule.log'))
            ->when(fn () => (bool) config('contable.saldos_cuenta_mes.integridad_diaria.habilitada', true));

        // Corre cada hora: cada suscripción define su propio día y hora de envío.
        $schedule->command('contable:distribuir-reportes-definibles')
            ->hourly()
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/contable-reporte-definible-distribucion.log'))
            ->when(fn () => (bool) config('contable.reporte_definible.distribucion.habilitada', true));

        // Cada suscripción de Sueldos define su frecuencia y horario. El comando
        // mantiene un snapshot auditable y segmenta destinatarios sin mezclar nóminas.
        $schedule->command('sueldos:distribuir-reportes-definibles', ['--ejecutar' => true])
            ->hourly()
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/sueldos-reporte-definible-distribucion.log'))
            ->when(fn () => (bool) config('sueldos.reporte_definible.distribucion.habilitada', true));

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

        $ventanaAuditoriaFacturas = max(1, (int) config('comprobante_proveedor_anita.auditoria_diaria.ventana_dias', 7));
        $schedule->command('comprobante-proveedor:auditoria-anita-diaria', [
            '--desde' => Carbon::today()->subDays($ventanaAuditoriaFacturas - 1)->toDateString(),
            '--hasta' => Carbon::today()->toDateString(),
        ])
            ->dailyAt((string) config('comprobante_proveedor_anita.auditoria_diaria.hora', '08:30'))
            ->runInBackground()
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/comprobante-proveedor-anita-auditoria-schedule.log'))
            ->when(fn () => (bool) config('comprobante_proveedor_anita.auditoria_diaria.habilitada', true));

        $ventanaMayorCc = max(1, (int) config('comprobante_proveedor_anita.conciliacion_mayor_cc.ventana_dias', 30));
        $schedule->command('comprobante-proveedor:conciliar-mayor-cc', [
            '--desde' => Carbon::today()->subDays($ventanaMayorCc - 1)->toDateString(),
            '--hasta' => Carbon::today()->toDateString(),
        ])
            ->dailyAt((string) config('comprobante_proveedor_anita.conciliacion_mayor_cc.hora', '08:35'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/comprobante-proveedor-mayor-cc-schedule.log'))
            ->when(fn () => (bool) config('comprobante_proveedor_anita.conciliacion_mayor_cc.habilitada', true));

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

        // Solicitudes de pago — sync Anita→ERP (faltantes + estados). Temporal mientras se pague en Anita.
        $schedule->command('solicitudpago:sincronizar-anita')
            ->dailyAt((string) config('solicitudpago.sync_anita.hora', '06:45'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/solicitudpago-sincronizar-anita-schedule.log'))
            ->when(fn () => (bool) config('solicitudpago.sync_anita.habilitado', true));

        // Solicitudes de pago — generación de hijas por cuota (Anita p-controlsolpm).
        // Gate: SOLICITUDPAGO_GENERAR_CUOTAS_HABILITADO.
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

        // Flash Report AGG: cada suscripción define día y hora de envío.
        $schedule->command('flash:distribuir-reportes')
            ->hourly()
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/flash-reporte-agg-distribucion.log'))
            ->when(fn () => (bool) config('caja.flash_reporte_agg.distribucion_habilitada', true));

        $limiteRegrabarAnitaPedido = max(1, (int) config('facturacion.ANITA_PEDIDO_REGRABAR_LIMITE', 20));
        $schedule->command('ventas:regrabar-anita-pedido', [
            '--ejecutar' => true,
            '--limite' => $limiteRegrabarAnitaPedido,
        ])
            ->everyTenMinutes()
            ->runInBackground()
            ->withoutOverlapping(15)
            ->appendOutputTo(storage_path('logs/pedido-anita-regrabar.log'))
            ->when(fn () => EntornoEmpresaSupport::esElBierzo()
                && (bool) config('facturacion.ANITA_TRAS_RESPUESTA_PEDIDO', true)
                && (bool) config('facturacion.ANITA_PEDIDO_REGRABAR_HABILITADO', true));

        // Flash 14:30 de la jornada de ayer (cerrada). Omite empresa si un usuario ya la cargó.
        $schedule->command('flash:calcular-diario')
            ->dailyAt((string) config('caja.flash_calculo_diario.hora', '14:30'))
            ->runInBackground()
            ->withoutOverlapping(45)
            ->appendOutputTo(storage_path('logs/flash-calculo-diario-schedule.log'))
            ->when(fn () => (bool) config('caja.flash_calculo_diario.habilitado', false));
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
