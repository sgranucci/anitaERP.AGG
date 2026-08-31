<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TotemWaitryGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\Waitry\WaitryAnalyticsOrdenesService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\Waitry\WaitryCierreJornadaDiscrepanciaSupport;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use App\Support\Ventas\Waitry\WaitryCierreJornadaVentanaSupport;
use App\Support\Ventas\Waitry\WaitryInformeZConciliacionSupport;
use App\Support\Ventas\Waitry\WaitryInformeZTransmisionFaltanteSupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use App\Support\Ventas\Waitry\WaitryOrdenCobroSupport;
use App\Support\Ventas\Waitry\WaitryOrdenEstadoSupport;
use App\Support\Ventas\Waitry\WaitryOrdenPaymentEnriquecimientoSupport;
use App\Support\Ventas\Waitry\WaitryPaymentGatewaySupport;
use App\Support\Ventas\Waitry\WaitryTableAccesoSupport;
use App\Support\Ventas\Waitry\WaitryTotemJornadaResumenSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Cierre de jornada — órdenes Waitry del tótem (waitry_order_id).
 * Persiste el rango de IDs incluido; el día siguiente consulta órdenes con id &gt; último hasta guardado.
 * Huecos de secuencia (IDs faltantes en getordersdetails) no se recuperan por API: quedan como discrepancia
 * para auditoría del día (proceso posterior en caja).
 */
final class GastronomiaCierreTotemJornadaService
{
    /**
     * Salto de order_id (entre la última orden por fecha/hora y el mayor order_id de la jornada) a
     * partir del cual se considera que Waitry cambió/reinició el numerador. Un día normal el span es
     * de ~15.000; un salto a la serie 1.000.000.000 lo dispara. Sirve de monitor (log de aviso).
     */
    private const GAP_NUMERADOR_ALERTA = 1_000_000;

    public function __construct(
        private readonly WaitryAnalyticsOrdenesService $analyticsOrdenesService,
        private readonly WaitryOrdenesExternasService $ordenesExternasService,
    ) {
    }

    public function habilitado(): bool
    {
        return (bool) config('gastronomia.cierre_totem_jornada_habilitado', true)
            && (bool) config('waitry.habilitado', false);
    }

    /**
     * Último waitry_order_id incluido en un cierre de jornada de la empresa (0 si nunca hubo cierre).
     */
    public function ultimoWaitryOrderIdHasta(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        $hasta = CierreTotemJornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('waitry_order_id_hasta')
            ->orderByDesc('waitry_order_id_hasta')
            ->orderByDesc('id')
            ->value('waitry_order_id_hasta');

        return max(0, (int) $hasta);
    }

    /**
     * Tope inferior (exclusivo) de order_id Waitry al consultar el tramo de una jornada.
     *
     * - Jornada con cierre tótem guardado: {@see CierreTotemJornadaGastronomia::$waitry_order_id_anterior}
     *   (no usar {@see ultimoWaitryOrderIdHasta()} si esa jornada es la última cerrada: su propio hasta
     *   descartaría todo el tramo al reauditar en Caja).
     * - Jornada abierta o sin cierre tótem: último hasta de otras jornadas de la empresa.
     */
    public function waitryOrderIdAnteriorParaJornada(JornadaGastronomia $jornada): int
    {
        $cierre = CierreTotemJornadaGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->first();

        if ($cierre !== null) {
            return max(0, (int) $cierre->waitry_order_id_anterior);
        }

        $empresaId = (int) $jornada->empresa_id;
        if ($empresaId <= 0) {
            return 0;
        }

        $hasta = CierreTotemJornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('jornada_gastronomia_id', '!=', (int) $jornada->id)
            ->whereNotNull('waitry_order_id_hasta')
            ->orderByDesc('waitry_order_id_hasta')
            ->orderByDesc('id')
            ->value('waitry_order_id_hasta');

        return max(0, (int) $hasta);
    }

    /** @deprecated Use ultimoWaitryOrderIdHasta() */
    public function ultimoTicketMovimientoIdHasta(int $empresaId): int
    {
        return $this->ultimoWaitryOrderIdHasta($empresaId);
    }

    /**
     * Órdenes Waitry del tramo de jornada (último cierre → cierre indicado) con estado ERP.
     * Usado por el proceso de cierre de jornada (conciliación, redistribución, asientos).
     *
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   meta: array<string, mixed>
     * }
     */
    public function movimientosParaJornada(JornadaGastronomia $jornada, ?Carbon $cierreHasta = null): array
    {
        $this->prepararEntornoConsultaCierreTotem();

        if (! $this->habilitado()) {
            throw new InvalidArgumentException('Cierre tótem Waitry no habilitado.');
        }

        $empresaId = (int) $jornada->empresa_id;
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa inválida.');
        }

        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $idAnterior = $this->waitryOrderIdAnteriorParaJornada($jornada);
        $hasta = $cierreHasta ?? WaitryCierreJornadaVentanaSupport::resolverCierreHasta($jornada);

        $listado = $this->listarOrdenesWaitryNuevas(
            $empresaId,
            $fechaJornada,
            $idAnterior,
            $jornada->apertura_en,
            $hasta,
        );

        $ventana = $listado['ventana'];
        $filtroOrdenes = WaitryOrdenEstadoSupport::filtrarOrdenesActivas($listado['ordenes']);
        $ordenesActivas = $filtroOrdenes['activas'];

        $lineasCompletas = $this->armarLineasConEstadoErp(
            $empresaId,
            $ordenesActivas,
            $ventana['desde'] ?? null,
            $ventana['hasta'] ?? null,
        );
        $lineasCompletas = array_merge(
            $lineasCompletas,
            $this->armarLineasHuecosPendientesAuditoria($listado['auditoria']['ids_huecos_secuencia'] ?? []),
        );

        $ids = array_keys($ordenesActivas);
        sort($ids, SORT_NUMERIC);
        $desde = $ids !== [] ? (int) min($ids) : null;
        $hastaId = $ids !== [] ? (int) max($ids) : $idAnterior;

        $split = WaitryOrdenEstadoSupport::separarCanceladas($lineasCompletas);

