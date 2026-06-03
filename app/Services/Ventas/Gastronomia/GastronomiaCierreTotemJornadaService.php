<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TotemWaitryGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\Waitry\WaitryAnalyticsOrdenesService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\Waitry\WaitryCierreJornadaDiscrepanciaSupport;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use App\Support\Ventas\Waitry\WaitryCierreJornadaVentanaSupport;
use App\Support\Ventas\Waitry\WaitryInformeZConciliacionSupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use App\Support\Ventas\Waitry\WaitryOrdenCobroSupport;
use App\Support\Ventas\Waitry\WaitryTotemJornadaResumenSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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
    public function __construct(
        private readonly WaitryAnalyticsOrdenesService $analyticsOrdenesService,
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
        if (! $this->habilitado()) {
            throw new InvalidArgumentException('Cierre tótem Waitry no habilitado.');
        }

        $empresaId = (int) $jornada->empresa_id;
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa inválida.');
        }

        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $idAnterior = $this->ultimoWaitryOrderIdHasta($empresaId);
        $hasta = $cierreHasta ?? $jornada->cierre_en ?? now();

        $listado = $this->listarOrdenesWaitryNuevas(
            $empresaId,
            $fechaJornada,
            $idAnterior,
            $jornada->apertura_en,
            $hasta,
        );

        $ventana = $listado['ventana'];
        $lineasCompletas = $this->armarLineasConEstadoErp(
            $empresaId,
            $listado['ordenes'],
            $ventana['desde'] ?? null,
            $ventana['hasta'] ?? null,
        );
        $lineasCompletas = array_merge(
            $lineasCompletas,
            $this->armarLineasHuecosPendientesAuditoria($listado['auditoria']['ids_huecos_secuencia'] ?? []),
        );

        $ids = array_keys($listado['ordenes']);
        sort($ids, SORT_NUMERIC);
        $desde = $ids !== [] ? (int) min($ids) : null;
        $hastaId = $ids !== [] ? (int) max($ids) : $idAnterior;

        return [
            'lineas' => $lineasCompletas,
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
                'cantidad_movimientos' => count($lineasCompletas),
                'auditoria' => $listado['auditoria'],
            ],
        ];
    }

    /**
     * Totales Waitry que se incluirían al cerrar la jornada abierta (sin persistir).
     * Usa la fecha/hora actual como cierre hipotético.
     *
     * @return array<string, mixed>|null
     */
    public function previewParaJornadaAbierta(JornadaGastronomia $jornada): ?array
    {
        if (! $this->habilitado()) {
            return null;
        }

        $empresaId = (int) $jornada->empresa_id;
        if ($empresaId <= 0) {
            return null;
        }

        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $idAnterior = $this->ultimoWaitryOrderIdHasta($empresaId);

        $listado = $this->listarOrdenesWaitryNuevas(
            $empresaId,
            $fechaJornada,
            $idAnterior,
            $jornada->apertura_en,
            now(),
        );

        $ventana = $listado['ventana'];
        $lineasCompletas = $this->armarLineasConEstadoErp(
            $empresaId,
            $listado['ordenes'],
            $ventana['desde'] ?? null,
            $ventana['hasta'] ?? null,
        );
        $lineasCompletas = array_merge(
            $lineasCompletas,
            $this->armarLineasHuecosPendientesAuditoria($listado['auditoria']['ids_huecos_secuencia'] ?? []),
        );
        $lineasDiscrepancias = WaitryCierreJornadaDiscrepanciaSupport::filtrar($lineasCompletas);

        $totems = TotemWaitryGastronomia::query()
            ->with('ubicacion')
            ->where('empresa_id', $empresaId)
            ->orderBy('ubicacion_id')
            ->get();
        $resumenTotems = WaitryTotemJornadaResumenSupport::armar($totems, $lineasCompletas);

        $ids = array_keys($listado['ordenes']);
        sort($ids, SORT_NUMERIC);
        $desde = $ids !== [] ? (int) min($ids) : null;
        $hasta = $ids !== [] ? (int) max($ids) : $idAnterior;

        $totalGeneral = $resumenTotems['total_general'] ?? [
            'cantidad_ordenes' => 0,
            'total_ingreso' => 0.0,
            'por_medio_pago' => [],
        ];

        $plantilla = WaitryInformeZConciliacionSupport::plantillaCarga($empresaId, $resumenTotems);
        $borrador = is_array($jornada->informe_z_borrador_json) ? $jornada->informe_z_borrador_json : null;
        $plantilla = WaitryInformeZConciliacionSupport::fusionarInformeZEnPlantilla($plantilla, $borrador);
        $conciliacion = $borrador !== null
            ? WaitryInformeZConciliacionSupport::conciliar($plantilla)
            : null;

        $snapshotCierre = $this->armarSnapshotCierre(
            $idAnterior,
            $desde,
            $hasta,
            $resumenTotems,
            $listado['auditoria'],
            (string) ($ventana['etiqueta'] ?? ''),
        );

        return [
            'jornada_id' => (int) $jornada->id,
            'fecha_jornada' => $jornada->fecha_jornada?->format('d/m/Y') ?? '',
            'ventana_operativa' => $ventana['etiqueta'] ?? '',
            'consulta_waitry_rango' => $listado['auditoria']['consulta_waitry_rango'] ?? '',
            'waitry_order_id_anterior' => $idAnterior,
            'waitry_order_id_desde' => $desde,
            'waitry_order_id_hasta' => $hasta,
            'rango_etiqueta' => $this->etiquetaRango($idAnterior, $desde, $hasta),
            'cantidad_ordenes' => count($lineasCompletas),
            'cantidad_discrepancias' => count($lineasDiscrepancias),
            'cantidad_huecos_secuencia' => count($listado['auditoria']['ids_huecos_secuencia'] ?? []),
            'hay_discrepancias' => count($lineasDiscrepancias) > 0,
            'por_totem' => $resumenTotems['por_totem'] ?? [],
            'total_general' => $totalGeneral,
            'total_ingreso_totem' => (float) ($totalGeneral['total_ingreso'] ?? 0),
            'cantidad_ingreso_totem' => (int) ($totalGeneral['cantidad_ordenes'] ?? 0),
            'totems' => $plantilla,
            'conciliacion' => $conciliacion,
            'informe_z_cargado' => $borrador !== null,
            'informe_z_en' => $borrador['informe_z_en'] ?? null,
            'usuario_informe_z' => $borrador['usuario_nombre'] ?? null,
            'tolerancia' => WaitryInformeZConciliacionSupport::toleranciaMonto(),
            'preview_en' => now()->format('d/m/Y H:i'),
            'snapshot_cierre' => $snapshotCierre,
        ];
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
        if ($snapshot !== null) {
            return $this->registrarAlCerrarJornadaDesdeSnapshot($jornada, $snapshot, $informeZBorrador);
        }

        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $idAnterior = $this->ultimoWaitryOrderIdHasta($empresaId);

        $listado = $this->listarOrdenesWaitryNuevas(
            $empresaId,
            $fechaJornada,
            $idAnterior,
            $jornada->apertura_en,
            $jornada->cierre_en,
        );
        $ordenesPorId = $listado['ordenes'];
        $auditoria = $listado['auditoria'];
        $ventana = $listado['ventana'];

        $lineasCompletas = $this->armarLineasConEstadoErp(
            $empresaId,
            $ordenesPorId,
            $ventana['desde'] ?? null,
            $ventana['hasta'] ?? null,
        );
        $lineasCompletas = array_merge(
            $lineasCompletas,
            $this->armarLineasHuecosPendientesAuditoria($auditoria['ids_huecos_secuencia'] ?? []),
        );
        $lineasDiscrepancias = WaitryCierreJornadaDiscrepanciaSupport::filtrar($lineasCompletas);
        $auditoria['cantidad_discrepancias'] = count($lineasDiscrepancias);
        $auditoria['ventana_operativa'] = $ventana['etiqueta'] ?? '';

        $totems = TotemWaitryGastronomia::query()
            ->with('ubicacion')
            ->where('empresa_id', $empresaId)
            ->orderBy('ubicacion_id')
            ->get();
        $resumenTotems = WaitryTotemJornadaResumenSupport::armar($totems, $lineasCompletas);

        $ids = array_keys($ordenesPorId);
        sort($ids, SORT_NUMERIC);

        $desde = $ids !== [] ? (int) min($ids) : null;
        $hasta = $ids !== [] ? (int) max($ids) : $idAnterior;

        $totalMonto = round(array_sum(array_map(
            fn (array $l) => (float) ($l['total'] ?? 0),
            $lineasCompletas,
        )), 2);

        $impagas = 0;
        $pagadas = 0;
        $facturadas = 0;
        foreach ($lineasCompletas as $l) {
            if (! empty($l['facturada_erp'])) {
                $facturadas++;
            }
            if ($l['paid_waitry'] === true || ! empty($l['waitry_cobro_totem'])) {
                $pagadas++;
            } elseif ($l['paid_waitry'] === false) {
                $impagas++;
            }
        }

        $limiteDetalle = max(100, (int) config('gastronomia.cierre_totem_jornada_max_lineas_detalle', 3000));
        $truncado = count($lineasDiscrepancias) > $limiteDetalle;
        $lineasPdf = $truncado
            ? array_slice($lineasDiscrepancias, 0, $limiteDetalle)
            : $lineasDiscrepancias;

        $informeZJson = $this->informeZJsonParaPersistir($informeZBorrador);

        return $this->persistirRegistroCierreTotem(
            $jornada,
            $empresaId,
            $idAnterior,
            $desde,
            $hasta,
            count($lineasCompletas),
            $totalMonto,
            $impagas,
            $pagadas,
            $facturadas,
            [
                'lineas' => $lineasPdf,
                'resumen_totems' => $resumenTotems,
                'auditoria' => $auditoria,
            ],
            $informeZJson,
            $truncado,
        );
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
        $totalGeneral = is_array($resumenTotems['total_general'] ?? null) ? $resumenTotems['total_general'] : [];
        $idAnterior = (int) ($snapshot['waitry_order_id_anterior'] ?? $this->ultimoWaitryOrderIdHasta($empresaId));
        $desde = isset($snapshot['waitry_order_id_desde']) ? (int) $snapshot['waitry_order_id_desde'] : null;
        $hasta = (int) ($snapshot['waitry_order_id_hasta'] ?? $idAnterior);
        $auditoria = is_array($snapshot['auditoria'] ?? null) ? $snapshot['auditoria'] : [];
        $auditoria['registro_desde_snapshot'] = true;
        $auditoria['ventana_operativa'] = $snapshot['ventana_operativa'] ?? ($auditoria['ventana_operativa'] ?? '');
        $auditoria['snapshot_preview_en'] = $snapshot['preview_en'] ?? null;

        $informeZJson = $this->informeZJsonParaPersistir($informeZBorrador);

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
            [
                'lineas' => [],
                'resumen_totems' => $resumenTotems,
                'auditoria' => $auditoria,
            ],
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
    ): array {
        return [
            'waitry_order_id_anterior' => $idAnterior,
            'waitry_order_id_desde' => $desde,
            'waitry_order_id_hasta' => $hasta,
            'resumen_totems' => $resumenTotems,
            'auditoria' => $auditoria,
            'ventana_operativa' => $ventanaOperativa,
            'preview_en' => now()->format('Y-m-d H:i:s'),
        ];
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
            $resumenTotems = WaitryTotemJornadaResumenSupport::armar($totems, $lineas);
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
            'conciliacion_informe_z' => is_array($cierre->informe_z_json['conciliacion'] ?? null)
                ? $cierre->informe_z_json['conciliacion']
                : null,
            'informe_z_cargado' => is_array($cierre->informe_z_json) && isset($cierre->informe_z_json['totems']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array{tipo:?string,etiqueta:string,cantidad:int,total:float,cuentacaja_label:?string}>
     */
    private function resumenPorMedioWaitry(array $lineas): array
    {
        $map = [];
        foreach ($lineas as $ln) {
            if (! WaitryTotemJornadaResumenSupport::lineaCuentaParaIngresoTotem($ln)) {
                continue;
            }
            $monto = (float) ($ln['monto_cobro_waitry'] ?? 0) > 0.0001
                ? (float) $ln['monto_cobro_waitry']
                : (float) ($ln['total'] ?? 0);
            $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($ln['waitry_tipo_pago'] ?? null) ?? 'totem';
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
        if (! ($waitry['ok'] ?? false)) {
            throw new RuntimeException(
                $waitry['error'] ?? 'No se pudieron obtener órdenes Waitry (getordersdetails) para el cierre de jornada.'
            );
        }

        $porId = [];
        $descartadasFueraVentana = 0;
        foreach ($waitry['ordenes'] ?? [] as $orden) {
            if (! is_array($orden)) {
                continue;
            }
            $id = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);
            if ($id <= $desdeExclusive) {
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
            $porId[$id]['fuente'] = 'getordersdetails';
        }

        $idsErpSuplemento = $this->suplementarOrdenesDesdeErp(
            $empresaId,
            $fechaJornada,
            $desdeExclusive,
            $porId,
            $ventana['desde'] ?? null,
            $ventana['hasta'] ?? null,
        );

        $idsHuecos = $this->detectarHuecosSecuenciales($desdeExclusive, $porId);

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
                'cantidad_getordersdetails' => count($waitry['ordenes'] ?? []),
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
     * IDs Waitry ausentes entre el último cierre y el máximo visto en getordersdetails.
     * No consulta Waitry: quedan como discrepancia para auditoría del día (proceso posterior).
     *
     * @param  array<int, array<string, mixed>>  $porId
     * @return list<int>
     */
    private function detectarHuecosSecuenciales(int $desdeExclusive, array $porId): array
    {
        if ($porId === []) {
            return [];
        }

        $ids = array_keys($porId);
        $maxId = (int) max($ids);
        $minId = (int) min($ids);
        $inicio = max($desdeExclusive + 1, $minId);
        $huecos = [];
        for ($id = $inicio; $id <= $maxId; $id++) {
            if (! isset($porId[$id])) {
                $huecos[] = $id;
            }
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
        $idsErp = [];

        CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('waitry_order_id')
            ->where('waitry_order_id', '>', $desdeExclusive)
            ->pluck('waitry_order_id')
            ->each(function ($id) use (&$idsErp) {
                $idsErp[(int) $id] = true;
            });

        VentaGastronomiaEmision::query()
            ->whereNotNull('waitry_order_id')
            ->where('waitry_order_id', '>', $desdeExclusive)
            ->whereHas('venta', function ($q) use ($empresaId, $fechaJornada, $ventanaDesde, $ventanaHasta) {
                $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
                $q->where(function ($fecha) use ($fechaJornada, $ventanaDesde, $ventanaHasta) {
                    $fecha->whereDate('fechajornada', $fechaJornada);
                    if ($ventanaDesde !== null && $ventanaHasta !== null) {
                        $fecha->orWhereBetween('created_at', [
                            $ventanaDesde->format('Y-m-d H:i:s'),
                            $ventanaHasta->format('Y-m-d H:i:s'),
                        ]);
                    }
                });
            })
            ->pluck('waitry_order_id')
            ->each(function ($id) use (&$idsErp) {
                $idsErp[(int) $id] = true;
            });

        $agregados = [];
        foreach (array_keys($idsErp) as $orderId) {
            if (isset($porId[$orderId])) {
                continue;
            }
            $porId[$orderId] = [
                'id' => $orderId,
                'orderId' => $orderId,
                'display_id' => null,
                'placed_at' => null,
                'totalAmount' => 0.0,
                'paid' => null,
                'fuente' => 'erp',
            ];
            $agregados[] = $orderId;
        }

        return $agregados;
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

        $emisionesPorWaitry = VentaGastronomiaEmision::query()
            ->with(['venta:id,codigo,total', 'cuenta:id,waitry_cobro_totem,waitry_tipo_pago,waitry_order_id', 'waitryComandaEnvio'])
            ->whereHas('venta', fn ($q) => $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId)))
            ->orderByDesc('venta_id')
            ->get();

        $mapEmisionPorWaitryId = [];
        foreach ($emisionesPorWaitry as $emision) {
            $wid = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
            if ($wid > 0 && in_array($wid, $ids, true) && ! isset($mapEmisionPorWaitryId[$wid])) {
                $mapEmisionPorWaitryId[$wid] = $emision;
            }
        }

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
            $waitryTipoPago = WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($orden)
                ?? $cuenta?->waitry_tipo_pago
                ?? $emision?->cuenta?->waitry_tipo_pago;
            $cuentaEsperada = WaitryMedioPagoCuentacajaSupport::cuentaParaTipoWaitry($waitryTipoPago, $empresaId);
            $waitryTableId = WaitryOrdenCobroSupport::extraerTableId($orden);

            $lineas[] = [
                'waitry_order_id' => $orderId,
                'display_id' => trim((string) (
                    $orden['display_id']
                    ?? $orden['externalDeliveryId']
                    ?? $orden['external_reference_id']
                    ?? $cuenta?->waitry_display_id
                    ?? ''
                )),
                'placed_at_fmt' => $this->formatearPlacedAt($orden['placed_at'] ?? null),
                'total' => $total,
                'monto_cobro_waitry' => $montoCobro,
                'waitry_table_id' => $waitryTableId,
                'paid_waitry' => $paid,
                'waitry_tipo_pago' => $waitryTipoPago,
                'waitry_medio_label' => WaitryMedioPagoCuentacajaSupport::etiquetaTipo($waitryTipoPago),
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
            ];
        }

        usort($lineas, fn (array $a, array $b) => ($a['waitry_order_id'] ?? 0) <=> ($b['waitry_order_id'] ?? 0));

        return $lineas;
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
}
