<?php

namespace App\Console;

use App\Support\Caja\TesoreriaCrownAvisoSupport;
use App\Support\Compras\ComprobanteProveedorAnitaBridgeGate;
use App\Support\Configuracion\EmpresaJurisdiccionIibbSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\PercepcionIibbJurisdiccionEntregaSupport;
use App\Support\Interbanking\InterbankingCalendarioSync;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalShoppingCaballitoSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAutomaticoSupport;
use App\Support\Ventas\Tiendanube\TiendanubeStockCatalogoSupport;
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
        $schedule->command('cotizacion:leeapi')
            ->dailyAt($horaCotizacion)
            ->appendOutputTo(storage_path('logs/cotizacion-leeapi.log'));

        // listado.envios_workbench / consulta_inteligente.envios_programados
        // → avisos:despachar-programados (08:05 / 08:10 en ModuloAvisoTipo).
        // $schedule->command('compras:enviar-listados-programados')->dailyAt('08:05');
        // $schedule->command('direccion:enviar-consultas-inteligentes')->dailyAt('08:10');

        $horaReclasificacionCheques = (string) config('caja.reclasificar_cheques_diferidos_hora', '06:30');
        $schedule->command('caja:reclasificar-cheques-diferidos')
            ->dailyAt($horaReclasificacionCheques)
            ->runInBackground()
            ->withoutOverlapping(30);

        $schedule->command('interbanking:persistir-saldos-diarios')
            ->daily()->at('07:15')
            ->when(fn () => ! EntornoEmpresaSupport::esFerli());

        // Macro y BIND a veces publican después de las 07:15. Si el cierre
        // del día hábil anterior no está completo, se vuelve a bajar.
        foreach (['09:30', '11:30', '13:30'] as $horaReintentoSaldos) {
            $schedule->command('interbanking:persistir-saldos-diarios --solo-si-incompleta')
                ->dailyAt($horaReintentoSaldos)
                ->withoutOverlapping(25)
                ->when(fn () => ! EntornoEmpresaSupport::esFerli());
        }

        $diasMovimientos = max(1, min(60, (int) config('interbanking.movimientos_sync_dias_ventana', 14)));
        $schedule->command('interbanking:persistir-movimientos', ['--dias' => $diasMovimientos])
            ->dailyAt('07:30')
            ->runInBackground()
            ->withoutOverlapping(120)
            ->when(fn () => ! EntornoEmpresaSupport::esFerli());

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

        // Índice del tracking de facturas. Resolver PDF, fecha de carga real y
        // estado de pago exige consultar el puente del Anita, así que se
        // materializa de noche: la grilla queda leyendo sólo tablas locales.
        $trackingLote = max(1, (int) config('tracking_facturas.indice.lote', 200));
        $trackingHabilitado = fn () => (bool) config('tracking_facturas.indice.backfill_habilitado', true);

        $schedule->command('tracking-facturas:sincronizar-indice', [
            '--lote' => $trackingLote,
            '--solo-faltantes',
        ])
            ->dailyAt((string) config('tracking_facturas.indice.faltantes_hora', '02:40'))
            ->runInBackground()
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/tracking-facturas-indice.log'))
            ->when($trackingHabilitado);

        // Repaso completo: los pagos de un comprobante ya indexado cambian con
        // el tiempo y el pase de «sólo faltantes» nunca los vuelve a mirar.
        $schedule->command('tracking-facturas:sincronizar-indice', ['--lote' => $trackingLote])
            ->weeklyOn(
                (int) config('tracking_facturas.indice.completo_dia', 0),
                (string) config('tracking_facturas.indice.completo_hora', '03:10'),
            )
            ->runInBackground()
            ->withoutOverlapping(600)
            ->appendOutputTo(storage_path('logs/tracking-facturas-indice.log'))
            ->when($trackingHabilitado);

        // Copia masiva Scan → Facturas_scan: deshabilitada. Lo viejo se sirve
        // desde /scan (ruta del índice) y lo nuevo vive en Facturas_scan.
        $schedule->command('tracking-facturas:migrar-pdf', [
            '--lote' => max(1, (int) config('tracking_facturas.migracion_pdf.lote', 100)),
            '--limite' => max(1, (int) config('tracking_facturas.migracion_pdf.limite_por_corrida', 2000)),
            '--ejecutar',
        ])
            ->dailyAt((string) config('tracking_facturas.migracion_pdf.hora', '04:20'))
            ->runInBackground()
            ->withoutOverlapping(600)
            ->appendOutputTo(storage_path('logs/tracking-facturas-migrar-pdf.log'))
            ->when(fn () => (bool) config('tracking_facturas.migracion_pdf.habilitada', false));
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
        // Se prende con el mismo tilde de Buenos Aires que el resto de los padrones.
        $horaArbaSync = (string) config('padrones_iibb.arba.sync_hora', '22:00');
        $periodoArbaSync = (string) config('padrones_iibb.arba.sync_periodo', 'siguiente');
        $schedule->command('padron-iibb-arba:sincronizar', [
            '--periodo' => $periodoArbaSync,
        ])
            ->lastDayOfMonth($horaArbaSync)
            ->runInBackground()
            ->withoutOverlapping(7200)
            ->appendOutputTo(storage_path('logs/padron-iibb-arba-sincronizar-schedule.log'))
            ->when(fn () => in_array(
                PercepcionIibbJurisdiccionEntregaSupport::JURISDICCION_BUENOS_AIRES,
                EmpresaJurisdiccionIibbSupport::jurisdiccionesAgente(),
                true
            ) && EmpresaJurisdiccionIibbSupport::publicaPadron(
                PercepcionIibbJurisdiccionEntregaSupport::JURISDICCION_BUENOS_AIRES
            ));

        // Salvo ARBA, los padrones se bajan a mano con clave fiscal. Se avisa antes
        // de que arranque el mes y en los primeros días, que es cuando todavía se
        // puede cargar sin haber facturado con la tasa de descarte.
        $schedule->command('padron-iibb:alertar-vencidos')
            ->cron('0 8 1,3,5,26,28 * *')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/padron-iibb-alertar-vencidos.log'))
            ->when(fn () => (bool) config('padrones_iibb.alertar_vencidos', true));

        // CABA y provincias: archivos pegados en la bandeja Samba. ARBA no entra acá.
        $cadaBandeja = max(5, min(60, (int) config('padrones_iibb.bandeja.cada_minutos', 15)));
        $schedule->command('padron-iibb:vigilar-bandeja')
            ->cron('*/' . $cadaBandeja . ' * * * *')
            ->runInBackground()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/padron-iibb-bandeja.log'))
            ->when(fn () => (bool) config('padrones_iibb.bandeja.habilitada', true));

        $schedule->command('bitacora-acceso:purge')
            ->dailyAt('03:20')
            ->withoutOverlapping(60)
            ->when(fn () => (bool) config('bitacora_acceso.habilitado', false));

        $schedule->command('cache-archivos:purge')
            ->dailyAt('04:10')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/cache-archivos-purge.log'));

        $schedule->command('pdf-temporarios:purge')
            ->dailyAt('04:25')
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/pdf-temporarios-purge.log'));

        // Deshabilitada por defecto: activar con AUDITS_PURGA_HABILITADA=true.
        $schedule->command('audits:purge')
            ->dailyAt('03:50')
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/audits-purge.log'))
            ->when(fn () => (bool) config('audits_purga.habilitada', false));

        $schedule->command('gastronomia:purge-anita-caches')
            ->dailyAt((string) config('gastronomia.anita_storage_cache_purge.hora', '03:40'))
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/purge-anita-caches-schedule.log'))
            ->when(fn () => (bool) config('gastronomia.anita_storage_cache_purge.habilitado', true));

        $schedule->command('gastronomia:purgar-snapshots-proceso')
            ->dailyAt((string) config('gastronomia.snapshot_proceso_purga.hora', '04:40'))
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/purgar-snapshots-proceso.log'))
            ->when(fn () => (bool) config('gastronomia.snapshot_proceso_purga.habilitado', true));

        // Costo de insumos diferido: la emisión graba costo 0 y acá se completa (liviano: 1 resolución por artículo).
        $schedule->command('gastronomia:completar-costo-insumos')
            ->hourlyAt(5)
            ->runInBackground()
            ->withoutOverlapping(50)
            ->appendOutputTo(storage_path('logs/gastronomia-costo-insumos-schedule.log'))
            ->when(fn () => (bool) config('gastronomia.insumos_costo_diferido.habilitado', false));

        $schedule->command('arca:solicitar-caea-quincenal')
            ->dailyAt('06:30')
            ->when(fn () => ! EntornoEmpresaSupport::esFerli()
                && config('arca.caea.pedido_automatico', true));

        $schedule->command('arca:presentar-caea-quincena')
            ->dailyAt(ParametroSistemaSupport::caeaPresentacionHora())
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/arca-caea-presentacion.log'))
            ->when(fn () => ! EntornoEmpresaSupport::esFerli()
                && (bool) config('arca.caea.presentacion_automatica', true));

        // arca:avisar-vencimiento-certificados → avisos:despachar-programados (arca.certificado_vencimiento)

        $schedule->command('facturacion:cargar-huecos-arca')
            ->dailyAt((string) config('arca.huecos_facturacion.hora', '07:10'))
            ->withoutOverlapping(90)
            ->appendOutputTo(storage_path('logs/facturacion-huecos-arca.log'))
            ->when(fn () => (bool) config('arca.huecos_facturacion.habilitado', false));

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

        $schedule->command('wigos:monitorear-servidor-activo')
            ->everyTwoMinutes()
            ->withoutOverlapping(4)
            ->appendOutputTo(storage_path('logs/wigos-monitor-servidor-activo.log'))
            ->when(fn () => filter_var(config('wigos.habilitado', false), FILTER_VALIDATE_BOOLEAN)
                && filter_var(config('wigos.monitor_servidor_activo.habilitado', true), FILTER_VALIDATE_BOOLEAN));

        // prestamo:recordatorios → avisos:despachar-programados (stock.prestamo_recordatorio)

        // Reemplazo firmante: vence_el = último día inclusive → restaura a las 00:05 del día siguiente.
        $schedule->command('configuracion:restaurar-reemplazos-firmante-vencidos')
            ->dailyAt((string) config('arbolaprobacion.reemplazo_firmante_vencidos.hora', '00:05'))
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/arbol-reemplazo-firmante-vencidos.log'));

        // Reemplazo destinatario de avisos: misma lógica de vence_el.
        $schedule->command('configuracion:restaurar-reemplazos-destinatario-aviso-vencidos')
            ->dailyAt((string) config('modulo_aviso.reemplazo_destinatario_vencidos.hora', '00:05'))
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/modulo-aviso-reemplazo-vencidos.log'));

        $schedule->command('compras:conciliar-cc-proveedor')
            ->dailyAt((string) config('compras.conciliacion_cc_proveedor.hora', '07:40'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/compras-cc-conciliacion-schedule.log'))
            ->when(fn () => (bool) config('compras.conciliacion_cc_proveedor.habilitada', true));

        $schedule->command('compras:auditar-deuda-ficha-proveedor')
            ->dailyAt((string) config('compras.deuda_ficha_auditor.hora', '07:50'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/compras-deuda-ficha-auditor-schedule.log'))
            ->when(fn () => (bool) config('compras.deuda_ficha_auditor.habilitada', true));

        $schedule->command('compras:auditar-factura-anticipada-recepcion')
            ->dailyAt((string) config('compras.factura_anticipada_recepcion_auditor.hora', '07:52'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/compras-factura-anticipada-recepcion-auditor-schedule.log'))
            ->when(fn () => (bool) config('compras.factura_anticipada_recepcion_auditor.habilitada', true));

        $schedule->command('ventas:auditar-deuda-ficha-cliente')
            ->dailyAt((string) config('ventas.deuda_ficha_auditor.hora', '07:55'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/ventas-deuda-ficha-auditor-schedule.log'))
            ->when(fn () => (bool) config('ventas.deuda_ficha_auditor.habilitada', true));

        // Crown: asientos Anita subdiario que duplican transferencia/venta ERP.
        $schedule->command('contable:auditar-asientos-subdiario-duplicados-transferencia')
            ->dailyAt((string) config('contable.asiento_subdiario_duplicado_auditor.hora', '08:05'))
            ->runInBackground()
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/contable-asiento-subdiario-dup-auditor-schedule.log'))
            ->when(fn () => EntornoEmpresaSupport::esCrown()
                && (bool) config('contable.asiento_subdiario_duplicado_auditor.habilitada', true));

        // Batch Compras → avisos:despachar-programados (ordencompra_alertas_abiertas,
        // entrega_dia_sin_recepcion, ordencompra_contrato_vencimiento,
        // suscripcion_comprobante_faltante, comprobante_proveedor_borrador_pendiente).

        $schedule->command('compras:recordatorio-legajo-gastronomia')
            ->dailyAt((string) config('compras.legajo.recordatorio_hora', '09:15'))
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/compras-legajo-recordatorio-schedule.log'))
            ->when(fn () => (bool) config('compras.legajo.recordatorio_habilitado', true));

        // arbolaprobacion.* / ai.* → avisos:despachar-programados (ModuloAvisoTipo).
        // $schedule->command('arbolaprobacion:recordatorios')->dailyAt(...);
        // $schedule->command('arbolaprobacion:digest-pendientes')->dailyAt(...);
        // $schedule->command('ai:digest-brief --ejecutar')->dailyAt(...);
        // $schedule->command('ai:alerta-umbral --ejecutar')->dailyAt(...);

        $schedule->command('anita-notificacion:purge')
            ->dailyAt((string) config('anita_notificacion.retencion.hora_purge', '03:45'))
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/anita-notificacion-purge-schedule.log'))
            ->when(fn () => (bool) config('anita_notificacion.retencion.habilitado', true));

        $schedule->command('ventas:avisar-contratos-venta-vencimiento')
            ->dailyAt((string) config('facturacion.contrato_venta_aviso.hora', '09:20'))
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/ventas-contrato-venta-aviso-schedule.log'))
            ->when(fn () => (bool) config('facturacion.contrato_venta_aviso.habilitado', true));

        // caja:avisar-cheques-aging → avisos:despachar-programados (caja.cheques_aging)

        // Interforming: PDF semanal auditoría notas CRM (todos los vendedores, últimos 7 días).
        $schedule->command('suitecrm:auditoria-notas-semanal')
            ->weeklyOn(
                (int) config('suitecrm.auditoria_semanal.dia', 5),
                (string) config('suitecrm.auditoria_semanal.hora', '08:00'),
            )
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/suitecrm-auditoria-notas-semanal.log'))
            ->when(fn () => EntornoEmpresaSupport::esInterforming()
                && (bool) config('suitecrm.habilitado', false)
                && (bool) config('suitecrm.auditoria_semanal.habilitada', true));

        // seguridad:recordatorio-tickets-ingreso → avisos:despachar-programados
        // (seguridad.ingreso_proveedor_recordatorio). Abono mensual queda en Kernel
        // (frecuencia día-del-mes no cubierta por programación Lun–Dom).

        $schedule->command('seguridad:alerta-abono-sin-ingresos')
            ->monthlyOn((int) config('ingreso_proveedor.abono_alerta_dia', 25), (string) config('ingreso_proveedor.abono_alerta_hora', '09:00'))
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/seguridad-alerta-abono-sin-ingresos.log'))
            ->when(fn () => (bool) config('ingreso_proveedor.abono_alerta_habilitado', true));

        $schedule->command('contable:verificar-saldos-cuenta-mes', ['--mail' => true])
            ->dailyAt((string) config('contable.saldos_cuenta_mes.integridad_diaria.hora', '06:10'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/contable-saldos-integridad-schedule.log'))
            ->when(fn () => (bool) config('contable.saldos_cuenta_mes.integridad_diaria.habilitada', true));

        // contable.reportes_definibles / sueldos.reportes_definibles → Programador (cada pasada).
        // $schedule->command('contable:distribuir-reportes-definibles')->hourly();
        // $schedule->command('sueldos:distribuir-reportes-definibles', ['--ejecutar' => true])->hourly();

        // Día y hora de cada aviso: Configuración → Avisos por módulo.
        $schedule->command('avisos:despachar-programados')
            ->everyFifteenMinutes()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/modulo-aviso-programados.log'))
            ->when(fn () => (bool) config('modulo_aviso.programados_habilitado', true));

        // Índice persistido de Mis aprobaciones (user_task).
        $schedule->command('user-task:sincronizar')
            ->everyTenMinutes()
            ->withoutOverlapping(8)
            ->appendOutputTo(storage_path('logs/user-task-sync.log'))
            ->when(fn () => (bool) config('arbolaprobacion.user_task_sync_habilitado', true));

        // SLA: escala user_task vencidas (notifica + marca).
        $schedule->command('user-task:escalar-vencidos')
            ->hourly()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/user-task-sla.log'))
            ->when(fn () => (bool) config('arbolaprobacion.user_task_sync_habilitado', true));

        $schedule->command('gastronomia:auditoria-anita-diaria')
            ->dailyAt((string) config('gastronomia.auditoria_anita_diaria.hora', '06:30'))
            ->runInBackground()
            ->withoutOverlapping(180)
            ->when(fn () => (bool) config('gastronomia.auditoria_anita_diaria.habilitada', true));

        // Los errores de talles se miran solo desde ayer; el histórico ya está relevado.
        $schedule->command('stock:control-ferli-ot', [
            '--desde' => Carbon::yesterday()->toDateString(),
            '--formato' => 'json',
        ])
            ->dailyAt('06:30')
            ->runInBackground()
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/control-ferli-ot.log'))
            ->when(fn () => EntornoEmpresaSupport::esFerli());

        $schedule->command('rendicion-gastronomia:auditoria-anita')
            ->dailyAt((string) config('rendicion_gastronomia_anita.auditoria_diaria.hora', '07:00'))
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/rendicion-gastronomia-auditoria-schedule.log'))
            ->when(fn () => ! EntornoEmpresaSupport::esFerli()
                && (bool) config('rendicion_gastronomia_anita.auditoria_diaria.habilitada', true));

        $ventanaAuditoriaCom = max(1, (int) config('recepcion_proveedor.auditoria_asientos_com_diaria.ventana_dias', 7));
        $schedule->command('recepcion-proveedor:auditoria-asientos-com', [
            '--desde' => Carbon::today()->subDays($ventanaAuditoriaCom - 1)->toDateString(),
            '--hasta' => Carbon::today()->toDateString(),
        ])
            ->dailyAt((string) config('recepcion_proveedor.auditoria_asientos_com_diaria.hora', '07:45'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->when(fn () => (bool) config('recepcion_proveedor.auditoria_asientos_com_diaria.habilitada', true));

        $schedule->command('recepcion-proveedor:auditar-costos')
            ->dailyAt((string) config('recepcion_proveedor.auditoria_costos_diaria.hora', '09:30'))
            ->runInBackground()
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/recepcion-proveedor-auditor-costos-schedule.log'))
            ->when(fn () => (bool) config('recepcion_proveedor.auditoria_costos_diaria.habilitada', true));

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

        $horasSyncBridge = config('comprobante_proveedor_anita.sync_bridge.horas', ['13:00', '16:00']);
        if (! is_array($horasSyncBridge) || $horasSyncBridge === []) {
            $horasSyncBridge = ['13:00', '16:00'];
        }
        foreach ($horasSyncBridge as $horaSyncBridge) {
            $schedule->command('comprobante-proveedor:sincronizar-bridge')
                ->dailyAt((string) $horaSyncBridge)
                ->runInBackground()
                ->withoutOverlapping(180)
                ->appendOutputTo(storage_path('logs/comprobante-proveedor-sincronizar-bridge-schedule.log'))
                ->when(fn () => ComprobanteProveedorAnitaBridgeGate::syncBridgeProgramadoHabilitado());
        }

        $ventanaAuditoriaOp = max(1, (int) config('pagoproveedor.auditoria_diaria.ventana_dias', 7));
        $schedule->command('pagoproveedor:auditoria-anita-diaria', [
            '--desde' => Carbon::today()->subDays($ventanaAuditoriaOp - 1)->toDateString(),
            '--hasta' => Carbon::today()->toDateString(),
        ])
            ->dailyAt((string) config('pagoproveedor.auditoria_diaria.hora', '08:45'))
            ->runInBackground()
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/pagoproveedor-anita-auditoria-schedule.log'))
            ->when(fn () => (bool) config('pagoproveedor.auditoria_diaria.habilitada', true));

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

        $ventanaImputacionAp = max(1, (int) config('comprobante_proveedor_anita.imputacion_ap_diaria.ventana_dias', 7));
        $schedule->command('comprobante-proveedor:auditar-imputacion-ap', [
            '--desde' => Carbon::today()->subDays($ventanaImputacionAp - 1)->toDateString(),
            '--hasta' => Carbon::today()->toDateString(),
        ])
            ->dailyAt((string) config('comprobante_proveedor_anita.imputacion_ap_diaria.hora', '08:40'))
            ->runInBackground()
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/comprobante-proveedor-imputacion-ap-schedule.log'))
            ->when(fn () => (bool) config('comprobante_proveedor_anita.imputacion_ap_diaria.habilitada', true));

        // Auditores Contaduría solo-ERP (mes calendario en curso vía default del comando).
        $schedule->command('comprobante-proveedor:auditar-far-diferencia')
            ->dailyAt((string) config('comprobante_proveedor_auditoria_erp.far_diferencia.hora', '08:55'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/comprobante-proveedor-auditor-far-diferencia-schedule.log'))
            ->when(fn () => (bool) config('comprobante_proveedor_auditoria_erp.far_diferencia.habilitada', true));

        $schedule->command('comprobante-proveedor:auditar-iva-tipo')
            ->dailyAt((string) config('comprobante_proveedor_auditoria_erp.iva_fuera_tipo.hora', '09:00'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/comprobante-proveedor-auditor-iva-tipo-schedule.log'))
            ->when(fn () => (bool) config('comprobante_proveedor_auditoria_erp.iva_fuera_tipo.habilitada', true));

        $schedule->command('comprobante-proveedor:auditar-anticipo-bsuso-capex')
            ->dailyAt((string) config('comprobante_proveedor_auditoria_erp.anticipo_bsuso_sin_capex.hora', '09:05'))
            ->runInBackground()
            ->withoutOverlapping(60)
            ->appendOutputTo(storage_path('logs/comprobante-proveedor-auditor-bsuso-capex-schedule.log'))
            ->when(fn () => (bool) config('comprobante_proveedor_auditoria_erp.anticipo_bsuso_sin_capex.habilitada', true));

        $ventanaImputacionOp = max(1, (int) config('pagoproveedor.imputacion_ap_diaria.ventana_dias', 7));
        $schedule->command('pagoproveedor:auditar-imputacion-ap', [
            '--desde' => Carbon::today()->subDays($ventanaImputacionOp - 1)->toDateString(),
            '--hasta' => Carbon::today()->toDateString(),
        ])
            ->dailyAt((string) config('pagoproveedor.imputacion_ap_diaria.hora', '08:50'))
            ->runInBackground()
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/pagoproveedor-imputacion-ap-schedule.log'))
            ->when(fn () => (bool) config('pagoproveedor.imputacion_ap_diaria.habilitada', true));

        $ventanaImputacionIe = max(1, (int) config('caja.ingresoegreso_imputacion_diaria.ventana_dias', 7));
        $schedule->command('ingresoegreso:auditar-imputacion', [
            '--desde' => Carbon::today()->subDays($ventanaImputacionIe - 1)->toDateString(),
            '--hasta' => Carbon::today()->toDateString(),
        ])
            ->dailyAt((string) config('caja.ingresoegreso_imputacion_diaria.hora', '08:55'))
            ->runInBackground()
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/ingresoegreso-imputacion-schedule.log'))
            ->when(fn () => (bool) config('caja.ingresoegreso_imputacion_diaria.habilitada', true));

        $schedule->command('rendicion-estacionamiento:auditoria-anita')
            ->dailyAt((string) config('rendicion_estacionamiento_anita.auditoria_diaria.hora', '07:30'))
            ->runInBackground()
            ->withoutOverlapping(120)
            ->when(fn () => ! EntornoEmpresaSupport::esFerli()
                && (bool) config('rendicion_estacionamiento_anita.auditoria_diaria.habilitada', false));

        $schedule->command('gastronomia:conciliacion-diaria-reporte', [
            '--fecha-desde' => Carbon::yesterday()->toDateString(),
            '--fecha-hasta' => Carbon::yesterday()->toDateString(),
            '--enviar-mail',
            '--requiere-jornada-cerrada',
        ])
            ->dailyAt((string) config('gastronomia.conciliacion_diaria_reporte.hora', '09:30'))
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/conciliacion-diaria-schedule.log'))
            ->when(fn () => (bool) config('gastronomia.conciliacion_diaria_reporte.habilitada', true));

        // Auto-sanado del Informe Z: regenera solo si el recomputo = venta Waitry del cierre.
        // No usa el asiento (todavía no está a las 07:45). No pisa a $0 si hay venta Waitry/ERP.
        $diasAtrasZ = max(1, (int) config('gastronomia.regenerar_z_desde_proceso.dias_atras', 2));
        $schedule->command('gastronomia:regenerar-z-desde-proceso', [
            '--fecha-desde' => Carbon::today()->subDays($diasAtrasZ)->toDateString(),
            '--fecha-hasta' => Carbon::yesterday()->toDateString(),
            '--aplicar',
        ])
            ->dailyAt((string) config('gastronomia.regenerar_z_desde_proceso.hora', '07:45'))
            ->withoutOverlapping(120)
            ->appendOutputTo(storage_path('logs/regenerar-z-desde-proceso-schedule.log'))
            ->when(fn () => ! EntornoEmpresaSupport::esFerli()
                && (bool) config('gastronomia.regenerar_z_desde_proceso.habilitado', true));

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

        foreach (CierreJornadaProcesoAutomaticoSupport::horariosPorEmpresa() as $empresaId => $horaCierre) {
            $schedule->command('gastronomia:cierre-jornada-waitry-automatico', [
                '--enviar-mail',
                '--empresa' => $empresaId,
            ])
                ->dailyAt($horaCierre)
                ->name('gastronomia-cierre-jornada-waitry-automatico-'.$empresaId)
                ->runInBackground()
                ->withoutOverlapping(240)
                ->appendOutputTo(storage_path('logs/cierre-jornada-waitry-automatico-schedule.log'))
                ->when(fn () => ! EntornoEmpresaSupport::esFerli()
                    && ! EntornoEmpresaSupport::esCrown()
                    && (bool) config('gastronomia.cierre_jornada_automatico.habilitado', false));
        }

        $schedule->command('waitry:reintentar-sync-status-pos', ['--limite' => 50])
            ->everyMinute()
            ->runInBackground()
            ->withoutOverlapping(5)
            ->appendOutputTo(storage_path('logs/waitry-sync-status-pos-reintento.log'))
            ->when(fn () => (bool) config('waitry.habilitado', false));

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

        $cadaReintentoCorreos = max(5, (int) config('queue.reintento_correos.cada_minutos', 10));
        $schedule->command('queue:reintentar-correos-fallidos')
            ->cron('*/'.$cadaReintentoCorreos.' * * * *')
            ->withoutOverlapping(8)
            ->appendOutputTo(storage_path('logs/queue-reintento-correos.log'))
            ->when(fn () => (bool) config('queue.reintento_correos.habilitado', true));

        $schedule->command('gastronomia:actualizar-costo-mensual-catalogo')
            ->dailyAt((string) config('gastronomia.costo_mensual_catalogo.hora', '07:00'))
            ->runInBackground()
            ->withoutOverlapping(240)
            ->appendOutputTo(storage_path('logs/costo-mensual-catalogo-schedule.log'))
            ->when(fn () => ! EntornoEmpresaSupport::esFerli()
                && (bool) config('gastronomia.costo_mensual_catalogo.habilitado', true));

        // Cierre de mes: última pasada del costo catálogo (lista base+mes) tras la jornada.
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
            if ($horaCuotaSp === '' || (EntornoEmpresaSupport::esFerli() && $horaCuotaSp === '08:00')) {
                continue;
            }
            $schedule->command('solicitudpago:generar-cuotas')
                ->dailyAt($horaCuotaSp)
                ->runInBackground()
                ->withoutOverlapping(60)
                ->appendOutputTo(storage_path('logs/solicitudpago-generar-cuotas-schedule.log'))
                ->when(fn () => (bool) config('solicitudpago.generar_cuotas.habilitado', false));
        }

        // flash.* / flash_crown.* distribución → Programador (cada pasada, gate AGG/Crown).
        // $schedule->command('flash:distribuir-reportes')->everyFifteenMinutes();
        // $schedule->command('flash-crown:distribuir-reportes')->everyFifteenMinutes();

        // El Bierzo: Anita → ERP pedidos del día (todos los repartos).
        $schedule->command('ventas:importar-pedido-anita --ejecutar')
            ->dailyAt((string) config('pedido.importar_anita_diaria.hora', '01:00'))
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/pedido-importar-anita-diaria.log'))
            ->when(fn () => EntornoEmpresaSupport::esElBierzo()
                && (bool) config('pedido.importar_anita_diaria.habilitado', true));

        // El Bierzo: cada 30 min, pedidos nuevos + pesada si el ERP aún no la tiene (no pisa cabecera/reparto).
        $schedule->command('ventas:importar-pedido-anita --ejecutar --solo-nuevos')
            ->everyThirtyMinutes()
            ->between(
                (string) config('pedido.importar_anita_diaria.refresco_desde', '05:00'),
                (string) config('pedido.importar_anita_diaria.refresco_hasta', '18:00')
            )
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/pedido-importar-anita-diaria.log'))
            ->when(fn () => EntornoEmpresaSupport::esElBierzo()
                && (bool) config('pedido.importar_anita_diaria.habilitado', true)
                && (bool) config('pedido.importar_anita_diaria.refresco_habilitado', true));

        $limiteRegrabarAnitaPedido = max(1, (int) config('facturacion.ANITA_PEDIDO_REGRABAR_LIMITE', 20));
        $schedule->command('ventas:regrabar-anita-pedido --ejecutar --limite='.$limiteRegrabarAnitaPedido)
            ->everyTenMinutes()
            ->runInBackground()
            ->withoutOverlapping(15)
            ->appendOutputTo(storage_path('logs/pedido-anita-regrabar.log'))
            ->when(fn () => EntornoEmpresaSupport::esElBierzo()
                && (bool) config('facturacion.ANITA_TRAS_RESPUESTA_PEDIDO', true)
                && (bool) config('facturacion.ANITA_PEDIDO_REGRABAR_HABILITADO', true));

        // Flash AGG 14:30 de la jornada de ayer (cerrada). Omite empresa si un usuario ya la cargó.
        $schedule->command('flash:calcular-diario')
            ->dailyAt((string) config('caja.flash_calculo_diario.hora', '14:30'))
            ->runInBackground()
            ->withoutOverlapping(45)
            ->appendOutputTo(storage_path('logs/flash-calculo-diario-schedule.log'))
            ->when(fn () => EntornoEmpresaSupport::esAgg()
                && (bool) config('caja.flash_calculo_diario.habilitado', false));

        // Flash Crown: snapshot online de ayer (no pisa días validados).
        $schedule->command('flash-crown:calcular-diario')
            ->dailyAt((string) config('caja.flash_crown_calculo_diario.hora', '14:30'))
            ->runInBackground()
            ->withoutOverlapping(45)
            ->appendOutputTo(storage_path('logs/flash-crown-calculo-diario-schedule.log'))
            ->when(fn () => EntornoEmpresaSupport::esCrown()
                && (bool) config('caja.flash_crown_calculo_diario.habilitado', true));

        // tesoreria_crown.avisos → Programador (solo Crown).
        // $schedule->command('tesoreria-crown:avisos --ejecutar --anita --mail')->dailyAt(...);

        $schedule->command('ventas:reenviar-impresion-pendiente --ejecutar')
            ->dailyAt((string) config('impresion_comprobante.cron_hora', '06:20'))
            ->runInBackground()
            ->withoutOverlapping(20)
            ->appendOutputTo(storage_path('logs/impresion-comprobante-reintento.log'));

        // Ferli: Tiendanube — health del token (no se renueva solo) + sync pedidos.
        $schedule->command('tiendanube:verificar-api --forzar')
            ->hourly()
            ->runInBackground()
            ->withoutOverlapping(10)
            ->appendOutputTo(storage_path('logs/tiendanube-health.log'))
            ->when(fn () => EntornoEmpresaSupport::esFerli()
                && (bool) config('tiendanube.health_cron_habilitado', true));

        // Ferli, solo Caballito: TXT del shopping por correo. La hora sale de Parámetros Facturación Local.
        $schedule->command('facturacion-local:exportar-shopping-caballito')
            ->dailyAt(FacturacionLocalShoppingCaballitoSupport::hora())
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/facturacion-local-shopping-caballito.log'))
            ->when(fn () => EntornoEmpresaSupport::esFerli()
                && FacturacionLocalShoppingCaballitoSupport::habilitado());

        $schedule->command('tiendanube:sincronizar-pedidos')
            ->everyTwoHours()
            ->between('07:00', '23:00')
            ->runInBackground()
            ->withoutOverlapping(30)
            ->appendOutputTo(storage_path('logs/tiendanube-sync.log'))
            ->when(fn () => EntornoEmpresaSupport::esFerli()
                && (bool) config('tiendanube.sync_cron_habilitado', true));

        // Una vez por día hábil, desde la hora configurada. Si ese minuto se pierde, reintenta
        // el resto del día. Sábados, domingos y feriados no disparan.
        $schedule->command('tiendanube:subir-stock')
            ->everyMinute()
            ->runInBackground()
            ->withoutOverlapping(180)
            ->appendOutputTo(storage_path('logs/tiendanube-stock-subida.log'))
            ->when(fn () => TiendanubeStockCatalogoSupport::debeDispararCron());

        // Certificado HTTPS: aviso si vence, si Certbot falló o si se renovó.
        $schedule->command('https:avisar-certificado')
            ->dailyAt('08:15')
            ->withoutOverlapping(15)
            ->when(fn () => EntornoEmpresaSupport::esElBierzo()
                && (bool) config('https_certificado.aviso_habilitado', false));
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
