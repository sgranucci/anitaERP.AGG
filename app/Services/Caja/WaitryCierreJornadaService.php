<?php

namespace App\Services\Caja;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaCierreTotemJornadaService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryAnalyticsOrdenesService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;
use App\Support\Ventas\Waitry\WaitryCierreJornadaVentanaSupport;
use App\Support\Ventas\Waitry\WaitryConciliacionCircuitoSupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaFacturadoAnitaSupport;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use App\Support\Ventas\Waitry\WaitryOrdenCobroSupport;
use App\Support\Ventas\Waitry\WaitryOrdenEstadoSupport;
use App\Support\Ventas\Waitry\WaitryOrdenPaymentEnriquecimientoSupport;
use App\Support\Ventas\Waitry\WaitryPaymentGatewaySupport;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Cierre de jornada Waitry (tesorería): concilia getordersdetails vs ventas Anita.
 *
 * Con jornada gastronomía abierta/cerrada: mismo tramo que Informe Z y proceso Caja
 * (order_id &gt; último cierre, placed_at entre apertura y cierre, API en días calendario que abarque la ventana).
 */
final class WaitryCierreJornadaService
{
    private const TOLERANCIA_MONTO = 0.02;

    public function __construct(
        private readonly WaitryAnalyticsOrdenesService $analyticsOrdenesService,
        private readonly WaitryOrdenesExternasService $ordenesExternasService,
        private readonly GastronomiaCierreTotemJornadaService $cierreTotemJornadaService,
    ) {
    }

