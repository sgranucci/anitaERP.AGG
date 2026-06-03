<?php

namespace App\Services\Caja;

use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\Waitry\WaitryAnalyticsOrdenesService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaFacturadoAnitaSupport;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use App\Support\Ventas\Waitry\WaitryOrdenCobroSupport;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Cierre de jornada Waitry (tesorería): concilia getordersdetails vs ventas Anita por fechajornada.
 */
final class WaitryCierreJornadaService
{
    private const TOLERANCIA_MONTO = 0.02;

    public function __construct(
        private readonly WaitryAnalyticsOrdenesService $analyticsOrdenesService,
        private readonly WaitryOrdenesExternasService $ordenesExternasService,
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
        $fechaWaitryHasta = Carbon::parse($fechaJornada)->addDay()->format('Y-m-d');
        $fechaWaitryHastaFmt = $this->formatearFecha($fechaWaitryHasta);

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->first();

        $waitry = $this->analyticsOrdenesService->ordenesPorRangoFecha($empresaId, $fechaJornada, $fechaWaitryHasta);
        if (! ($waitry['ok'] ?? false)) {
            return [
                'ok' => false,
                'error' => $waitry['error'] ?? 'No se pudieron obtener órdenes Waitry.',
                'empresa_id' => $empresaId,
                'fecha_jornada' => $fechaJornada,
                'fecha_jornada_fmt' => $fechaFmt,
                'jornada' => $this->jornadaResumen($jornada),
            ];
        }

        $ordenesWaitry = $waitry['ordenes'] ?? [];
        $mapWaitry = [];
        foreach ($ordenesWaitry as $orden) {
            $id = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);
            if ($id > 0) {
                $mapWaitry[$id] = $orden;
            }
        }

        $emisionesJornada = VentaGastronomiaEmision::query()
            ->with(['venta.cobranzasDirectas', 'venta.caja_movimientos.cobranzas', 'cuenta', 'waitryComandaEnvio'])
            ->whereHas('venta', function ($q) use ($empresaId, $fechaJornada) {
                $q->whereDate('fechajornada', $fechaJornada)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->get();

        $emisionesPorWaitry = VentaGastronomiaEmision::query()
            ->with(['venta.cobranzasDirectas', 'venta.caja_movimientos.cobranzas', 'cuenta', 'waitryComandaEnvio'])
            ->whereHas('venta', fn ($q) => $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId)))
            ->get();

        $mapAnitaJornada = [];
        $emisionesJornadaSinWaitry = [];
        foreach ($emisionesJornada as $emision) {
            $wid = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
            if ($wid > 0) {
                $mapAnitaJornada[$wid] = $emision;
            } else {
                $emisionesJornadaSinWaitry[] = $emision;
            }
        }