        return [
            'lineas' => $split['activas'],
            'meta' => [
                'jornada_id' => (int) $jornada->id,
                'empresa_id' => $empresaId,
                'fecha_jornada' => $jornada->fecha_jornada?->format('Y-m-d') ?? $fechaJornada,
                'fecha_jornada_fmt' => $jornada->fecha_jornada?->format('d/m/Y') ?? '',
                'ventana_operativa' => $ventana['etiqueta'] ?? '',
                'rango_calendario_waitry' => $listado['auditoria']['consulta_waitry_rango'] ?? '',
                'waitry_order_id_anterior' => $idAnterior,
                'waitry_order_id_desde' => $desde,
                'waitry_order_id_hasta' => $hastaId,
                'rango_etiqueta' => $this->etiquetaRango($idAnterior, $desde, $hastaId),
                'cantidad_movimientos' => count($split['activas']),
                'cantidad_canceladas_excluidas' => $filtroOrdenes['cantidad_excluidas'],
                'cantidad_anuladas_descuento_excluidas' => (int) ($filtroOrdenes['cantidad_anuladas_descuento_excluidas'] ?? 0)
                    + count($split['anuladas_descuento'] ?? []),
                'waitry_canceladas' => $split['resumen'],
                'waitry_anuladas_descuento' => self::fusionarResumenExcluidas(
                    $filtroOrdenes['waitry_anuladas_descuento'] ?? ['cantidad' => 0, 'total' => 0.0],
                    $split['resumen_anuladas_descuento'] ?? ['cantidad' => 0, 'total' => 0.0],
                ),
                'auditoria' => $listado['auditoria'],
            ],
        ];
    }

    /**
     * Tramo Waitry para el proceso de Caja (auditoría / facturación).
     *
     * - Jornada abierta (sin cierre gastronomía): corte temporal = ahora; último ticket = el mayor ID leído en esa consulta.
     * - Jornada cerrada: ventana hasta {@see JornadaGastronomia::$cierre_en}; si existe registro de cierre tótem de esa jornada,
     *   el tope de ID es {@see CierreTotemJornadaGastronomia::$waitry_order_id_hasta} (mismo que al cerrar en Ventas).
     *
     * @return array{
     *   lineas: list<array<string, mixed>>,
     *   meta: array<string, mixed>
     * }
     */
    public function movimientosParaProcesoCaja(JornadaGastronomia $jornada): array
    {
        $cerrada = (string) ($jornada->estado ?? '') === JornadaGastronomia::ESTADO_CERRADA
            && $jornada->cierre_en !== null;

        if (! $cerrada) {
            $cargado = $this->movimientosParaJornada(
                $jornada,
                WaitryCierreJornadaVentanaSupport::resolverCierreHasta($jornada),
            );
            $cargado['meta']['tramo_modo'] = 'auditoria_en_curso';
            $cargado['meta']['tramo_ultimo_ticket_origen'] = 'ultimo_leido';
            $cargado['meta']['tramo_cierre_hasta'] = now()->toIso8601String();

            return $cargado;
        }

        $cargado = $this->movimientosParaJornada($jornada, $jornada->cierre_en);
        $cierreTotem = CierreTotemJornadaGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->first();

        if ($cierreTotem !== null) {
            return $this->aplicarTopeUltimoTicketCierreGastronomia($cargado, $cierreTotem);
        }

        $cargado['meta']['tramo_modo'] = 'definitivo_sin_registro_totem';
        $cargado['meta']['tramo_ultimo_ticket_origen'] = 'ultimo_leido_cierre_jornada';
        $cargado['meta']['tramo_cierre_hasta'] = $jornada->cierre_en->toIso8601String();

        return $cargado;
    }

    /**
     * @param  array{lineas: list<array<string, mixed>>, meta: array<string, mixed>}  $cargado
     * @return array{lineas: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function aplicarTopeUltimoTicketCierreGastronomia(array $cargado, CierreTotemJornadaGastronomia $cierre): array
    {
        $hasta = (int) $cierre->waitry_order_id_hasta;
        $anterior = (int) $cierre->waitry_order_id_anterior;
        $desdeRegistro = $cierre->waitry_order_id_desde !== null ? (int) $cierre->waitry_order_id_desde : null;

        $instanteTope = null;
        foreach ($cargado['lineas'] as $l) {
            if ($this->waitryOrderIdDeLinea($l) === $hasta) {
                $instanteTope = $this->parsearInstanteOrden($l);

                break;
            }
        }

        // Self-heal del tope: las líneas ya vienen acotadas a la ventana [apertura, cierre_en] de la
        // jornada. Si el watermark persistido (waitry_order_id_hasta) quedó por debajo del último
        // ticket real —preview anterior al último ticket o salto de numeración Waitry (serie
        // 1.000.000.000)— su instante corta comandas legítimas de la jornada. Extender el tope al
        // último ticket por fecha/hora dentro de la ventana para no perderlas en el proceso/asientos.
        $topeFecha = $this->topeUltimoTicketPorFechaEnLineas($cargado['lineas']);
        if ($topeFecha['instante'] !== null
            && ($instanteTope === null || $topeFecha['instante']->greaterThan($instanteTope))) {
            $instanteTope = $topeFecha['instante'];
            if ($topeFecha['id'] > 0) {
                $hasta = max($hasta, $topeFecha['id']);
            }
        }

        $this->persistirWatermarkSiJornadaCerrada($cierre, $hasta);

        $lineas = array_values(array_filter(
            $cargado['lineas'],
            function (array $l) use ($hasta, $instanteTope) {
                $id = $this->waitryOrderIdDeLinea($l);
                if ($id <= $hasta) {
                    return true;
                }
                if ($instanteTope !== null) {
                    $instante = $this->parsearInstanteOrden($l);

                    return $instante !== null && $instante->lessThanOrEqualTo($instanteTope);
                }

                return false;
            },
        ));

        $ids = [];
        foreach ($lineas as $linea) {
            $id = $this->waitryOrderIdDeLinea($linea);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        sort($ids, SORT_NUMERIC);
        $desde = $ids !== [] ? (int) min($ids) : $desdeRegistro;

        $meta = $cargado['meta'];
        $meta['waitry_order_id_hasta'] = $hasta;
        $meta['waitry_order_id_anterior'] = $anterior;
        $meta['waitry_order_id_desde'] = $desde;
        $meta['rango_etiqueta'] = $this->etiquetaRango($anterior, $desde, $hasta);
        $meta['cantidad_movimientos'] = count($lineas);
        $meta['tramo_modo'] = 'definitivo_cierre_gastronomia';
        $meta['tramo_ultimo_ticket_origen'] = 'cierre_gastronomia';
        $meta['cierre_totem_jornada_id'] = (int) $cierre->id;

        return [
            'lineas' => $lineas,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private function waitryOrderIdDeLinea(array $linea): int
    {
        return max(0, (int) ($linea['waitry_order_id'] ?? $linea['order_id'] ?? 0));
    }

    /**
     * Último ticket por fecha/hora (placed_at) entre líneas ya acotadas a la ventana de la jornada.
     * Igual criterio que {@see self::topeUltimaOrdenPorFecha()} pero sobre líneas ERP (no órdenes API):
     * el watermark se elige por fecha/hora y no por mayor order_id, para no confundir la serie anómala
     * de Waitry (1.000.000.000) con el último ticket real de la jornada.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return array{id: int, instante: ?Carbon}
     */
    private function topeUltimoTicketPorFechaEnLineas(array $lineas): array
    {
        $idPorFecha = 0;
        $instanteTope = null;
        foreach ($lineas as $l) {
            if (! is_array($l)) {
                continue;
            }
            $instante = $this->parsearInstanteOrden($l);
            if ($instante === null) {
                continue;
            }
            $id = $this->waitryOrderIdDeLinea($l);
            if ($instanteTope === null
                || $instante->greaterThan($instanteTope)
                || ($instante->equalTo($instanteTope) && $id > $idPorFecha)
            ) {
                $instanteTope = $instante;
                $idPorFecha = $id;
            }
        }

        return ['id' => $idPorFecha, 'instante' => $instanteTope];
    }

    /**
     * Vista previa Informe Z / totales tótem (consulta Waitry). Los cálculos van aquí, no al grabar el cierre.
     *
     * @return array<string, mixed>|null
     */
    public function previewParaJornadaAbierta(JornadaGastronomia $jornada): ?array
    {
        if (! $this->habilitado()) {
            return null;
        }

        $consulta = $this->consultarTramoInformeZ($jornada);
        $empresaId = (int) $jornada->empresa_id;
        $resumenTotems = $consulta['resumen_informe_z'];
        $totalGeneral = $resumenTotems['total_general'] ?? [
            'cantidad_ordenes' => 0,
            'total_ingreso' => 0.0,
            'por_medio_pago' => [],
        ];

        $plantilla = WaitryInformeZConciliacionSupport::plantillaCarga($empresaId, $resumenTotems);
        $plantilla = WaitryInformeZConciliacionSupport::precargarMontosInformeZDesdeSistema($plantilla);
        $conciliacion = $plantilla !== []
            ? WaitryInformeZConciliacionSupport::conciliar($plantilla)
            : null;

        return [
            'jornada_id' => (int) $jornada->id,
            'fecha_jornada' => $jornada->fecha_jornada?->format('d/m/Y') ?? '',
            'ventana_operativa' => $consulta['ventana_operativa'],
            'consulta_waitry_rango' => $consulta['auditoria']['consulta_waitry_rango'] ?? '',
            'waitry_order_id_anterior' => $consulta['waitry_order_id_anterior'],
            'waitry_order_id_desde' => $consulta['waitry_order_id_desde'],
            'waitry_order_id_hasta' => $consulta['waitry_order_id_hasta'],
            'rango_etiqueta' => $consulta['rango_etiqueta'],
            'tramo_ultimo_ticket_origen' => $consulta['tramo_ultimo_ticket_origen'],
            'tramo_modo' => $consulta['tramo_modo'],
            'cantidad_ordenes' => (int) ($consulta['cantidad_ordenes_informe_z'] ?? 0),
            'cantidad_canceladas_excluidas' => (int) ($consulta['cantidad_canceladas_excluidas'] ?? 0),
            'cantidad_anuladas_descuento_excluidas' => (int) ($consulta['cantidad_anuladas_descuento_excluidas'] ?? 0),
            'waitry_anuladas_descuento' => is_array($consulta['waitry_anuladas_descuento'] ?? null)
                ? $consulta['waitry_anuladas_descuento']
                : ['cantidad' => 0, 'total' => 0.0],
            'por_totem' => $resumenTotems['por_totem'] ?? [],
            'resumen_informe_z' => $consulta['resumen_informe_z'] ?? $resumenTotems,
            'total_general' => $totalGeneral,
            'total_ingreso_totem' => (float) ($totalGeneral['total_ingreso'] ?? 0),
            'total_informe_z_sistema' => (float) (($consulta['resumen_informe_z']['total_general']['total_ingreso'] ?? 0)),
            'cantidad_ingreso_totem' => (int) ($totalGeneral['cantidad_ordenes'] ?? 0),
            'totems' => $plantilla,
            'conciliacion' => $conciliacion,
            'informe_z_precarga_automatica' => true,
            'informe_z_cargado' => $plantilla !== [],
            'informe_z_en' => null,
            'usuario_informe_z' => null,
            'tolerancia' => WaitryInformeZConciliacionSupport::toleranciaMonto(),
            'preview_en' => now()->format('d/m/Y H:i'),
            'snapshot_cierre' => $consulta['snapshot_cierre'],
            'diagnostico_waitry' => $consulta['diagnostico_waitry'] ?? null,
        ];
    }

    /**
     * Tramo Waitry + resúmenes para Informe Z (misma regla que al cerrar / vista previa).
     *
     * @return array<string, mixed> Salida de {@see consultarTramoInformeZ()}
     */
    public function datosTramoInformeZ(JornadaGastronomia $jornada): array
    {
        return $this->consultarTramoInformeZ($jornada);
    }

    /**
     * Consulta Waitry y arma resumen por tótem / total general por medio (Informe Z).
     *
     * Tramo: order_id &gt; tope anterior de la jornada, placed_at en [apertura, cierre jornada],
     * order_id &lt;= último ticket del cierre ({@see CierreTotemJornadaGastronomia::$waitry_order_id_hasta} si ya cerró).
     * Informe Z sistema: cobros Waitry QR/MP/Posnet no cobrados en caja Anita real (+ facturadas con cuenta TOTEM).
     *
     * @return array{
     *   resumen_totems: array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>,tramo?:array<string,mixed>},
     *   snapshot_cierre: array<string, mixed>,
     *   auditoria: array<string, mixed>,
     *   ventana_operativa: string,
     *   waitry_order_id_anterior: int,
     *   waitry_order_id_desde: ?int,
     *   waitry_order_id_hasta: int,
     *   rango_etiqueta: string,
     *   tramo_ultimo_ticket_origen: string,
     *   tramo_modo: string,
     *   cantidad_ordenes_listado: int,
     *   cantidad_discrepancias: int
     * }
     */
    private function consultarTramoInformeZ(JornadaGastronomia $jornada): array
    {
        $this->prepararEntornoConsultaCierreTotem();

        $empresaId = (int) $jornada->empresa_id;
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa inválida.');
        }

        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $idAnterior = $this->waitryOrderIdAnteriorParaJornada($jornada);
        $cierreHasta = WaitryCierreJornadaVentanaSupport::resolverCierreHasta($jornada);

        $listado = $this->listarOrdenesWaitryNuevas(
            $empresaId,
            $fechaJornada,
            $idAnterior,
            $jornada->apertura_en,
            $cierreHasta,
        );

        $ventana = $listado['ventana'];
        $filtroOrdenes = WaitryOrdenEstadoSupport::filtrarOrdenesActivas($listado['ordenes']);
        $ordenesParaInformeZ = $filtroOrdenes['activas'];
        $tope = $this->resolverTopeConInstante($jornada, $ordenesParaInformeZ);
        $topeHastaId = $tope['id'];
        $ordenesParaInformeZ = $this->filtrarOrdenesActivasPorTopeHasta(
            $ordenesParaInformeZ,
            $topeHastaId,
            $tope['instante'],
        );
        $cantidadCanceladasExcluidas = $filtroOrdenes['cantidad_excluidas'];

        $lineasCompletas = $this->armarLineasConEstadoErp(
            $empresaId,
            $ordenesParaInformeZ,
            $ventana['desde'] ?? null,
            $ventana['hasta'] ?? null,
        );
        $split = WaitryOrdenEstadoSupport::separarCanceladas($lineasCompletas);
        $cantidadAnuladasDescuentoExcluidas = (int) ($filtroOrdenes['cantidad_anuladas_descuento_excluidas'] ?? 0)
            + count($split['anuladas_descuento'] ?? []);
        $lineasActivas = $split['activas'];

        $totems = TotemWaitryGastronomia::query()
            ->with('ubicacion')
            ->where('empresa_id', $empresaId)
            ->orderBy('ubicacion_id')
            ->get();
        $resumenTotems = WaitryTotemJornadaResumenSupport::armar($totems, $lineasActivas, $empresaId);
        $totemsInformeZ = $totems->filter(static fn (TotemWaitryGastronomia $t) => $t->participaInformeZ());
        $resumenInformeZ = WaitryTotemJornadaResumenSupport::armarParaInformeZ($totemsInformeZ, $lineasActivas, $empresaId);

        $ids = array_keys($ordenesParaInformeZ);
        sort($ids, SORT_NUMERIC);
        $desde = $ids !== [] ? (int) min($ids) : null;
        $hasta = $topeHastaId > 0 ? $topeHastaId : ($ids !== [] ? (int) max($ids) : $idAnterior);
        $origenTopeHasta = $this->origenTopeWaitryOrderIdHasta($jornada);

        $resumenTotems = $this->resumenTotemsConMetadatosTramo(
            $resumenTotems,
            $idAnterior,
            $desde,
            $hasta,
            $origenTopeHasta,
            'auditoria_en_curso',
            $cierreHasta->toIso8601String(),
        );
        $resumenInformeZ = $this->resumenTotemsConMetadatosTramo(
            $resumenInformeZ,
            $idAnterior,
            $desde,
            $hasta,
            $origenTopeHasta,
            'auditoria_en_curso',
            $cierreHasta->toIso8601String(),
        );

        $auditoriaInformeZ = [
            'consulta_waitry_rango' => $listado['auditoria']['consulta_waitry_rango'] ?? '',
            'cantidad_canceladas_excluidas' => $cantidadCanceladasExcluidas,
            'cantidad_anuladas_descuento_excluidas' => $cantidadAnuladasDescuentoExcluidas,
            'waitry_anuladas_descuento' => self::fusionarResumenExcluidas(
                $filtroOrdenes['waitry_anuladas_descuento'] ?? ['cantidad' => 0, 'total' => 0.0],
                $split['resumen_anuladas_descuento'] ?? ['cantidad' => 0, 'total' => 0.0],
            ),
        ];

        $snapshotCierre = $this->armarSnapshotCierre(
            $idAnterior,
            $desde,
            $hasta,
            $resumenTotems,
            $auditoriaInformeZ,
            (string) ($ventana['etiqueta'] ?? ''),
            $resumenInformeZ,
        );

        $lineasConIngreso = array_values(array_filter(
            $lineasActivas,
            static fn (array $ln) => WaitryTotemJornadaResumenSupport::lineaEntraInformeZSistema($ln),
        ));

        $ordenesInformeZ = WaitryInformeZTransmisionFaltanteSupport::compactarOrdenesDesdeLineas(
            $lineasConIngreso,
            $empresaId,
        );
        $snapshotCierre[WaitryInformeZTransmisionFaltanteSupport::CLAVE_ORDENES_SNAPSHOT] = $ordenesInformeZ;

        return [
            'resumen_totems' => $resumenTotems,
            'resumen_informe_z' => $resumenInformeZ,
            'snapshot_cierre' => $snapshotCierre,
            'lineas_informe_z' => $lineasConIngreso,
            'ventana_operativa' => (string) ($ventana['etiqueta'] ?? ''),
            'waitry_order_id_anterior' => $idAnterior,
            'waitry_order_id_desde' => $desde,
            'waitry_order_id_hasta' => $hasta,
            'rango_etiqueta' => $this->etiquetaRango($idAnterior, $desde, $hasta),
            'tramo_ultimo_ticket_origen' => 'ultimo_leido',
            'tramo_modo' => 'auditoria_en_curso',
            'cantidad_ordenes_informe_z' => count($lineasConIngreso),
            'waitry_order_id_hasta_tope' => $hasta,
            'waitry_order_id_hasta_origen' => $origenTopeHasta,
            'cantidad_canceladas_excluidas' => $cantidadCanceladasExcluidas,
            'cantidad_anuladas_descuento_excluidas' => $cantidadAnuladasDescuentoExcluidas,
            'waitry_anuladas_descuento' => $auditoriaInformeZ['waitry_anuladas_descuento'],
            'diagnostico_waitry' => [
                'ordenes_waitry_en_tramo' => count($listado['ordenes']),
                'ordenes_sin_cancelar' => count($ordenesParaInformeZ),
                'lineas_con_ingreso_totem' => count($lineasConIngreso),
                'total_ingreso_resumen' => (float) ($resumenTotems['total_general']['total_ingreso'] ?? 0),
                'total_informe_z_sistema' => (float) ($resumenInformeZ['total_general']['total_ingreso'] ?? 0),
            ],
        ];
    }

    /**
     * @param  array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}  $resumenTotems
     * @param  array<string, mixed>  $snapshot
     * @return array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>,tramo?:array<string,mixed>}
     */
    private function marcarResumenTotemsDefinitivoAlPersistir(
        array $resumenTotems,
        JornadaGastronomia $jornada,
        array $snapshot,
    ): array {
        $tramo = is_array($resumenTotems['tramo'] ?? null) ? $resumenTotems['tramo'] : [];
        $tramo['tramo_modo'] = 'definitivo_cierre_jornada';
        $tramo['tramo_ultimo_ticket_origen'] = $tramo['tramo_ultimo_ticket_origen'] ?? 'ultimo_leido';
        $tramo['jornada_cierre_en'] = $jornada->cierre_en?->toIso8601String();
        $tramo['persistido_en'] = now()->toIso8601String();
        $tramo['waitry_order_id_hasta'] = (int) ($snapshot['waitry_order_id_hasta'] ?? $tramo['waitry_order_id_hasta'] ?? 0);
        $resumenTotems['tramo'] = $tramo;

        return $resumenTotems;
    }

    /**
     * @param  array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}  $resumenTotems
     * @return array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>,tramo:array<string,mixed>}
     */
    private function resumenTotemsConMetadatosTramo(
        array $resumenTotems,
        int $idAnterior,
        ?int $desde,
        int $hasta,
        string $ultimoTicketOrigen,
        string $tramoModo,
        string $cierreHastaIso,
    ): array {
        $resumenTotems['tramo'] = [
            'waitry_order_id_anterior' => $idAnterior,
            'waitry_order_id_desde' => $desde,
            'waitry_order_id_hasta' => $hasta,
            'tramo_ultimo_ticket_origen' => $ultimoTicketOrigen,
            'tramo_modo' => $tramoModo,
            'tramo_cierre_hasta' => $cierreHastaIso,
            'rango_etiqueta' => $this->etiquetaRango($idAnterior, $desde, $hasta),
        ];

        return $resumenTotems;
    }

    /**
     * Registra el cierre de órdenes Waitry (tótem) al cerrar la jornada gastronómica.
     *
     * @param  array<string, mixed>|null  $informeZBorrador  Informe Z cargado antes del cierre (borrador o request).
     * @param  array<string, mixed>|null  $snapshotCierre  Totales Waitry ya consultados en vista previa (evita 2.ª llamada API).
     */
    public function registrarAlCerrarJornada(
        JornadaGastronomia $jornada,
        ?array $informeZBorrador = null,
        ?array $snapshotCierre = null,
    ): ?CierreTotemJornadaGastronomia {
        if (! $this->habilitado()) {
            return null;
        }

        $empresaId = (int) $jornada->empresa_id;
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa inválida para cierre de órdenes Waitry.');
        }

        $existente = CierreTotemJornadaGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->first();
        if ($existente !== null) {
            return $existente;
        }

        $snapshot = $this->resolverSnapshotCierre($informeZBorrador, $snapshotCierre);
        if ($snapshot === null) {
            throw new InvalidArgumentException(
                'Falta el snapshot del cierre tótem Waitry. Actualice la vista previa del Informe Z antes de cerrar la jornada '
                .'(el cierre no vuelve a consultar Waitry para mantener la grabación rápida).'
            );
        }

        return $this->registrarAlCerrarJornadaDesdeSnapshot($jornada, $snapshot, $informeZBorrador);
    }

    /**
     * Persiste cierre usando snapshot de la vista previa (sin reconsultar Waitry).
     *
     * @param  array<string, mixed>  $snapshot
     */
    private function registrarAlCerrarJornadaDesdeSnapshot(
        JornadaGastronomia $jornada,
        array $snapshot,
        ?array $informeZBorrador,
    ): CierreTotemJornadaGastronomia {
        $empresaId = (int) $jornada->empresa_id;
        $resumenTotems = is_array($snapshot['resumen_totems'] ?? null) ? $snapshot['resumen_totems'] : [];
        $resumenTotems = $this->marcarResumenTotemsDefinitivoAlPersistir($resumenTotems, $jornada, $snapshot);
        $resumenInformeZ = is_array($snapshot['resumen_informe_z'] ?? null)
            ? $snapshot['resumen_informe_z']
            : WaitryInformeZConciliacionSupport::filtrarResumenSoloCreditCardPosnet($resumenTotems);
        $resumenInformeZ = $this->marcarResumenTotemsDefinitivoAlPersistir($resumenInformeZ, $jornada, $snapshot);
        $totalGeneral = is_array($resumenTotems['total_general'] ?? null) ? $resumenTotems['total_general'] : [];
        $idAnterior = (int) ($snapshot['waitry_order_id_anterior'] ?? $this->ultimoWaitryOrderIdHasta($empresaId));
        $desde = isset($snapshot['waitry_order_id_desde']) ? (int) $snapshot['waitry_order_id_desde'] : null;
        $hasta = (int) ($snapshot['waitry_order_id_hasta'] ?? $idAnterior);
        $auditoria = is_array($snapshot['auditoria'] ?? null) ? $snapshot['auditoria'] : [];
        $auditoria['registro_desde_snapshot'] = true;
        $auditoria['ventana_operativa'] = $snapshot['ventana_operativa'] ?? ($auditoria['ventana_operativa'] ?? '');
        $auditoria['snapshot_preview_en'] = $snapshot['preview_en'] ?? null;

        $informeZJson = $this->informeZJsonParaPersistir($informeZBorrador);

        $detalleJson = [
            'lineas' => [],
            'resumen_totems' => $resumenTotems,
            'resumen_informe_z' => $resumenInformeZ,
            'auditoria' => $auditoria,
        ];
        $ordenesSnap = $snapshot[WaitryInformeZTransmisionFaltanteSupport::CLAVE_ORDENES_SNAPSHOT] ?? null;
        if (is_array($ordenesSnap)) {
            $detalleJson[WaitryInformeZTransmisionFaltanteSupport::CLAVE_ORDENES_SNAPSHOT] = array_values($ordenesSnap);
        }
        if (is_array($informeZJson) && is_array($informeZJson['totems'] ?? null)) {
            $detalleJson['informe_z_por_totem'] = $informeZJson['totems'];
        }

        return $this->persistirRegistroCierreTotem(
            $jornada,
            $empresaId,
            $idAnterior,
            $desde,
            $hasta,
            (int) ($totalGeneral['cantidad_ordenes'] ?? 0),
            round((float) ($totalGeneral['total_ingreso'] ?? 0), 2),
            0,
            0,
            0,
            $detalleJson,
            $informeZJson,
            false,
        );
    }

    /**
     * @param  array<string, mixed>|null  $informeZBorrador
     * @param  array<string, mixed>|null  $snapshotRequest
     * @return array<string, mixed>|null
     */
    private function resolverSnapshotCierre(?array $informeZBorrador, ?array $snapshotRequest): ?array
    {
        $candidatos = [
            $snapshotRequest,
            is_array($informeZBorrador['snapshot_cierre'] ?? null) ? $informeZBorrador['snapshot_cierre'] : null,
        ];

        foreach ($candidatos as $snapshot) {
            if (! is_array($snapshot)) {
                continue;
            }
            $resumen = $snapshot['resumen_totems'] ?? null;
            if (is_array($resumen) && is_array($resumen['por_totem'] ?? null)) {
                return $snapshot;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resumenTotems
     * @param  array<string, mixed>  $auditoria
     * @return array<string, mixed>
     */
    private function armarSnapshotCierre(
        int $idAnterior,
        ?int $desde,
        int $hasta,
        array $resumenTotems,
        array $auditoria,
        string $ventanaOperativa,
        ?array $resumenInformeZ = null,
    ): array {
        $snapshot = [
            'waitry_order_id_anterior' => $idAnterior,
            'waitry_order_id_desde' => $desde,
            'waitry_order_id_hasta' => $hasta,
            'resumen_totems' => $resumenTotems,
            'auditoria' => $auditoria,
            'ventana_operativa' => $ventanaOperativa,
            'preview_en' => now()->format('Y-m-d H:i:s'),
        ];
        if (is_array($resumenInformeZ)) {
            $snapshot['resumen_informe_z'] = $resumenInformeZ;
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>|null  $informeZBorrador
     * @return array<string, mixed>|null
     */
    private function informeZJsonParaPersistir(?array $informeZBorrador): ?array
    {
        if ($informeZBorrador === null || ! isset($informeZBorrador['totems'])) {
            return null;
        }

        unset($informeZBorrador['snapshot_cierre']);

        return $informeZBorrador;
    }

    /**
     * @param  array<string, mixed>  $detalleJson
     */
    private function persistirRegistroCierreTotem(
        JornadaGastronomia $jornada,
        int $empresaId,
        int $idAnterior,
        ?int $desde,
        int $hasta,
        int $cantidadLineas,
        float $totalMonto,
        int $impagas,
        int $pagadas,
        int $facturadas,
        array $detalleJson,
        ?array $informeZJson,
        bool $detalleTruncado,
    ): CierreTotemJornadaGastronomia {
        return CierreTotemJornadaGastronomia::query()->create([
            'jornada_gastronomia_id' => (int) $jornada->id,
            'empresa_id' => $empresaId,
            'waitry_order_id_anterior' => $idAnterior,
            'waitry_order_id_desde' => $desde,
            'waitry_order_id_hasta' => $hasta,
            'cantidad_lineas' => $cantidadLineas,
            'total_monto' => $totalMonto,
            'cantidad_impagas_waitry' => $impagas,
            'cantidad_pagadas_waitry' => $pagadas,
            'cantidad_facturadas_erp' => $facturadas,
            'detalle_json' => $detalleJson,
            'informe_z_json' => $informeZJson,
            'detalle_truncado' => $detalleTruncado,
            'usuario_id' => Auth::id(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function datosComprobantePdf(CierreTotemJornadaGastronomia $cierre): array
    {
        $cierre->loadMissing(['jornada', 'empresa', 'usuario']);

        $empresaNombre = $cierre->empresa?->nombre ?? '';
        $idAnterior = (int) $cierre->waitry_order_id_anterior;
        $desde = $cierre->waitry_order_id_desde;
        $hasta = $cierre->waitry_order_id_hasta;

        $detalleJson = is_array($cierre->detalle_json) ? $cierre->detalle_json : [];
        $lineas = $cierre->lineasDetalle();
        $auditoria = is_array($detalleJson['auditoria'] ?? null) ? $detalleJson['auditoria'] : [];
        $resumenTotems = $detalleJson['resumen_totems'] ?? null;
        if (! is_array($resumenTotems) || ($resumenTotems['por_totem'] ?? null) === null) {
            $totems = TotemWaitryGastronomia::query()
                ->with('ubicacion')
                ->where('empresa_id', (int) $cierre->empresa_id)
                ->orderBy('ubicacion_id')
                ->get();
            $resumenTotems = WaitryTotemJornadaResumenSupport::armar($totems, $lineas, (int) $cierre->empresa_id);
        }

        return [
            'titulo' => 'Cierre de jornada — ingresos tótem Waitry',
            'subtitulo' => 'Jornada #'.$cierre->jornada_gastronomia_id
                .' · '.$cierre->jornada?->fecha_jornada?->format('d/m/Y')
                .' — presentar en caja',
            'logo' => EmpresaLogoArchivo::dataUriDesdeNombre($empresaNombre),
            'empresa_nombre' => $empresaNombre,
            'fecha_jornada' => $cierre->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'apertura_jornada_en' => $cierre->jornada?->apertura_en?->format('d/m/Y H:i') ?? '',
            'cierre_jornada_en' => $cierre->jornada?->cierre_en?->format('d/m/Y H:i') ?? '',
            'usuario_registro' => $cierre->usuario?->nombre ?? '',
            'fecha_emision_comprobante' => $cierre->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i'),
            'waitry_order_id_anterior' => $idAnterior,
            'waitry_order_id_desde' => $desde,
            'waitry_order_id_hasta' => $hasta,
            'proximo_waitry_order_id' => $hasta !== null ? (int) $hasta + 1 : $idAnterior + 1,
            'rango_etiqueta' => $this->etiquetaRango($idAnterior, $desde, $hasta),
            'cantidad_lineas' => (int) $cierre->cantidad_lineas,
            'total_monto' => (float) $cierre->total_monto,
            'cantidad_impagas_waitry' => (int) $cierre->cantidad_impagas_waitry,
            'cantidad_pagadas_waitry' => (int) $cierre->cantidad_pagadas_waitry,
            'cantidad_facturadas_erp' => (int) $cierre->cantidad_facturadas_erp,
            'detalle_truncado' => (bool) $cierre->detalle_truncado,
            'lineas' => $lineas,
            'cantidad_discrepancias' => (int) ($auditoria['cantidad_discrepancias'] ?? count($lineas)),
            'hay_discrepancias' => WaitryCierreJornadaDiscrepanciaSupport::hayDiscrepanciasAuditoria(
                $auditoria,
                (int) ($auditoria['cantidad_discrepancias'] ?? count($lineas)),
            ),
            'ventana_operativa' => $auditoria['ventana_operativa'] ?? '',
            'consulta_waitry_rango' => $auditoria['consulta_waitry_rango'] ?? '',
            'por_totem' => $resumenTotems['por_totem'] ?? [],
            'total_general' => $resumenTotems['total_general'] ?? [
                'cantidad_ordenes' => 0,
                'total_ingreso' => 0.0,
                'por_medio_pago' => [],
            ],
            'auditoria' => $auditoria,
            'informe_z' => is_array($cierre->informe_z_json) ? $cierre->informe_z_json : null,
            'conciliacion_informe_z' => ($presentacionIz = WaitryInformeZConciliacionSupport::conciliacionPresentacionDesdeCierre($cierre)) !== null
                ? $presentacionIz['conciliacion']
                : null,
            'informe_z_cargado' => is_array($cierre->informe_z_json) && isset($cierre->informe_z_json['totems']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array{tipo:?string,etiqueta:string,cantidad:int,total:float,cuentacaja_label:?string}>
     */
    private function resumenPorMedioWaitry(array $lineas, int $empresaId): array
    {
        $map = [];
        foreach ($lineas as $ln) {
            if (! WaitryTotemJornadaResumenSupport::lineaCuentaParaIngresoTotem($ln)) {
                continue;
            }
            $monto = (float) ($ln['monto_cobro_waitry'] ?? 0) > 0.0001
                ? (float) $ln['monto_cobro_waitry']
                : (float) ($ln['total'] ?? 0);
            $tipo = WaitryMedioPagoCuentacajaSupport::resolverTipoMedioInformeZDesdeLinea($ln, $empresaId);
            if ($tipo === null) {
                continue;
            }
            if (! isset($map[$tipo])) {
                $map[$tipo] = [
                    'tipo' => $ln['waitry_tipo_pago'] ?? null,
                    'etiqueta' => (string) ($ln['waitry_medio_label'] ?? WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipo)),
                    'cantidad' => 0,
                    'total' => 0.0,
                    'cuentacaja_label' => $ln['cuentacaja_esperada_label'] ?? null,
                ];
            }
            $map[$tipo]['cantidad']++;
            $map[$tipo]['total'] = round($map[$tipo]['total'] + $monto, 2);
        }

        return array_values($map);
    }

    /**
     * Último order_id Waitry a incluir en el tramo de esta jornada.
     * Jornada cerrada con cierre tótem: el guardado al cerrar; abierta: el mayor leído en la consulta.
     *
     * @param  array<int|string, array<string, mixed>>  $ordenesActivas
     */
    private function resolverTopeWaitryOrderIdHasta(JornadaGastronomia $jornada, array $ordenesActivas): int
    {
        return $this->resolverTopeConInstante($jornada, $ordenesActivas)['id'];
    }

    /**
     * Tope de la jornada: order_id de la ÚLTIMA orden real por fecha/hora (placed_at) e instante.
     *
     * El watermark se elige por fecha/hora y no por mayor order_id: si Waitry reinicia/cambia el
     * numerador (p. ej. salto a la serie 1.000.000.000) el máximo numérico no es la última orden
     * real y contaminaría el piso de la próxima jornada. La continuidad de la jornada siguiente
     * sigue siendo por número (order_id > hasta), que en operación normal es secuencial.
     *
     * @param  array<int|string, array<string, mixed>>  $ordenesActivas
     * @return array{id: int, instante: ?Carbon}
     */
    private function resolverTopeConInstante(JornadaGastronomia $jornada, array $ordenesActivas): array
    {
        $cierre = CierreTotemJornadaGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->first();

        $porFecha = $this->topeUltimaOrdenPorFecha(
            $ordenesActivas,
            (int) $jornada->empresa_id,
            $jornada->fecha_jornada?->format('Y-m-d') ?? '',
        );

        if ($cierre !== null && (int) $cierre->waitry_order_id_hasta > 0) {
            $hastaId = (int) $cierre->waitry_order_id_hasta;
            $instantePersistido = $this->instanteOrdenPorId($ordenesActivas, $hastaId);

            // Self-heal: si la relectura tiene comandas dentro de la ventana de jornada colocadas
            // después del watermark persistido (quedó bajo por un preview anterior al último ticket
            // o por un salto de numeración Waitry), extender el tope al último real por fecha/hora.
            if ($porFecha['instante'] !== null
                && ($instantePersistido === null || $porFecha['instante']->greaterThan($instantePersistido))) {
                return $porFecha;
            }

            return ['id' => $hastaId, 'instante' => $instantePersistido];
        }

        return $porFecha;
    }

    /**
     * Última orden por fecha/hora (placed_at). Devuelve su order_id e instante.
     * Si ninguna orden tiene fecha parseable, cae al mayor order_id (comportamiento previo).
     * Monitor: si el mayor order_id se aleja del último por fecha/hora más allá de
     * {@see self::GAP_NUMERADOR_ALERTA}, registra un aviso (Waitry cambió el numerador).
     *
     * @param  array<int|string, array<string, mixed>>  $ordenes
     * @return array{id: int, instante: ?Carbon}
     */
    private function topeUltimaOrdenPorFecha(array $ordenes, int $empresaId = 0, string $fechaJornada = ''): array
    {
        $idPorFecha = 0;
        $instanteTope = null;
        $maxId = 0;
        foreach ($ordenes as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            $id = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($id > $maxId) {
                $maxId = $id;
            }
            $instante = $this->parsearInstanteOrden($orden);
            if ($instante === null) {
                continue;
            }
            if ($instanteTope === null
                || $instante->greaterThan($instanteTope)
                || ($instante->equalTo($instanteTope) && $id > $idPorFecha)
            ) {
                $instanteTope = $instante;
                $idPorFecha = $id;
            }
        }

        $topeId = $idPorFecha > 0 ? $idPorFecha : $maxId;

        if ($topeId > 0 && $maxId - $topeId > self::GAP_NUMERADOR_ALERTA) {
            Log::warning('gastronomia.cierre_totem.numerador_waitry_anomalo', [
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'tope_por_fecha' => $topeId,
                'tope_instante' => $instanteTope?->format('Y-m-d H:i:s'),
                'max_order_id' => $maxId,
                'gap' => $maxId - $topeId,
                'nota' => 'Waitry parece haber cambiado el numerador de order_id; el tope se toma por fecha/hora, no por número.',
            ]);
        }

        return ['id' => $topeId, 'instante' => $instanteTope];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $ordenes
     */
    private function instanteOrdenPorId(array $ordenes, int $orderId): ?Carbon
    {
        foreach ($ordenes as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            if ((int) ($orden['orderId'] ?? $orden['id'] ?? 0) === $orderId) {
                return $this->parsearInstanteOrden($orden);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private function parsearInstanteOrden(array $orden): ?Carbon
    {
        $placedAt = $orden['placed_at'] ?? null;
        if (($placedAt === null || $placedAt === '') && isset($orden['timestamp']['date'])) {
            $placedAt = $orden['timestamp']['date'];
        }
        if (! is_string($placedAt) || trim($placedAt) === '') {
            return null;
        }

        try {
            return Carbon::parse($placedAt);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Si la jornada ya cerró y el self-heal extendió el tope, persistir el watermark.
     * Así el Informe Z y la jornada siguiente usan el mismo último ticket.
     */
    private function persistirWatermarkSiJornadaCerrada(CierreTotemJornadaGastronomia $cierre, int $hasta): void
    {
        $persistido = (int) ($cierre->waitry_order_id_hasta ?? 0);
        if ($hasta <= $persistido) {
            return;
        }

        $cierre->loadMissing('jornada');
        if ((string) ($cierre->jornada?->estado ?? '') !== JornadaGastronomia::ESTADO_CERRADA) {
            return;
        }

        $cierre->waitry_order_id_hasta = $hasta;
        $cierre->save();

        Log::info('gastronomia.cierre_totem.watermark_extendido_post_cierre', [
            'cierre_totem_id' => (int) $cierre->id,
            'jornada_id' => (int) $cierre->jornada_gastronomia_id,
            'empresa_id' => (int) $cierre->empresa_id,
            'hasta_anterior' => $persistido,
            'hasta_nuevo' => $hasta,
        ]);
    }

    private function origenTopeWaitryOrderIdHasta(JornadaGastronomia $jornada): string
    {
        $cierre = CierreTotemJornadaGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->first();

        if ($cierre !== null && (int) $cierre->waitry_order_id_hasta > 0) {
            return 'ultimo_ticket_cierre_jornada';
        }

        return 'ultimo_leido';
    }

    /**
     * Acota las órdenes al tope de la jornada. Filtra "por los dos": una orden queda dentro si su
     * order_id es &lt;= al hasta (número, operación secuencial normal) O si su fecha/hora (placed_at)
     * es &lt;= al instante del tope. La condición temporal solo agrega órdenes que el número dejaría
     * fuera cuando Waitry cambió el numerador (serie 1.000.000.000 dentro de la jornada); en
     * operación normal (id monótono con la hora) ambas condiciones coinciden y no cambia nada.
     *
     * @param  array<int|string, array<string, mixed>>  $ordenes
     * @return array<int|string, array<string, mixed>>
     */
    private function filtrarOrdenesActivasPorTopeHasta(array $ordenes, int $hastaInclusive, ?Carbon $instanteTope = null): array
    {
        if ($hastaInclusive <= 0 && $instanteTope === null) {
            return $ordenes;
        }

        return array_filter(
            $ordenes,
            function ($orden) use ($hastaInclusive, $instanteTope) {
                if (! is_array($orden)) {
                    return false;
                }
                $id = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);
                if ($id <= 0) {
                    return false;
                }
                if ($hastaInclusive > 0 && $id <= $hastaInclusive) {
                    return true;
                }
                if ($instanteTope !== null) {
                    $instante = $this->parsearInstanteOrden($orden);

                    return $instante !== null && $instante->lessThanOrEqualTo($instanteTope);
                }

                return false;
            },
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function etiquetaRango(int $idAnterior, ?int $desde, ?int $hasta): string
    {
        if ($desde === null) {
            return 'Sin órdenes Waitry nuevas (último ID cerrado: #'.$idAnterior.')';
        }

        if ($desde === $hasta) {
            return 'Orden Waitry #'.$desde.' (nuevas desde #'.($idAnterior + 1).')';
        }

        return 'Órdenes Waitry #'.$desde.' — #'.$hasta.' (nuevas desde #'.($idAnterior + 1).')';
    }

    /**
     * Órdenes Waitry con orderId &gt; $desdeExclusive; API por días calendario entre apertura y cierre; filtro por hora exacta.
     *
     * @return array{
     *   ordenes: array<int, array<string, mixed>>,
     *   auditoria: array<string, mixed>,
     *   ventana: array{desde:?Carbon,hasta:?Carbon,etiqueta:string}
     * }
     */
    private function listarOrdenesWaitryNuevas(
        int $empresaId,
        string $fechaJornada,
        int $desdeExclusive,
        mixed $aperturaEn,
        mixed $cierreEn,
    ): array {
        $limite = max(1, (int) config('gastronomia.cierre_totem_jornada_max_ordenes', 15000));
        $resuelto = WaitryCierreJornadaVentanaSupport::resolverParaCierreJornada(
            $fechaJornada,
            $aperturaEn,
            $cierreEn,
        );
        $ventana = $resuelto['ventana'];
        $rangoCal = $resuelto['rango_calendario'];

        $waitry = $this->analyticsOrdenesService->ordenesPorRangoFecha(
            $empresaId,
            $rangoCal['desde'],
            $rangoCal['hasta'],
        );

        $porId = [];
        $descartadasFueraVentana = 0;
        $fuenteConsulta = 'getordersdetails';
        $ordenesFuente = ($waitry['ok'] ?? false) ? ($waitry['ordenes'] ?? []) : [];

        if ($ordenesFuente === []) {
            $mapPos = $this->ordenesExternasService->mapOrdenesPosEnVentanaJornada(
                $empresaId,
                $fechaJornada,
                $aperturaEn,
                $cierreEn,
            );
            if ($mapPos !== []) {
                $ordenesFuente = array_values($mapPos);
                $fuenteConsulta = 'getOrdersPOS';
            } elseif (! ($waitry['ok'] ?? false)) {
                throw new RuntimeException(
                    $waitry['error'] ?? 'No se pudieron obtener órdenes Waitry (getordersdetails) para el cierre de jornada.'
                );
            }
        }

        foreach ($ordenesFuente as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            $id = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);
            if (! WaitryCierreJornadaVentanaSupport::perteneceTramoOrderId($id, $desdeExclusive)) {
                continue;
            }
            $normalizada = $this->analyticsOrdenesService->normalizarOrden($orden);
            if (! WaitryCierreJornadaVentanaSupport::ordenDentroVentanaOperativa(
                $normalizada,
                $ventana['desde'],
                $ventana['hasta'],
            )) {
                $descartadasFueraVentana++;

                continue;
            }
            $porId[$id] = $normalizada;
            $porId[$id]['fuente'] = $fuenteConsulta;
        }

        $idsErpSuplemento = $this->suplementarOrdenesDesdeErp(
            $empresaId,
            $fechaJornada,
            $desdeExclusive,
            $porId,
            $ventana['desde'] ?? null,
            $ventana['hasta'] ?? null,
        );

        $porId = $this->enriquecerPaymentOrdenesDesdePos(
            $empresaId,
            $porId,
            $fechaJornada,
            $aperturaEn,
            $cierreEn,
        )['ordenes'];

        $idsHuecos = $this->detectarHuecosSecuenciales($desdeExclusive, $porId, $empresaId, $fechaJornada);

        if (count($porId) > $limite) {
            throw new InvalidArgumentException(
                'Hay más de '.$limite.' órdenes Waitry nuevas desde el ID #'.($desdeExclusive + 1)
                .'. Cierre jornadas intermedias o aumente GASTRONOMIA_CIERRE_TOTEM_MAX_ORDENES.'
            );
        }

        ksort($porId, SORT_NUMERIC);

        return [
            'ordenes' => $porId,
            'ventana' => $ventana,
            'auditoria' => [
                'consulta_waitry_fecha' => $fechaJornada,
                'consulta_waitry_rango' => $rangoCal['etiqueta'],
                'ventana_operativa' => $ventana['etiqueta'],
                'ventana_desde' => $ventana['desde']->format('Y-m-d H:i:s'),
                'ventana_hasta' => $ventana['hasta']->format('Y-m-d H:i:s'),
                'waitry_order_id_anterior' => $desdeExclusive,
                'cantidad_getordersdetails' => count($ordenesFuente),
                'waitry_fuente_consulta' => $fuenteConsulta,
                'cantidad_descartadas_fuera_ventana' => $descartadasFueraVentana,
                'cantidad_incluidas' => count($porId),
                'ids_suplementados_erp' => $idsErpSuplemento,
                'ids_huecos_secuencia' => $idsHuecos,
                /** @deprecated Usar ids_huecos_secuencia; se mantiene por compatibilidad en JSON/PDF guardados */
                'ids_gap_sin_recuperar' => $idsHuecos,
                'ids_recuperados_gap' => [],
                'huecos_pendientes_auditoria_dia' => $idsHuecos !== [],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $ordenesPorId
     * @return array{ordenes: array<int, array<string, mixed>>, map_pos: array<int, array<string, mixed>>}
     */
    private function enriquecerPaymentOrdenesDesdePos(
        int $empresaId,
        array $ordenesPorId,
        string $fechaJornada,
        mixed $aperturaEn,
        mixed $cierreEn,
    ): array {
        return WaitryOrdenPaymentEnriquecimientoSupport::enriquecerDesdePos(
            $this->ordenesExternasService,
            $empresaId,
            $ordenesPorId,
            $fechaJornada,
            $aperturaEn,
            $cierreEn,
        );
    }

    /**
     * IDs Waitry ausentes entre el último cierre y el máximo visto en getordersdetails.
     * No consulta Waitry: quedan como discrepancia para auditoría del día (proceso posterior).
     *
     * Protección de memoria: los waitry_order_id son globales de Waitry (se intercalan con
     * órdenes de otras cuentas) y el ERP puede suplementar ids muy antiguos. Un rango con un
     * id atípico (min muy chico o max enorme) generaría millones de "huecos" y agotaría la
     * memoria (bytes exhausted en el cierre de jornada). Se acota la cantidad de huecos y las
     * iteraciones; al superarse, se corta y se registra la anomalía para auditoría del día.
     *
     * @param  array<int, array<string, mixed>>  $porId
     * @return list<int>
     */
    private function detectarHuecosSecuenciales(
        int $desdeExclusive,
        array $porId,
        int $empresaId = 0,
        string $fechaJornada = '',
    ): array {
        if ($porId === []) {
            return [];
        }

        $ids = array_keys($porId);
        $maxId = (int) max($ids);
        $minId = (int) min($ids);
        $inicio = max($desdeExclusive + 1, $minId);

        $maxHuecos = max(1000, (int) config('gastronomia.cierre_totem_jornada_max_huecos_secuencia', 20000));
        $maxIteraciones = $maxHuecos * 50;

        $huecos = [];
        $iteraciones = 0;
        $cortadoPorLimite = false;
        for ($id = $inicio; $id <= $maxId; $id++) {
            if (++$iteraciones > $maxIteraciones) {
                $cortadoPorLimite = true;

                break;
            }
            if (! isset($porId[$id])) {
                $huecos[] = $id;
                if (count($huecos) >= $maxHuecos) {
                    $cortadoPorLimite = true;

                    break;
                }
            }
        }

        if ($cortadoPorLimite) {
            Log::warning('gastronomia.cierre_totem.huecos_secuencia_excedidos', [
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'desde_exclusive' => $desdeExclusive,
                'min_id' => $minId,
                'max_id' => $maxId,
                'inicio' => $inicio,
                'span' => $maxId - $inicio,
                'ordenes_en_ventana' => count($porId),
                'huecos_detectados' => count($huecos),
                'max_huecos' => $maxHuecos,
            ]);
        }

        return $huecos;
    }

    /**
     * Filas de discrepancia sintéticas por hueco de secuencia (sin detalle Waitry en el listado del día).
     *
     * @param  list<int>  $idsHuecos
     * @return list<array<string, mixed>>
     */
    private function armarLineasHuecosPendientesAuditoria(array $idsHuecos): array
    {
        $lineas = [];
        foreach ($idsHuecos as $orderId) {
            $orderId = (int) $orderId;
            if ($orderId <= 0) {
                continue;
            }
            $lineas[] = [
                'waitry_order_id' => $orderId,
                'display_id' => '',
                'placed_at_fmt' => '',
                'total' => 0.0,
                'monto_cobro_waitry' => null,
                'waitry_table_id' => null,
                'paid_waitry' => null,
                'waitry_tipo_pago' => null,
                'waitry_medio_label' => null,
                'cuentacaja_esperada_id' => null,
                'cuentacaja_esperada_label' => null,
                'importada_erp' => false,
                'facturada_erp' => false,
                'cuenta_id' => null,
                'cuenta_estado' => null,
                'waitry_cobro_totem' => false,
                'venta_codigo' => '',
                'fuente_listado' => 'hueco_secuencia',
                'discrepancia_gap' => true,
            ];
        }

        return $lineas;
    }

    /**
     * Incluye waitry_order_id presentes en ERP pero ausentes en getordersdetails del día.
     *
     * @param  array<int, array<string, mixed>>  $porId
     * @return list<int>
     */
    private function suplementarOrdenesDesdeErp(
        int $empresaId,
        string $fechaJornada,
        int $desdeExclusive,
        array &$porId,
        ?Carbon $ventanaDesde = null,
        ?Carbon $ventanaHasta = null,
    ): array {
        if ($ventanaDesde === null || $ventanaHasta === null) {
            return [];
        }

        $desdeSql = $ventanaDesde->format('Y-m-d H:i:s');
        $hastaSql = $ventanaHasta->format('Y-m-d H:i:s');
        $stubs = [];

        CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('waitry_order_id')
            ->where('waitry_order_id', '>', $desdeExclusive)
            ->where(function ($q) use ($desdeSql, $hastaSql) {
                $q->whereBetween('updated_at', [$desdeSql, $hastaSql])
                    ->orWhereBetween('created_at', [$desdeSql, $hastaSql]);
            })
            ->orderByDesc('id')
            ->get()
            ->unique('waitry_order_id')
            ->each(function (CuentaGastronomia $cuenta) use (&$stubs) {
                $orderId = (int) $cuenta->waitry_order_id;
                $instante = $cuenta->updated_at ?? $cuenta->created_at;
                $stubs[$orderId] = [
                    'placed_at' => $instante?->format('Y-m-d H:i:s'),
                    'totalAmount' => 0.0,
                    'paid' => $cuenta->waitry_cobro_totem ? true : null,
                    'waitry_tipo_pago' => $cuenta->waitry_tipo_pago,
                ];
            });

        VentaGastronomiaEmision::query()
            ->with('venta:id,total,created_at')
            ->whereNotNull('waitry_order_id')
            ->where('waitry_order_id', '>', $desdeExclusive)
            ->whereHas('venta', function ($q) use ($empresaId, $desdeSql, $hastaSql) {
                $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
                $q->whereBetween('created_at', [$desdeSql, $hastaSql]);
            })
            ->orderByDesc('venta_id')
            ->get()
            ->each(function (VentaGastronomiaEmision $emision) use (&$stubs) {
                $orderId = (int) $emision->waitry_order_id;
                if ($orderId <= 0) {
                    return;
                }
                if (! isset($stubs[$orderId])) {
                    $stubs[$orderId] = [
                        'placed_at' => $emision->venta?->created_at?->format('Y-m-d H:i:s'),
                        'totalAmount' => round((float) ($emision->venta?->total ?? 0), 2),
                        'paid' => null,
                    ];
                }
            });

        $agregados = [];
        foreach ($stubs as $orderId => $stub) {
            if (isset($porId[$orderId])) {
                continue;
            }
            $ordenStub = [
                'id' => $orderId,
                'orderId' => $orderId,
                'display_id' => null,
                'placed_at' => $stub['placed_at'] ?? null,
                'totalAmount' => (float) ($stub['totalAmount'] ?? 0),
                'paid' => $stub['paid'] ?? null,
                'waitry_tipo_pago' => $stub['waitry_tipo_pago'] ?? null,
                'fuente' => 'erp',
            ];
            if (! WaitryCierreJornadaVentanaSupport::ordenDentroVentanaOperativa(
                $ordenStub,
                $ventanaDesde,
                $ventanaHasta,
            )) {
                continue;
            }
            $porId[$orderId] = $ordenStub;
            $agregados[] = $orderId;
        }

        return $agregados;
    }

    private function prepararEntornoConsultaCierreTotem(): void
    {
        $memory = (string) config('gastronomia.cierre_jornada_proceso_memory_limit', '1024M');
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }
        @set_time_limit(180);
    }

    /**
     * Emisiones gastronomía vinculadas a los order_id del tramo (no todo el histórico de la empresa).
     *
     * @param  list<int>  $ids
     * @return array<int, VentaGastronomiaEmision>
     */
    private function mapaEmisionPorWaitryOrderIds(int $empresaId, array $ids): array
    {
        $idsUnicos = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
        if ($idsUnicos === []) {
            return [];
        }

        $eager = [
            'venta:id,codigo,total',
            'venta.cobranzasDirectas',
            'venta.caja_movimientos.cobranzas',
            'cuenta:id,waitry_cobro_totem,waitry_tipo_pago,waitry_order_id',
            'waitryComandaEnvio',
        ];

        $map = [];
        foreach (array_chunk($idsUnicos, 500) as $lote) {
            $emisiones = VentaGastronomiaEmision::query()
                ->with($eager)
                ->whereHas('venta', fn ($q) => $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId)))
                ->where(function ($q) use ($lote) {
                    $q->whereIn('waitry_order_id', $lote)
                        ->orWhereHas('cuenta', fn ($c) => $c->whereIn('waitry_order_id', $lote))
                        ->orWhereHas('waitryComandaEnvio', fn ($e) => $e->whereIn('waitry_order_id', $lote));
                })
                ->orderByDesc('venta_id')
                ->get();

            foreach ($emisiones as $emision) {
                $wid = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
                if ($wid > 0 && ! isset($map[$wid])) {
                    $map[$wid] = $emision;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $ordenesPorId
     * @return list<array<string, mixed>>
     */
    private function armarLineasConEstadoErp(
        int $empresaId,
        array $ordenesPorId,
        ?Carbon $ventanaDesde = null,
        ?Carbon $ventanaHasta = null,
    ): array {
        if ($ordenesPorId === [] || $ventanaDesde === null || $ventanaHasta === null) {
            return [];
        }

        $ids = array_keys($ordenesPorId);

        $cuentasPorWaitry = CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('waitry_order_id', $ids)
            ->orderByDesc('id')
            ->get()
            ->unique('waitry_order_id')
            ->keyBy(fn (CuentaGastronomia $c) => (int) $c->waitry_order_id);

        $mapEmisionPorWaitryId = $this->mapaEmisionPorWaitryOrderIds($empresaId, $ids);

        $lineas = [];
        foreach ($ordenesPorId as $orderId => $orden) {
            if (! WaitryCierreJornadaVentanaSupport::ordenDentroVentanaOperativa($orden, $ventanaDesde, $ventanaHasta)) {
                continue;
            }

            $cuenta = $cuentasPorWaitry->get($orderId);
            $emision = $mapEmisionPorWaitryId[$orderId] ?? null;

            $total = round((float) ($orden['totalAmount'] ?? 0), 2);
            if ($total <= 0. && $emision?->venta) {
                $total = round((float) $emision->venta->total, 2);
            }

            $paid = WaitryOrdenCobroSupport::cobradaEnTotem($orden) ? true : (
                array_key_exists('paid', $orden) && in_array($orden['paid'], [0, '0', false], true) ? false : null
            );
            $montoCobro = WaitryOrdenCobroSupport::montoCobro($orden);
            $waitryGateway = WaitryPaymentGatewaySupport::extraerGatewayDesdeOrden($orden);
            $waitryTipoPago = WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($orden)
                ?? WaitryMedioPagoCuentacajaSupport::normalizarTipo($cuenta?->waitry_tipo_pago)
                ?? WaitryMedioPagoCuentacajaSupport::normalizarTipo($emision?->cuenta?->waitry_tipo_pago);
            if ($waitryTipoPago === null && WaitryTotemJornadaResumenSupport::cobradaEnWaitryLinea([
                'paid_waitry' => $paid,
                'waitry_cobro_totem' => (bool) ($cuenta?->waitry_cobro_totem ?? $emision?->cuenta?->waitry_cobro_totem),
                'monto_cobro_waitry' => $montoCobro,
            ])) {
                $waitryTipoPago = WaitryMedioPagoCuentacajaSupport::resolverTipoMedioInformeZDesdeLinea(
                    ['waitry_tipo_pago' => null],
                    $empresaId,
                );
            }
            $cuentaEsperada = WaitryMedioPagoCuentacajaSupport::cuentaParaTipoInformeZ($waitryTipoPago, $empresaId, $waitryGateway)
                ?? WaitryMedioPagoCuentacajaSupport::cuentaParaTipoWaitry($waitryTipoPago, $empresaId);
            $accesoWaitry = WaitryTableAccesoSupport::extraerDesdeOrden($orden);
            $waitryTableId = $accesoWaitry['table_id'];

            $displayId = trim((string) (
                $orden['display_id']
                ?? $orden['externalDeliveryId']
                ?? $orden['external_reference_id']
                ?? $cuenta?->waitry_display_id
                ?? ''
            ));

            $anitaCuentacajaId = null;
            $anitaCuentacajaLabel = null;
            $anitaEsTotem = false;
            if ($emision !== null) {
                $medioAnita = $this->primerMedioCobranzaEmision($emision, $empresaId);
                if ($medioAnita !== null) {
                    $anitaCuentacajaId = $medioAnita['cuentacaja_id'];
                    $anitaCuentacajaLabel = $medioAnita['label'];
                    $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
                    $anitaEsTotem = $totemId > 0 && $anitaCuentacajaId === $totemId;
                }
            }

            $lineas[] = [
                'waitry_order_id' => $orderId,
                'display_id' => $displayId,
                'placed_at_fmt' => $this->formatearPlacedAt($orden['placed_at'] ?? null),
                'total' => $total,
                'total_amount_waitry' => WaitryOrdenEstadoSupport::montoBrutoWaitry($orden),
                'total_discount_waitry' => WaitryOrdenEstadoSupport::montoDescuentoWaitry($orden),
                'monto_cobro_waitry' => $montoCobro,
                'waitry_table_id' => $waitryTableId,
                'waitry_table_name' => $accesoWaitry['table_name'],
                'waitry_layout_id' => $accesoWaitry['layout_id'],
                'waitry_layout_name' => $accesoWaitry['layout_name'],
                'paid_waitry' => $paid,
                'waitry_tipo_pago' => $waitryTipoPago,
                'waitry_payment_gateway' => $waitryGateway,
                'orden_push_erp' => WaitryPaymentGatewaySupport::esOrdenPushErp([
                    'waitry_tipo_pago' => $waitryTipoPago,
                    'display_id' => $displayId,
                    'external_reference_id' => $orden['external_reference_id'] ?? null,
                ]),
                'waitry_medio_label' => WaitryMedioPagoCuentacajaSupport::etiquetaTipo($waitryTipoPago, $waitryGateway),
                'cuentacaja_esperada_id' => $cuentaEsperada['id'] ?? null,
                'cuentacaja_esperada_label' => isset($cuentaEsperada['codigo'], $cuentaEsperada['nombre'])
                    ? trim($cuentaEsperada['codigo'].' — '.$cuentaEsperada['nombre'])
                    : null,
                'importada_erp' => $cuenta !== null,
                'facturada_erp' => $emision !== null,
                'cuenta_id' => $cuenta?->id,
                'cuenta_estado' => $cuenta?->estado,
                'waitry_cobro_totem' => (bool) ($cuenta?->waitry_cobro_totem ?? $emision?->cuenta?->waitry_cobro_totem),
                'venta_codigo' => $emision?->venta?->codigo ?? '',
                'fuente_listado' => $orden['fuente'] ?? 'waitry',
                'placed_at' => $orden['placed_at'] ?? $orden['created_at'] ?? null,
                'waitry_cancelada' => WaitryOrdenEstadoSupport::esCancelada($orden),
                'waitry_anulada_descuento' => WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotal($orden),
                'total_neto_waitry' => WaitryOrdenEstadoSupport::montoNetoOperativo($orden),
                'empresa_id' => $empresaId,
                'anita_cuentacaja_id' => $anitaCuentacajaId,
                'anita_cuentacaja_label' => $anitaCuentacajaLabel,
                'anita_es_totem' => $anitaEsTotem,
                'waitry_tipo_pago_cuenta' => $emision?->cuenta?->waitry_tipo_pago,
            ];
        }

        usort($lineas, fn (array $a, array $b) => ($a['waitry_order_id'] ?? 0) <=> ($b['waitry_order_id'] ?? 0));

        return $lineas;
    }

    /**
     * @return array{cuentacaja_id:int,label:string}|null
     */
    private function primerMedioCobranzaEmision(VentaGastronomiaEmision $emision, int $empresaId): ?array
    {
        $venta = $emision->venta;
        if ($venta === null) {
            return null;
        }

        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        foreach (GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas) as $lineasMedio) {
            foreach ($lineasMedio as $medio) {
                $ccId = (int) ($medio->cuentacaja_id ?? 0);
                if ($ccId <= 0) {
                    continue;
                }
                $codigo = trim((string) ($medio->codigo ?? ''));
                $nombre = trim((string) ($medio->nombre ?? ''));
                $label = $codigo !== '' && $nombre !== ''
                    ? $codigo.' — '.$nombre
                    : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$ccId));

                return ['cuentacaja_id' => $ccId, 'label' => $label];
            }
        }

        return null;
    }

    private function formatearPlacedAt(mixed $placedAt): string
    {
        if ($placedAt === null || $placedAt === '') {
            return '';
        }

        try {
            return Carbon::parse((string) $placedAt)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $placedAt;
        }
    }

    /**
     * @param  array{cantidad:int,total:float}  $a
     * @param  array{cantidad:int,total:float}  $b
     * @return array{cantidad:int,total:float}
     */
    private static function fusionarResumenExcluidas(array $a, array $b): array
    {
        return [
            'cantidad' => (int) ($a['cantidad'] ?? 0) + (int) ($b['cantidad'] ?? 0),
            'total' => round((float) ($a['total'] ?? 0) + (float) ($b['total'] ?? 0), 2),
        ];
    }
}