    /**
     * @return array{
     *     ok:bool,
     *     error?:string,
     *     empresa_id?:int,
     *     fecha_jornada?:string,
     *     fecha_jornada_fmt?:string,
     *     jornada?:array<string,mixed>|null,
     *     resumen?:array<string,mixed>,
     *     filas?:list<array<string,mixed>>
     * }
     */
    public function conciliar(int $empresaId, string $fechaJornada): array
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe seleccionar una empresa.');
        }

        $fechaJornada = $this->normalizarFecha($fechaJornada);
        $fechaFmt = $this->formatearFecha($fechaJornada);

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->first();

        $usaTramoJornada = $jornada !== null
            && $this->cierreTotemJornadaService->habilitado();
        $idAnterior = 0;
        $ventanaOperativa = null;

        if ($usaTramoJornada) {
            $idAnterior = $this->cierreTotemJornadaService->waitryOrderIdAnteriorParaJornada($jornada);
            $cierreHasta = WaitryCierreJornadaVentanaSupport::resolverCierreHasta($jornada);
            $resuelto = WaitryCierreJornadaVentanaSupport::resolverParaCierreJornada(
                $fechaJornada,
                $jornada->apertura_en,
                $cierreHasta,
            );
            $fechaWaitryDesde = $resuelto['rango_calendario']['desde'];
            $fechaWaitryHasta = $resuelto['rango_calendario']['hasta'];
            $ventanaOperativa = $resuelto['ventana'];
        } else {
            $fechaWaitryDesde = $fechaJornada;
            $fechaWaitryHasta = Carbon::parse($fechaJornada)->addDay()->format('Y-m-d');
        }

        $fechaWaitryHastaFmt = $this->formatearFecha($fechaWaitryHasta);

        $cierreHasta = $usaTramoJornada && $jornada !== null
            ? WaitryCierreJornadaVentanaSupport::resolverCierreHasta($jornada)
            : $jornada?->cierre_en;

        $consultaWaitry = $this->obtenerOrdenesWaitryParaConciliacion(
            $empresaId,
            $fechaWaitryDesde,
            $fechaWaitryHasta,
            $fechaJornada,
            $jornada,
            $usaTramoJornada,
            $cierreHasta,
        );
        if (($consultaWaitry['error'] ?? '') !== '' && ($consultaWaitry['ordenes'] ?? []) === []) {
            return [
                'ok' => false,
                'error' => $consultaWaitry['error'],
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'fecha_jornada_fmt' => $fechaFmt,
                'jornada' => $this->jornadaResumen($jornada),
            ];
        }

        $waitryFuente = (string) ($consultaWaitry['fuente'] ?? 'getordersdetails');
        $ordenesWaitry = $consultaWaitry['ordenes'] ?? [];
        $mapWaitry = [];
        $descartadasFueraVentana = 0;
        $descartadasPorIdAnterior = 0;
        foreach ($ordenesWaitry as $orden) {
            $id = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if ($usaTramoJornada) {
                if (! WaitryCierreJornadaVentanaSupport::perteneceTramoOrderId($id, $idAnterior)) {
                    $descartadasPorIdAnterior++;

                    continue;
                }
                $normalizada = $this->analyticsOrdenesService->normalizarOrden($orden);
                if ($ventanaOperativa !== null
                    && ! WaitryCierreJornadaVentanaSupport::ordenDentroVentanaOperativa(
                        $normalizada,
                        $ventanaOperativa['desde'],
                        $ventanaOperativa['hasta'],
                    )) {
                    $descartadasFueraVentana++;

                    continue;
                }
                $mapWaitry[$id] = $normalizada;

                continue;
            }
            $mapWaitry[$id] = $orden;
        }

        $emisionesJornada = VentaGastronomiaEmision::query()
            ->with(['venta.cobranzasDirectas', 'venta.caja_movimientos.cobranzas', 'cuenta', 'waitryComandaEnvio'])
            ->whereHas('venta', function ($q) use ($empresaId, $fechaJornada) {
                $q->whereDate('fechajornada', $fechaJornada)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->get();

        $mapAnitaJornada = [];
        $emisionesJornadaSinWaitry = [];
        foreach ($emisionesJornada as $emision) {
            $wid = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
            if ($wid > 0) {
                if ($usaTramoJornada && ! WaitryCierreJornadaVentanaSupport::perteneceTramoOrderId($wid, $idAnterior)) {
                    continue;
                }
                $mapAnitaJornada[$wid] = $emision;
            } else {
                $emisionesJornadaSinWaitry[] = $emision;
            }
        }

        $waitryIdsConciliacion = array_values(array_unique(array_merge(
            array_keys($mapWaitry),
            array_keys($mapAnitaJornada),
        )));

        $enriquecido = WaitryOrdenPaymentEnriquecimientoSupport::enriquecerDesdePos(
            $this->ordenesExternasService,
            $empresaId,
            $mapWaitry,
            $fechaJornada,
            $jornada?->apertura_en,
            $jornada?->cierre_en,
        );
        $mapWaitry = $enriquecido['ordenes'];
        $mapPosCache = $enriquecido['map_pos'];

        $mapAnitaPorWaitry = $this->mapEmisionesPorWaitryIds($empresaId, $waitryIdsConciliacion);

        $mapCuentasImportadas = $this->mapCuentasImportadasPorWaitryIds($empresaId, $waitryIdsConciliacion);

        $waitryCanceladas = ['cantidad' => 0, 'total' => 0.0];
        $waitryAnuladasDescuento = ['cantidad' => 0, 'total' => 0.0];
        $mapWaitryActivas = [];
        foreach ($mapWaitry as $orderId => $orden) {
            if (WaitryOrdenEstadoSupport::esCancelada($orden)) {
                $waitryCanceladas['cantidad']++;
                $waitryCanceladas['total'] = round(
                    $waitryCanceladas['total'] + $this->montoTotalWaitry($orden),
                    2,
                );
                continue;
            }
            if (WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotal($orden)) {
                $waitryAnuladasDescuento['cantidad']++;
                $waitryAnuladasDescuento['total'] = round(
                    $waitryAnuladasDescuento['total'] + WaitryOrdenEstadoSupport::montoBrutoWaitry($orden),
                    2,
                );
                continue;
            }
            $mapWaitryActivas[$orderId] = $orden;
        }

        $cuentasPendientes = CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('waitry_order_id')
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->whereHas('lineas')
            ->get()
            ->keyBy(fn (CuentaGastronomia $c) => (int) $c->waitry_order_id);

        $filas = [];
        $idsProcesados = [];

        foreach ($mapWaitryActivas as $orderId => $orden) {
            $idsProcesados[] = $orderId;
            $emision = $mapAnitaPorWaitry[$orderId] ?? null;
            $cuentaPend = $cuentasPendientes->get($orderId);
            $cuentaImportada = $mapCuentasImportadas[$orderId] ?? null;
            $filas[] = $this->armarFila($orderId, $orden, $emision, $cuentaPend, $cuentaImportada, $fechaJornada, $empresaId);
        }

        $idsAnitaFueraListado = [];
        foreach ($mapAnitaJornada as $orderId => $emision) {
            if (! in_array($orderId, $idsProcesados, true)) {
                $idsAnitaFueraListado[] = $orderId;
            }
        }

        $mapPosConciliacion = $idsAnitaFueraListado !== []
            ? $this->resolverOrdenesPosFueraListado(
                $empresaId,
                $idsAnitaFueraListado,
                $fechaJornada,
                $jornada?->apertura_en,
                $jornada?->cierre_en,
                $mapPosCache,
            )
            : [];

        foreach ($mapAnitaJornada as $orderId => $emision) {
            if (in_array($orderId, $idsProcesados, true)) {
                continue;
            }
            $ordenPos = $mapPosConciliacion[$orderId] ?? null;
            if ($ordenPos !== null) {
                $ordenNorm = $this->analyticsOrdenesService->normalizarOrden($ordenPos);
                $fila = $this->armarFila($orderId, $ordenNorm, $emision, null, $mapCuentasImportadas[$orderId] ?? null, $fechaJornada, $empresaId);
                $fila['waitry_fuente_consulta'] = 'getOrdersPOS';
                $fila['waitry_en_listado_dia'] = false;
                $filas[] = $fila;
            } else {
                $filas[] = $this->armarFilaSoloAnita($orderId, $emision);
            }
        }

        foreach ($emisionesJornadaSinWaitry as $emision) {
            $filas[] = $this->armarFilaAnitaSinWaitry($emision);
        }

        usort($filas, static function (array $a, array $b): int {
            $ordA = (int) ($a['waitry_order_id'] ?? 0);
            $ordB = (int) ($b['waitry_order_id'] ?? 0);
            if ($ordA !== $ordB) {
                return $ordB <=> $ordA;
            }

            return ((int) ($b['anita_venta_id'] ?? 0)) <=> ((int) ($a['anita_venta_id'] ?? 0));
        });

        $filas = WaitryConciliacionCircuitoSupport::enriquecerFilas($filas);

        $resumen = $this->calcularResumen(
            $filas,
            $mapWaitryActivas,
            $emisionesJornada,
            $empresaId,
            $fechaJornada,
            $waitryCanceladas,
            $waitryAnuladasDescuento,
        );

        return [
            'ok' => true,
            'empresa_id' => $empresaId,
            'fecha_jornada' => $fechaJornada,
            'fecha_jornada_fmt' => $fechaFmt,
            'jornada' => $this->jornadaResumen($jornada),
            'meta_conciliacion' => $this->metaConciliacion(
                $fechaJornada,
                $fechaFmt,
                $fechaWaitryDesde,
                $fechaWaitryHasta,
                $fechaWaitryHastaFmt,
                $jornada,
                $usaTramoJornada,
                $idAnterior,
                $ventanaOperativa,
                $descartadasFueraVentana,
                $descartadasPorIdAnterior,
                $waitryFuente,
                $consultaWaitry['fallback_de'] ?? null,
            ),
            'resumen' => $resumen,
            'filas' => $filas,
        ];
    }

    /**
     * @param  array<string, mixed>  $orden
     * @return array<string, mixed>
     */
    private function armarFila(
        int $orderId,
        array $orden,
        ?VentaGastronomiaEmision $emision,
        ?CuentaGastronomia $cuentaPendiente,
        ?CuentaGastronomia $cuentaImportada,
        string $fechaJornadaConsulta,
        int $empresaId,
    ): array {
        $waitryPaid = $this->resolverPagadaWaitry($orden);
        $anitaTotal = $emision !== null ? round((float) ($emision->venta?->total ?? 0), 2) : null;
        $waitryTotal = $this->montoImporteWaitryConciliacion($orden, $anitaTotal);
        $diferencia = $anitaTotal !== null ? round($waitryTotal - $anitaTotal, 2) : null;
        $anitaFechajornada = $this->fechaJornadaVenta($emision);
        $jornadaCoincide = $anitaFechajornada === null || $anitaFechajornada === $fechaJornadaConsulta;

        $waitryTipoPago = WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($orden)
            ?? $emision?->cuenta?->waitry_tipo_pago
            ?? $cuentaPendiente?->waitry_tipo_pago;
        $waitryGateway = WaitryPaymentGatewaySupport::extraerGatewayDesdeOrden($orden);
        $referenciaWaitry = trim((string) (
            $orden['display_id']
            ?? $orden['externalDeliveryId']
            ?? $orden['external_reference_id']
            ?? ''
        ));
        $esOrdenPushErp = WaitryPaymentGatewaySupport::esOrdenPushErp([
            'display_id' => $referenciaWaitry,
            'external_reference_id' => $orden['external_reference_id'] ?? null,
            'referencia_waitry' => $referenciaWaitry,
            'waitry_tipo_pago' => $waitryTipoPago,
            'waitry_payment_gateway' => $waitryGateway,
            'payment' => $orden['payment'] ?? null,
            'facturada_erp' => $emision !== null,
            'anita_es_totem' => (bool) ($emision?->cuenta?->waitry_cobro_totem),
            'waitry_cobro_totem' => (bool) ($emision?->cuenta?->waitry_cobro_totem),
        ]);
        $cuentaEsperada = $esOrdenPushErp
            ? null
            : (WaitryMedioPagoCuentacajaSupport::cuentaParaTipoInformeZ($waitryTipoPago, $empresaId, $waitryGateway)
                ?? WaitryMedioPagoCuentacajaSupport::cuentaParaTipoWaitry($waitryTipoPago, $empresaId));
        $anitaMedio = $this->primerMedioCobranzaAnita($emision);

        $estado = 'sin_factura_anita';
        if ($emision !== null) {
            if (! $jornadaCoincide) {
                $estado = abs((float) $diferencia) > self::TOLERANCIA_MONTO ? 'jornada_distinta_monto' : 'jornada_distinta';
            } else {
                $estado = abs((float) $diferencia) > self::TOLERANCIA_MONTO ? 'monto_distinto' : 'conciliada';
            }
            if (! $esOrdenPushErp
                && $estado === 'conciliada'
                && $waitryPaid
                && $anitaMedio !== null
                && $cuentaEsperada !== null
                && (int) ($anitaMedio['cuentacaja_id'] ?? 0) !== (int) $cuentaEsperada['id']) {
                $estado = 'medio_distinto';
            }
        } elseif ($cuentaPendiente !== null) {
            $estado = 'importada_pendiente';
        }

        $metaEnvio = $emision !== null
            ? VentaGastronomiaEmisionWaitrySupport::metaEnvioComanda($emision)
            : ['estado' => null, 'ultimo_error' => null];

        $cuentaRef = $cuentaImportada ?? $cuentaPendiente ?? $emision?->cuenta;
        $importadaErp = $cuentaImportada !== null || $cuentaPendiente !== null;

        return [
            'waitry_order_id' => $orderId,
            'referencia_waitry' => trim((string) (
                $orden['display_id']
                ?? $orden['externalDeliveryId']
                ?? $orden['external_reference_id']
                ?? ''
            )),
            'hora_waitry' => $this->formatearHora($orden['placed_at'] ?? null),
            'fecha_hora_waitry' => $this->formatearFechaHora($orden['placed_at'] ?? null),
            'placed_at' => $orden['placed_at'] ?? null,
            'waitry_paid' => $waitryPaid,
            'waitry_total' => $waitryTotal,
            'waitry_tipo_pago' => $waitryTipoPago,
            'waitry_payment_gateway' => $waitryGateway,
            'waitry_medio_label' => WaitryMedioPagoCuentacajaSupport::etiquetaTipo($waitryTipoPago, $waitryGateway),
            'cuentacaja_esperada_id' => $cuentaEsperada['id'] ?? null,
            'cuentacaja_esperada_label' => isset($cuentaEsperada['codigo'], $cuentaEsperada['nombre'])
                ? trim($cuentaEsperada['codigo'].' — '.$cuentaEsperada['nombre'])
                : null,
            'anita_venta_id' => $emision?->venta_id,
            'anita_codigo' => $emision?->venta?->codigo,
            'anita_fechajornada' => $anitaFechajornada,
            'anita_fechajornada_fmt' => $anitaFechajornada ? $this->formatearFecha($anitaFechajornada) : null,
            'anita_total' => $anitaTotal,
            'anita_totem' => (bool) ($cuentaRef?->waitry_cobro_totem),
            'importada_erp' => $importadaErp,
            'cuenta_importada_id' => $cuentaImportada?->id,
            'anita_cuentacaja_id' => $anitaMedio['cuentacaja_id'] ?? null,
            'anita_cuentacaja_label' => $anitaMedio['label'] ?? null,
            'cuenta_pendiente_id' => $cuentaPendiente?->id,
            'waitry_comanda_estado' => $metaEnvio['estado'],
            'waitry_comanda_error' => $metaEnvio['ultimo_error'],
            'waitry_en_listado_dia' => true,
            'diferencia' => $diferencia,
            'estado' => $estado,
            'estado_label' => $this->etiquetaEstado($estado),
        ];
    }

    private function armarFilaSoloAnita(int $orderId, VentaGastronomiaEmision $emision): array
    {
        $anitaMedio = $this->primerMedioCobranzaAnita($emision);
        $metaEnvio = VentaGastronomiaEmisionWaitrySupport::metaEnvioComanda($emision);
        $referencia = trim((string) ($emision->cuenta?->waitry_display_id ?? ''));
        if ($referencia === '') {
            $referencia = '#'.$orderId;
        }

        return [
            'waitry_order_id' => $orderId,
            'referencia_waitry' => $referencia,
            'hora_waitry' => '',
            'fecha_hora_waitry' => '',
            'placed_at' => null,
            'waitry_paid' => null,
            'waitry_total' => null,
            'waitry_tipo_pago' => $emision->cuenta?->waitry_tipo_pago,
            'waitry_medio_label' => WaitryMedioPagoCuentacajaSupport::etiquetaTipo($emision->cuenta?->waitry_tipo_pago),
            'cuentacaja_esperada_id' => null,
            'cuentacaja_esperada_label' => null,
            'anita_venta_id' => $emision->venta_id,
            'anita_codigo' => $emision->venta?->codigo,
            'anita_total' => round((float) ($emision->venta?->total ?? 0), 2),
            'anita_totem' => (bool) ($emision->cuenta?->waitry_cobro_totem),
            'anita_cuentacaja_id' => $anitaMedio['cuentacaja_id'] ?? null,
            'anita_cuentacaja_label' => $anitaMedio['label'] ?? null,
            'cuenta_pendiente_id' => null,
            'waitry_comanda_estado' => $metaEnvio['estado'],
            'waitry_comanda_error' => $metaEnvio['ultimo_error'],
            'waitry_fuente_consulta' => 'anita_local',
            'waitry_en_listado_dia' => false,
            'diferencia' => null,
            'estado' => 'solo_anita',
            'estado_label' => $this->etiquetaEstado('solo_anita'),
        ];
    }

    private function armarFilaAnitaSinWaitry(VentaGastronomiaEmision $emision): array
    {
        $anitaMedio = $this->primerMedioCobranzaAnita($emision);
        $metaEnvio = VentaGastronomiaEmisionWaitrySupport::metaEnvioComanda($emision);

        return [
            'waitry_order_id' => null,
            'referencia_waitry' => '',
            'hora_waitry' => '',
            'fecha_hora_waitry' => '',
            'placed_at' => null,
            'waitry_paid' => null,
            'waitry_total' => null,
            'waitry_tipo_pago' => $emision->cuenta?->waitry_tipo_pago,
            'waitry_medio_label' => WaitryMedioPagoCuentacajaSupport::etiquetaTipo($emision->cuenta?->waitry_tipo_pago),
            'cuentacaja_esperada_id' => null,
            'cuentacaja_esperada_label' => null,
            'anita_venta_id' => $emision->venta_id,
            'anita_codigo' => $emision->venta?->codigo,
            'anita_total' => round((float) ($emision->venta?->total ?? 0), 2),
            'anita_totem' => (bool) ($emision->cuenta?->waitry_cobro_totem),
            'anita_cuentacaja_id' => $anitaMedio['cuentacaja_id'] ?? null,
            'anita_cuentacaja_label' => $anitaMedio['label'] ?? null,
            'cuenta_pendiente_id' => null,
            'waitry_comanda_estado' => $metaEnvio['estado'],
            'waitry_comanda_error' => $metaEnvio['ultimo_error'],
            'diferencia' => null,
            'estado' => 'anita_sin_waitry',
            'estado_label' => $this->etiquetaEstado('anita_sin_waitry'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<int, array<string, mixed>>  $mapWaitry
     * @param  \Illuminate\Support\Collection<int, VentaGastronomiaEmision>  $emisiones
     * @return array<string, mixed>
     */
    private function calcularResumen(
        array $filas,
        array $mapWaitry,
        $emisiones,
        int $empresaId,
        string $fechaJornada,
        array $waitryCanceladas = ['cantidad' => 0, 'total' => 0.0],
        array $waitryAnuladasDescuento = ['cantidad' => 0, 'total' => 0.0],
    ): array {
        $totalWaitry = 0.0;
        $totalWaitryPagado = 0.0;
        $totalesAnita = CierreJornadaFacturadoAnitaSupport::totalesJornadaEmpresa($empresaId, $fechaJornada);
        $totalAnita = $totalesAnita['total'];
        $conciliadas = 0;
        $sinFactura = 0;
        $importadasPendientes = 0;
        $montoDistinto = 0;
        $medioDistinto = 0;
        $soloAnita = 0;
        $anitaSinWaitry = 0;
        $jornadaDistinta = 0;
        $porMedioWaitry = [];

        $totalAnitaEnviadasWaitry = 0.0;
        $totalAnitaConImporteWaitryEnGrilla = 0.0;
        $totalWaitryEnFilasConAnita = 0.0;
        $totalAnitaSoloAnitaMonto = 0.0;

        foreach ($filas as $f) {
            match ($f['estado']) {
                'conciliada' => $conciliadas++,
                'sin_factura_anita' => $sinFactura++,
                'importada_pendiente' => $importadasPendientes++,
                'monto_distinto' => $montoDistinto++,
                'medio_distinto' => $medioDistinto++,
                'jornada_distinta', 'jornada_distinta_monto' => $jornadaDistinta++,
                'solo_anita' => $soloAnita++,
                'anita_sin_waitry' => $anitaSinWaitry++,
                default => null,
            };

            $orderId = (int) ($f['waitry_order_id'] ?? 0);
            $anitaTotal = $f['anita_total'] ?? null;
            $waitryTotal = $f['waitry_total'] ?? null;
            if ($anitaTotal === null || $orderId <= 0) {
                continue;
            }

            $anita = (float) $anitaTotal;
            $totalAnitaEnviadasWaitry = round($totalAnitaEnviadasWaitry + $anita, 2);
            if (($f['estado'] ?? '') === 'solo_anita') {
                $totalAnitaSoloAnitaMonto = round($totalAnitaSoloAnitaMonto + $anita, 2);
            }
            if ($waitryTotal !== null) {
                $totalAnitaConImporteWaitryEnGrilla = round($totalAnitaConImporteWaitryEnGrilla + $anita, 2);
                $totalWaitryEnFilasConAnita = round($totalWaitryEnFilasConAnita + (float) $waitryTotal, 2);
            }
        }

        foreach ($mapWaitry as $orden) {
            $monto = $this->montoTotalWaitry($orden);
            $totalWaitry += $monto;
            if ($this->esPagadaWaitry($orden)) {
                $totalWaitryPagado += $monto;
                $tipo = WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($orden)
                    ?? WaitryMedioPagoCuentacajaSupport::tipoPredefinidoFallbackInformeZ($empresaId);
                if ($tipo === null || WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipo)) {
                    continue;
                }
                if (! isset($porMedioWaitry[$tipo])) {
                    $cuenta = WaitryMedioPagoCuentacajaSupport::cuentaParaTipoInformeZ($tipo, $empresaId);
                    $porMedioWaitry[$tipo] = [
                        'tipo' => $tipo,
                        'etiqueta' => WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipo),
                        'cuentacaja_id' => $cuenta['id'] ?? null,
                        'cuentacaja_label' => isset($cuenta['codigo'], $cuenta['nombre'])
                            ? trim($cuenta['codigo'].' — '.$cuenta['nombre'])
                            : null,
                        'cantidad' => 0,
                        'total' => 0.0,
                    ];
                }
                $porMedioWaitry[$tipo]['cantidad']++;
                $porMedioWaitry[$tipo]['total'] = round($porMedioWaitry[$tipo]['total'] + $monto, 2);
            }
        }

        return [
            'ordenes_waitry' => count($mapWaitry),
            'waitry_canceladas_cantidad' => (int) ($waitryCanceladas['cantidad'] ?? 0),
            'waitry_canceladas_total' => round((float) ($waitryCanceladas['total'] ?? 0), 2),
            'waitry_anuladas_descuento_cantidad' => (int) ($waitryAnuladasDescuento['cantidad'] ?? 0),
            'waitry_anuladas_descuento_total' => round((float) ($waitryAnuladasDescuento['total'] ?? 0), 2),
            'facturas_anita_jornada' => $emisiones->count(),
            'facturas_anita_waitry' => $emisiones->filter(
                fn (VentaGastronomiaEmision $e) => VentaGastronomiaEmisionWaitrySupport::resolverOrderId($e) > 0,
            )->count(),
            'total_waitry' => round($totalWaitry, 2),
            'total_anita_enviadas_waitry' => $totalAnitaEnviadasWaitry,
            'total_anita_con_importe_waitry' => $totalAnitaConImporteWaitryEnGrilla,
            'total_anita_solo_anita_monto' => $totalAnitaSoloAnitaMonto,
            'diferencia_importes_pareados' => round($totalAnitaConImporteWaitryEnGrilla - $totalWaitryEnFilasConAnita, 2),
            'total_waitry_pagado' => round($totalWaitryPagado, 2),
            'total_anita_facturado' => round($totalAnita, 2),
            'total_anita_facturas' => $totalesAnita['total_facturas'],
            'total_anita_notas_credito' => $totalesAnita['total_notas_credito'],
            'diferencia_global' => round($totalWaitry - $totalAnita, 2),
            'conciliadas' => $conciliadas,
            'sin_factura_anita' => $sinFactura,
            'importadas_pendientes' => $importadasPendientes,
            'monto_distinto' => $montoDistinto,
            'medio_distinto' => $medioDistinto,
            'solo_anita' => $soloAnita,
            'anita_sin_waitry' => $anitaSinWaitry,
            'jornada_distinta' => $jornadaDistinta,
            'por_medio_waitry' => array_values($porMedioWaitry),
            'tiene_diferencias' => ($sinFactura + $importadasPendientes + $montoDistinto + $medioDistinto + $soloAnita + $anitaSinWaitry + $jornadaDistinta) > 0,
            'circuitos' => WaitryConciliacionCircuitoSupport::resumenPorCircuito($filas),
        ];
    }

    /**
     * @param  list<int>  $waitryIds
     * @return array<int, VentaGastronomiaEmision>
     */
    private function mapEmisionesPorWaitryIds(int $empresaId, array $waitryIds): array
    {
        $waitryIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $waitryIds),
            static fn (int $id) => $id > 0,
        )));

        if ($waitryIds === []) {
            return [];
        }

        $emisiones = VentaGastronomiaEmision::query()
            ->with(['venta.cobranzasDirectas', 'venta.caja_movimientos.cobranzas', 'cuenta', 'waitryComandaEnvio'])
            ->whereHas('venta', fn ($q) => $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId)))
            ->where(function ($q) use ($waitryIds) {
                $q->whereIn('waitry_order_id', $waitryIds)
                    ->orWhereHas('cuenta', fn ($c) => $c->whereIn('waitry_order_id', $waitryIds))
                    ->orWhereHas('waitryComandaEnvio', fn ($w) => $w->whereIn('waitry_order_id', $waitryIds));
            })
            ->orderByDesc('venta_id')
            ->get();

        $map = [];
        foreach ($emisiones as $emision) {
            $wid = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
            if ($wid > 0 && in_array($wid, $waitryIds, true) && ! isset($map[$wid])) {
                $map[$wid] = $emision;
            }
        }

        return $map;
    }

    /**
     * @param  list<int>  $orderIds
     * @param  array<int, array<string, mixed>>  $mapPosCache
     * @return array<int, array<string, mixed>>
     */
    private function resolverOrdenesPosFueraListado(
        int $empresaId,
        array $orderIds,
        string $fechaJornada,
        mixed $aperturaEn,
        mixed $cierreEn,
        array $mapPosCache,
    ): array {
        $desdeCache = WaitryOrdenPaymentEnriquecimientoSupport::filtrarMapPosPorIds($mapPosCache, $orderIds);
        if (count($desdeCache) === count($orderIds)) {
            return $desdeCache;
        }

        $faltantes = array_values(array_diff($orderIds, array_keys($desdeCache)));
        if ($faltantes === []) {
            return $desdeCache;
        }

        return $desdeCache + $this->ordenesExternasService->mapOrdenesPorIdsConciliacion(
            $empresaId,
            $faltantes,
            $fechaJornada,
            $aperturaEn,
            $cierreEn,
        );
    }

    /**
     * @return array{cuentacaja_id:int,label:string}|null
     */
    private function primerMedioCobranzaAnita(?VentaGastronomiaEmision $emision): ?array
    {
        $venta = $emision?->venta;
        if ($venta === null) {
            return null;
        }

        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        $linea = null;
        foreach ($medios as $lineas) {
            foreach ($lineas as $medio) {
                if ((int) ($medio->cuentacaja_id ?? 0) > 0) {
                    $linea = $medio;
                    break 2;
                }
            }
        }
        if ($linea === null) {
            return null;
        }

        $ccId = (int) ($linea->cuentacaja_id ?? 0);
        if ($ccId <= 0) {
            return null;
        }

        $codigo = trim((string) ($linea->codigo ?? $linea->cuenta ?? ''));
        $nombre = trim((string) ($linea->nombre ?? ''));
        $label = $codigo !== '' && $nombre !== ''
            ? $codigo.' — '.$nombre
            : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$ccId));

        return [
            'cuentacaja_id' => $ccId,
            'label' => $label,
        ];
    }

    private function fechaJornadaVenta(?VentaGastronomiaEmision $emision): ?string
    {
        $fecha = $emision?->venta?->fechajornada;
        if ($fecha === null || $fecha === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $fecha)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<int>  $waitryIds
     * @return array<int, CuentaGastronomia>
     */
    private function mapCuentasImportadasPorWaitryIds(int $empresaId, array $waitryIds): array
    {
        $waitryIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $waitryIds),
            static fn (int $id) => $id > 0,
        )));

        if ($waitryIds === []) {
            return [];
        }

        $map = [];
        CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('waitry_order_id', $waitryIds)
            ->orderByDesc('id')
            ->get()
            ->each(function (CuentaGastronomia $cuenta) use (&$map): void {
                $orderId = (int) $cuenta->waitry_order_id;
                if ($orderId > 0 && ! isset($map[$orderId])) {
                    $map[$orderId] = $cuenta;
                }
            });

        return $map;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private function resolverPagadaWaitry(array $orden): ?bool
    {
        return WaitryOrdenCobroSupport::resolverEstadoPagoWaitry($orden);
    }

    /**
     * Importe Waitry para conciliar: bruto de la comanda; si falta en POS, total Anita pareado.
     *
     * @param  array<string, mixed>  $orden
     */
    private function montoImporteWaitryConciliacion(array $orden, ?float $anitaTotal): float
    {
        $bruto = WaitryOrdenEstadoSupport::montoBrutoWaitry($orden);
        if ($bruto > 0.0001) {
            return $bruto;
        }

        $cobro = WaitryOrdenCobroSupport::montoCobro($orden);
        if ($cobro > 0.0001) {
            return $cobro;
        }

        if ($anitaTotal !== null && $anitaTotal > 0.0001) {
            return round($anitaTotal, 2);
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    private function esPagadaWaitry(array $orden): bool
    {
        return WaitryOrdenCobroSupport::resolverEstadoPagoWaitry($orden) === true;
    }

    /**
     * Monto Waitry: getordersdetails (`totalAmount`) o getOrdersPOS (`payment.total_fee`).
     *
     * @param  array<string, mixed>  $orden
     */
    private function montoTotalWaitry(array $orden): float
    {
        return $this->montoImporteWaitryConciliacion($orden, null);
    }

    private function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'conciliada' => 'Conciliada',
            'sin_factura_anita' => 'Waitry sin factura Anita',
            'importada_pendiente' => 'Importada, pendiente de facturar',
            'monto_distinto' => 'Diferencia de monto',
            'medio_distinto' => 'Medio de pago distinto',
            'jornada_distinta' => 'Facturada Anita (otra jornada)',
            'jornada_distinta_monto' => 'Facturada Anita (otra jornada) + dif. monto',
            'solo_anita' => 'Facturada Anita — Waitry no listado en el día (consultar POS/KDS)',
            'anita_sin_waitry' => 'Facturada Anita sin orden Waitry (revisar KDS)',
            default => $estado,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jornadaResumen(?JornadaGastronomia $jornada): ?array
    {
        if ($jornada === null) {
            return null;
        }

        return [
            'id' => (int) $jornada->id,
            'estado' => (string) $jornada->estado,
            'fecha_jornada' => $jornada->fecha_jornada?->format('Y-m-d'),
            'apertura_en' => $jornada->apertura_en?->format('d/m/Y H:i'),
            'cierre_en' => $jornada->cierre_en?->format('d/m/Y H:i'),
        ];
    }

    private function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            throw new InvalidArgumentException('Debe indicar la fecha de jornada.');
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable) {
            throw new InvalidArgumentException('Fecha de jornada inválida.');
        }
    }

    private function formatearFecha(string $fechaYmd): string
    {
        try {
            return Carbon::parse($fechaYmd)->format('d/m/Y');
        } catch (\Throwable) {
            return $fechaYmd;
        }
    }

    private function formatearHora(mixed $placed): string
    {
        if ($placed === null || $placed === '') {
            return '';
        }

        try {
            return Carbon::parse((string) $placed)->format('H:i');
        } catch (\Throwable) {
            return (string) $placed;
        }
    }

    private function formatearFechaHora(mixed $placed): string
    {
        if ($placed === null || $placed === '') {
            return '';
        }

        try {
            return Carbon::parse((string) $placed)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $placed;
        }
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array{desde:Carbon,hasta:Carbon,etiqueta:string}|null  $ventanaOperativa
     */
    private function metaConciliacion(
        string $fechaJornada,
        string $fechaFmt,
        string $fechaWaitryDesde,
        string $fechaWaitryHasta,
        string $fechaWaitryHastaFmt,
        ?JornadaGastronomia $jornada,
        bool $usaTramoJornada,
        int $idAnterior,
        ?array $ventanaOperativa,
        int $descartadasFueraVentana,
        int $descartadasPorIdAnterior,
        string $waitryFuente = 'getordersdetails',
        ?string $waitryFallbackDe = null,
    ): array {
        $meta = [
            'waitry_api' => $waitryFuente,
            'waitry_desde' => $fechaWaitryDesde,
            'waitry_hasta' => $fechaWaitryHasta,
            'waitry_rango_etiqueta' => 'Calendario Waitry API: '.$this->formatearFecha($fechaWaitryDesde)
                .' — '.$fechaWaitryHastaFmt,
            'anita_criterio' => 'venta.fechajornada = '.$fechaFmt.' (emisiones gastronomía; orderId en emisión, cuenta o KDS)',
        ];
        if ($waitryFallbackDe !== null && $waitryFallbackDe !== '') {
            $meta['waitry_fallback_de'] = $waitryFallbackDe;
        }

        if ($usaTramoJornada && $ventanaOperativa !== null) {
            $meta['tramo_order_id_desde'] = $idAnterior + 1;
            $meta['tramo_order_id_anterior'] = $idAnterior;
            $meta['ventana_operativa'] = $ventanaOperativa['etiqueta'];
            $meta['waitry_criterio_tramo'] = 'order_id > #'.$idAnterior
                .' y placed_at dentro de ventana operativa (mismo criterio que Informe Z y proceso Caja)';
            $meta['descartadas_fuera_ventana'] = $descartadasFueraVentana;
            $meta['descartadas_por_id_anterior'] = $descartadasPorIdAnterior;
        } elseif ($jornada !== null) {
            $meta['ventana_jornada_etiqueta'] = $jornada->apertura_en?->format('d/m/Y H:i')
                .' — '.($jornada->cierre_en?->format('d/m/Y H:i') ?? 'abierta');
        }

        return $meta;
    }

    /**
     * getordersdetails primero; si falla o viene vacío, getOrdersPOS en ventana de jornada.
     *
     * @return array{
     *   ordenes: list<array<string, mixed>>,
     *   fuente: string,
     *   error?: string,
     *   fallback_de?: string
     * }
     */
    private function obtenerOrdenesWaitryParaConciliacion(
        int $empresaId,
        string $fechaWaitryDesde,
        string $fechaWaitryHasta,
        string $fechaJornada,
        ?JornadaGastronomia $jornada,
        bool $usaTramoJornada,
        mixed $cierreHasta = null,
    ): array {
        $waitry = $this->analyticsOrdenesService->ordenesPorRangoFecha(
            $empresaId,
            $fechaWaitryDesde,
            $fechaWaitryHasta,
        );

        if (($waitry['ok'] ?? false) && ($waitry['ordenes'] ?? []) !== []) {
            return [
                'ordenes' => $waitry['ordenes'],
                'fuente' => 'getordersdetails',
            ];
        }

        $mapPos = $this->ordenesExternasService->mapOrdenesPosEnVentanaJornada(
            $empresaId,
            $fechaJornada,
            $jornada?->apertura_en,
            $cierreHasta,
        );

        if ($mapPos !== []) {
            return [
                'ordenes' => array_values($mapPos),
                'fuente' => 'getOrdersPOS',
                'fallback_de' => ($waitry['ok'] ?? false)
                    ? 'getordersdetails_vacio'
                    : (string) ($waitry['error'] ?? 'getordersdetails_error'),
            ];
        }

        return [
            'ordenes' => [],
            'fuente' => 'getordersdetails',
            'error' => $waitry['error'] ?? 'No se pudieron obtener órdenes Waitry.',
        ];
    }
}