        $mapAnitaPorWaitry = [];
        foreach ($emisionesPorWaitry as $emision) {
            $wid = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
            if ($wid > 0) {
                $mapAnitaPorWaitry[$wid] = $emision;
            }
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

        foreach ($mapWaitry as $orderId => $orden) {
            $idsProcesados[] = $orderId;
            $emision = $mapAnitaPorWaitry[$orderId] ?? null;
            $cuentaPend = $cuentasPendientes->get($orderId);
            $filas[] = $this->armarFila($orderId, $orden, $emision, $cuentaPend, $fechaJornada, $empresaId);
        }

        $idsAnitaFueraListado = [];
        foreach ($mapAnitaJornada as $orderId => $emision) {
            if (! in_array($orderId, $idsProcesados, true)) {
                $idsAnitaFueraListado[] = $orderId;
            }
        }

        $mapPosConciliacion = $idsAnitaFueraListado !== []
            ? $this->ordenesExternasService->mapOrdenesPorIdsConciliacion(
                $empresaId,
                $idsAnitaFueraListado,
                $fechaJornada,
                $jornada?->apertura_en,
                $jornada?->cierre_en,
            )
            : [];

        foreach ($mapAnitaJornada as $orderId => $emision) {
            if (in_array($orderId, $idsProcesados, true)) {
                continue;
            }
            $ordenPos = $mapPosConciliacion[$orderId] ?? null;
            if ($ordenPos !== null) {
                $ordenNorm = $this->analyticsOrdenesService->normalizarOrden($ordenPos);
                $fila = $this->armarFila($orderId, $ordenNorm, $emision, null, $fechaJornada, $empresaId);
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

        $resumen = $this->calcularResumen($filas, $mapWaitry, $emisionesJornada, $empresaId, $fechaJornada);

        return [
            'ok' => true,
            'empresa_id' => $empresaId,
            'fecha_jornada' => $fechaJornada,
            'fecha_jornada_fmt' => $fechaFmt,
            'jornada' => $this->jornadaResumen($jornada),
            'meta_conciliacion' => $this->metaConciliacion($fechaJornada, $fechaFmt, $fechaWaitryHasta, $fechaWaitryHastaFmt, $jornada),
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
        string $fechaJornadaConsulta,
        int $empresaId,
    ): array {
        $waitryTotal = $this->montoTotalWaitry($orden);
        $waitryPaid = $this->esPagadaWaitry($orden);
        $anitaTotal = $emision !== null ? round((float) ($emision->venta?->total ?? 0), 2) : null;
        $diferencia = $anitaTotal !== null ? round($waitryTotal - $anitaTotal, 2) : null;
        $anitaFechajornada = $this->fechaJornadaVenta($emision);
        $jornadaCoincide = $anitaFechajornada === null || $anitaFechajornada === $fechaJornadaConsulta;

        $waitryTipoPago = WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($orden)
            ?? $emision?->cuenta?->waitry_tipo_pago
            ?? $cuentaPendiente?->waitry_tipo_pago;
        $cuentaEsperada = WaitryMedioPagoCuentacajaSupport::cuentaParaTipoWaitry($waitryTipoPago, $empresaId);
        $anitaMedio = $this->primerMedioCobranzaAnita($emision);

        $estado = 'sin_factura_anita';
        if ($emision !== null) {
            if (! $jornadaCoincide) {
                $estado = abs((float) $diferencia) > self::TOLERANCIA_MONTO ? 'jornada_distinta_monto' : 'jornada_distinta';
            } else {
                $estado = abs((float) $diferencia) > self::TOLERANCIA_MONTO ? 'monto_distinto' : 'conciliada';
            }
            if ($estado === 'conciliada' && $waitryPaid && $anitaMedio !== null && $cuentaEsperada !== null) {
                if ((int) ($anitaMedio['cuentacaja_id'] ?? 0) !== (int) $cuentaEsperada['id']) {
                    $estado = 'medio_distinto';
                }
            }
        } elseif ($cuentaPendiente !== null) {
            $estado = 'importada_pendiente';
        }

        $metaEnvio = $emision !== null
            ? VentaGastronomiaEmisionWaitrySupport::metaEnvioComanda($emision)
            : ['estado' => null, 'ultimo_error' => null];

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
            'waitry_medio_label' => WaitryMedioPagoCuentacajaSupport::etiquetaTipo($waitryTipoPago),
            'cuentacaja_esperada_id' => $cuentaEsperada['id'] ?? null,
            'cuentacaja_esperada_label' => isset($cuentaEsperada['codigo'], $cuentaEsperada['nombre'])
                ? trim($cuentaEsperada['codigo'].' — '.$cuentaEsperada['nombre'])
                : null,
            'anita_venta_id' => $emision?->venta_id,
            'anita_codigo' => $emision?->venta?->codigo,
            'anita_fechajornada' => $anitaFechajornada,
            'anita_fechajornada_fmt' => $anitaFechajornada ? $this->formatearFecha($anitaFechajornada) : null,
            'anita_total' => $anitaTotal,
            'anita_totem' => (bool) ($emision?->cuenta?->waitry_cobro_totem ?? $cuentaPendiente?->waitry_cobro_totem),
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
    private function calcularResumen(array $filas, array $mapWaitry, $emisiones, int $empresaId, string $fechaJornada): array
    {
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
        }

        foreach ($mapWaitry as $orden) {
            $monto = $this->montoTotalWaitry($orden);
            $totalWaitry += $monto;
            if ($this->esPagadaWaitry($orden)) {
                $totalWaitryPagado += $monto;
                $tipo = WaitryMedioPagoCuentacajaSupport::extraerTipoPagoOrden($orden) ?? 'totem';
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
            'facturas_anita_jornada' => $emisiones->count(),
            'facturas_anita_waitry' => $emisiones->filter(
                fn (VentaGastronomiaEmision $e) => VentaGastronomiaEmisionWaitrySupport::resolverOrderId($e) > 0,
            )->count(),
            'total_waitry' => round($totalWaitry, 2),
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
        ];
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
     * @param  array<string, mixed>  $orden
     */
    private function esPagadaWaitry(array $orden): bool
    {
        return WaitryOrdenCobroSupport::cobradaEnTotem($orden);
    }

    /**
     * Monto Waitry: getordersdetails (`totalAmount`) o getOrdersPOS (`payment.total_fee`).
     *
     * @param  array<string, mixed>  $orden
     */
    private function montoTotalWaitry(array $orden): float
    {
        $total = isset($orden['totalAmount']) && is_numeric($orden['totalAmount'])
            ? round((float) $orden['totalAmount'], 2)
            : 0.0;
        if ($total <= 0.0001) {
            $total = WaitryOrdenCobroSupport::montoCobro($orden);
        }

        return $total;
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
    private function metaConciliacion(
        string $fechaJornada,
        string $fechaFmt,
        string $fechaWaitryHasta,
        string $fechaWaitryHastaFmt,
        ?JornadaGastronomia $jornada,
    ): array {
        $meta = [
            'waitry_api' => 'getordersdetails',
            'waitry_desde' => $fechaJornada,
            'waitry_hasta' => $fechaWaitryHasta,
            'waitry_rango_etiqueta' => 'Calendario Waitry: '.$fechaFmt.' — '.$fechaWaitryHastaFmt
                .' (+1 día calendario por ventas después de medianoche)',
            'anita_criterio' => 'venta.fechajornada = '.$fechaFmt.' (todas las emisiones gastronomía; orderId desde emisión, cuenta o envío KDS)',
        ];

        if ($jornada !== null) {
            $apertura = $jornada->apertura_en?->format('d/m/Y H:i');
            $cierre = $jornada->cierre_en?->format('d/m/Y H:i');
            if ($apertura !== null || $cierre !== null) {
                $meta['ventana_jornada_etiqueta'] = trim(($apertura ?? '—').' — '.($cierre ?? '—'))
                    .' (referencia; el proceso inferior usa esta ventana + tramo de IDs Waitry)';
            }
        }

        return $meta;
    }
}
