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
use App\Support\Ventas\Waitry\WaitryCierreJornadaDiscrepanciaSupport;
use App\Support\Ventas\Waitry\WaitryCierreJornadaVentanaSupport;
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
 */
final class GastronomiaCierreTotemJornadaService
{
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

    /** @deprecated Use ultimoWaitryOrderIdHasta() */
    public function ultimoTicketMovimientoIdHasta(int $empresaId): int
    {
        return $this->ultimoWaitryOrderIdHasta($empresaId);
    }

    /**
     * Registra el cierre de órdenes Waitry (tótem) al cerrar la jornada gastronómica.
     */
    public function registrarAlCerrarJornada(JornadaGastronomia $jornada): ?CierreTotemJornadaGastronomia
    {
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

        return CierreTotemJornadaGastronomia::query()->create([
            'jornada_gastronomia_id' => (int) $jornada->id,
            'empresa_id' => $empresaId,
            'waitry_order_id_anterior' => $idAnterior,
            'waitry_order_id_desde' => $desde,
            'waitry_order_id_hasta' => $hasta,
            'cantidad_lineas' => count($lineasCompletas),
            'total_monto' => $totalMonto,
            'cantidad_impagas_waitry' => $impagas,
            'cantidad_pagadas_waitry' => $pagadas,
            'cantidad_facturadas_erp' => $facturadas,
            'detalle_json' => [
                'lineas' => $lineasPdf,
                'resumen_totems' => $resumenTotems,
                'auditoria' => $auditoria,
            ],
            'detalle_truncado' => $truncado,
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

        $recuperacion = $this->recuperarHuecosSecuenciales($empresaId, $desdeExclusive, $porId);

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
                'ids_recuperados_gap' => $recuperacion['recuperados'],
                'ids_gap_sin_recuperar' => $recuperacion['sin_recuperar'],
            ],
        ];
    }

    /**
     * Busca hacia atrás IDs faltantes en la secuencia (desde último cierre + 1 hasta máximo visto).
     *
     * @param  array<int, array<string, mixed>>  $porId
     * @return array{recuperados: list<int>, sin_recuperar: list<int>}
     */
    private function recuperarHuecosSecuenciales(int $empresaId, int $desdeExclusive, array &$porId): array
    {
        $maxLimiteGap = max(0, (int) config('gastronomia.cierre_totem_jornada_max_ids_gap_recuperar', 250));
        if ($maxLimiteGap === 0 || $porId === []) {
            return ['recuperados' => [], 'sin_recuperar' => []];
        }

        $ids = array_keys($porId);
        $maxId = (int) max($ids);
        $minId = (int) min($ids);
        $inicio = max($desdeExclusive + 1, $minId);
        $faltantes = [];
        for ($id = $inicio; $id <= $maxId; $id++) {
            if (! isset($porId[$id])) {
                $faltantes[] = $id;
            }
        }

        if ($faltantes === []) {
            return ['recuperados' => [], 'sin_recuperar' => []];
        }

        $recuperados = [];
        $sinRecuperar = [];
        $intentos = 0;

        foreach ($faltantes as $orderId) {
            if ($intentos >= $maxLimiteGap) {
                $sinRecuperar = array_merge($sinRecuperar, array_slice($faltantes, $intentos));

                break;
            }
            $intentos++;

            $orden = $this->ordenesExternasService->obtenerOrdenPorIdConciliacion($empresaId, $orderId);
            if ($orden === null) {
                $sinRecuperar[] = $orderId;

                continue;
            }

            $porId[$orderId] = $this->analyticsOrdenesService->normalizarOrden($orden);
            $porId[$orderId]['fuente'] = 'getOrdersPOS_gap';
            $recuperados[] = $orderId;
        }

        return ['recuperados' => $recuperados, 'sin_recuperar' => $sinRecuperar];
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
            ->with(['venta:id,codigo,total', 'cuenta:id,waitry_cobro_totem,waitry_tipo_pago'])
            ->whereIn('waitry_order_id', $ids)
            ->whereHas('venta', fn ($q) => $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId)))
            ->orderByDesc('id')
            ->get()
            ->unique('waitry_order_id')
            ->keyBy(fn (VentaGastronomiaEmision $e) => (int) $e->waitry_order_id);

        $lineas = [];
        foreach ($ordenesPorId as $orderId => $orden) {
            if (! WaitryCierreJornadaVentanaSupport::ordenDentroVentanaOperativa($orden, $ventanaDesde, $ventanaHasta)) {
                continue;
            }

            $cuenta = $cuentasPorWaitry->get($orderId);
            $emision = $emisionesPorWaitry->get($orderId);

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
