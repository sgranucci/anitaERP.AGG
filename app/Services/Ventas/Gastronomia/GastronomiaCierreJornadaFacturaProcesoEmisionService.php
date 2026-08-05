<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Stock\MovimientoStock;
use App\Models\Configuracion\Actividad_Arca;
use App\Models\Configuracion\Condicioniva;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Stock\Articulo_MovimientoService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryAnalyticsOrdenesService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosPreviewSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaComandasSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaItemsSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaLotesSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaNumeracionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaRecuperacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoInsumoAjusteSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoJornadaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoPuntoventaSupport;
use App\Support\Ventas\CaeaEmisionFechaCorrelatividadSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaFechajornadaSupport;
use App\Support\Ventas\CaeaEmisionNumeracionSupport;
use App\Support\Ventas\GastronomiaDepositoConfigSupport;
use App\Support\Ventas\GastronomiaMovimientoStockSupport;
use App\Support\Ventas\GastronomiaPuntoventaEmisionLock;
use App\Support\Ventas\Waitry\WaitryCierreJornadaVentanaSupport;
use App\Support\Ventas\Waitry\WaitryFacturacionDuplicadosSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Emisión de facturas CF del proceso de cierre Waitry (Caja), en lotes por tope consumidor final.
 */
final class GastronomiaCierreJornadaFacturaProcesoEmisionService
{
    public const IDENTIFICADOR_PC_PROCESO = 'CIERRE-JORNADA-WAITRY';

    public function __construct(
        private readonly GastronomiaCierreJornadaProcesoService $procesoService,
        private readonly GastronomiaFacturacionService $facturacionGastronomiaService,
        private readonly GastronomiaCobranzaService $cobranzaGastronomiaService,
        private readonly GastronomiaReceptorFacturacionService $receptorFacturacionService,
        private readonly GastronomiaCuentaService $cuentaService,
        private readonly WaitryAnalyticsOrdenesService $analyticsOrdenesService,
        private readonly WaitryOrdenesExternasService $waitryOrdenesService,
        private readonly GastronomiaFormulaConsumoService $consumoFormulaService,
        private readonly Articulo_MovimientoService $articuloMovimientoService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function previewLotes(int $jornadaId, float $porcentaje): array
    {
        $clasificacion = $this->procesoService->clasificacionActual($jornadaId, $porcentaje);
        $movimientos = $clasificacion['movimientos'] ?? [];
        $plan = CierreJornadaProcesoFacturaLotesSupport::armarPlanDesdeMovimientos($movimientos);

        $lotesResumen = [];
        foreach ($plan['lotes'] as $lote) {
            $lotesResumen[] = [
                'numero' => (int) $lote['numero'],
                'total' => (float) $lote['total'],
                'cantidad_comandas' => (int) $lote['cantidad_comandas'],
                'waitry_order_ids' => $lote['waitry_order_ids'],
            ];
        }

        return [
            'ok' => true,
            'plan' => $plan,
            'lotes' => $lotesResumen,
            'cantidad_lotes' => count($lotesResumen),
            'cantidad_comandas_factura' => (int) $plan['cantidad_comandas_factura'],
            'cantidad_comandas_ajuste' => (int) $plan['cantidad_comandas_ajuste'],
            'total_factura' => (float) $plan['total_factura'],
            'total_ajuste' => (float) $plan['total_ajuste'],
            'tope_cf' => (float) $plan['tope_cf'],
            'objetivo_lote' => (float) $plan['objetivo_lote'],
            'porcentaje_lote' => (float) $plan['porcentaje_lote'],
            'cuadre_ok' => (bool) $plan['cuadre_ok'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function emitir(
        int $jornadaId,
        float $porcentaje,
        int $puntoventaId,
        string $fechaFactura,
        bool $usarRecuperacionSnapshot = false,
    ): array {
        $jornada = JornadaGastronomia::query()->findOrFail($jornadaId);
        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();

        CierreJornadaProcesoJornadaSupport::assertPuedeEmitirFacturaProceso($jornada, $snapshot);

        $payloadSnap = is_array($snapshot?->payload) ? $snapshot->payload : [];
        if (self::emisionYaRegistrada($payloadSnap['factura_proceso_emision'] ?? null)) {
            $prev = $payloadSnap['factura_proceso_emision'];
            $cant = count($prev['facturas'] ?? []);
            $ref = $cant > 0
                ? $cant.' factura(s) del proceso'
                : ('venta #'.($prev['venta_id'] ?? ''));

            throw new InvalidArgumentException('Ya se emitió la facturación del proceso para esta jornada ('.$ref.').');
        }

        if ($usarRecuperacionSnapshot) {
            return $this->reemitirDesdeRecuperacionSnapshot($jornadaId, $puntoventaId, $fechaFactura);
        }

        $porcentaje = CierreJornadaProcesoJornadaSupport::resolverPorcentajeOperacion($snapshot, $porcentaje, true);

        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $fechaCierre = CaeaEmisionFechaCorrelatividadSupport::fechaCalendarioCierre(
            $jornada->cierre_en,
            $jornada->fecha_jornada,
        );
        $fechaFactura = trim($fechaFactura) !== '' ? $fechaFactura : $fechaCierre;

        $pv = $this->validarPuntoventa($puntoventaId, $empresaId);
        $cfg = $this->resolverCfgOperativa($empresaId);

        $clasificacion = $this->procesoService->clasificacionActual($jornadaId, $porcentaje);
        $movimientos = $clasificacion['movimientos'] ?? [];
        $plan = CierreJornadaProcesoFacturaLotesSupport::armarPlanDesdeMovimientos($movimientos);
        $lotes = $plan['lotes'];

        if ($lotes === [] && ($plan['comandas_ajuste'] ?? []) === []) {
            return $this->registrarEmisionOmitidaSinComandas(
                $jornada,
                $snapshot,
                $porcentaje,
                $fechaFactura,
                $fechaJornada,
            );
        }

        if ($lotes === [] && ($plan['comandas_ajuste'] ?? []) !== []) {
            return $this->emitirSoloAjusteInsumos(
                $jornada,
                $snapshot,
                $cfg,
                $plan,
                $ordenesPorId = $this->mapOrdenesWaitry($jornada, $this->waitryOrderIdsParaEmision($plan)),
                $fechaFactura,
                $fechaJornada,
                $porcentaje,
            );
        }

        foreach (CierreJornadaProcesoFacturaComandasSupport::movimientosFacturacion($movimientos) as $mov) {
            $wid = (int) ($mov['waitry_order_id'] ?? 0);
            if ($wid > 0 && WaitryFacturacionDuplicadosSupport::waitryOrderIdYaFacturado($wid)) {
                throw new InvalidArgumentException(WaitryFacturacionDuplicadosSupport::mensajeOrdenYaFacturada($wid));
            }
        }

        $ordenesPorId = $this->mapOrdenesWaitry($jornada, $this->waitryOrderIdsParaEmision($plan));
        $tipoFacturaId = $this->resolverTipoFacturaId($cfg);
        $puntoventaEmisionId = (int) $pv['id'];
        $monedaId = (int) config('gastronomia.moneda_factura_id', 1) ?: 1;
        $tipo = Tipotransaccion::query()->find($tipoFacturaId);
        $nombreTipo = $tipo !== null ? (string) ($tipo->nombre ?? 'Venta') : 'Venta';

        $receptor = $this->receptorFacturacionService->datosVentaReceptorConsumidorFinal();
        $receptor['cliente_id'] = (int) config('facturacion.CLIENTE_CONSUMIDOR_FINAL_ID', 1);

        $puntoventaModel = Puntoventa::query()->findOrFail($puntoventaEmisionId);
        $letraComprobante = $this->letraComprobanteDesdeReceptor($receptor);
        $emisionCaea = ($puntoventaModel->modofacturacion ?? '') === 'A';
        $fechaFacturaPedida = $fechaFactura;
        $correlatividad = null;

        $lockPv = null;
        try {
            $lockPv = GastronomiaPuntoventaEmisionLock::adquirir($puntoventaEmisionId);

            // Dentro del lock: re-evaluar correlatividad (POS pudo emitir entre preview y acá).
            $correlatividad = $this->resolverFechaFacturaCaeaCorrelatividad(
                $puntoventaModel,
                $tipo,
                $letraComprobante,
                $fechaFacturaPedida,
                $fechaJornada,
                $emisionCaea,
                $empresaId,
            );
            $fechaFactura = (string) $correlatividad['fechafactura'];

            $resultado = DB::transaction(function () use (
                $lotes,
                $plan,
                $cfg,
                $jornada,
                $snapshot,
                $ordenesPorId,
                $fechaFactura,
                $fechaJornada,
                $porcentaje,
                $tipoFacturaId,
                $nombreTipo,
                $puntoventaEmisionId,
                $receptor,
                $monedaId,
                $puntoventaModel,
                $tipo,
                $letraComprobante,
                $emisionCaea,
            ) {
                $facturasEmitidas = [];
                $anitaPendientes = [];
                $vencaePendientes = [];
                $secuenciaNumeracion = null;
                if (count($lotes) > 1 && $tipo !== null) {
                    $secuenciaNumeracion = new CierreJornadaProcesoFacturaNumeracionSupport(
                        $puntoventaEmisionId,
                        (int) ($tipo->codigo ?? 0),
                        $letraComprobante,
                        0,
                        (int) $jornada->empresa_id,
                    );
                }

                foreach ($lotes as $lote) {
                    $comandas = $lote['comandas'];
                    $numeroLote = (int) $lote['numero'];
                    $items = CierreJornadaProcesoFacturaItemsSupport::construirItemsFactura(
                        $comandas,
                        $ordenesPorId,
                        $cfg,
                        $this->waitryOrdenesService,
                        $this->cuentaService,
                    );

                    if ($items['articulo_ids'] === []) {
                        $msg = $items['errores'][0] ?? 'No se pudieron armar ítems para el lote '.$numeroLote.'.';
                        throw new InvalidArgumentException($msg);
                    }

                    $erroresItems = array_filter($items['errores']);
                    if ($erroresItems !== []) {
                        throw new InvalidArgumentException(implode(' ', array_slice($erroresItems, 0, 5)));
                    }

                    $mediosPago = CierreJornadaProcesoAsientosPreviewSupport::mediosCobroConsolidadosComandas(
                        $comandas,
                        (int) $jornada->empresa_id,
                    );
                    if ($mediosPago === []) {
                        throw new InvalidArgumentException(
                            'No se pudieron resolver medios de cobro para el lote '.$numeroLote.'.',
                        );
                    }

                    $payload = [
                        'tipotransaccion_id' => $tipoFacturaId,
                        'puntoventa_id' => $puntoventaEmisionId,
                        'fechafactura' => $fechaFactura,
                        'fechajornada' => $fechaJornada,
                        'leyendafactura' => 'Cierre Waitry '.$fechaJornada.' — lote '.$numeroLote,
                        'actividad_arca_id' => (int) (Actividad_Arca::query()->orderBy('id')->value('id') ?? 1),
                        'cliente_id' => $receptor['cliente_id'],
                        'moneda_id' => $monedaId,
                        'listaprecio_id' => (int) ($cfg->listaprecio_id ?? 1),
                        'descuentopie' => 0.,
                        'descuentoimportepie' => 0.,
                        'descuentolinea' => 0.,
                        'articulo_ids' => $items['articulo_ids'],
                        'cantidades' => $items['cantidades'],
                        'precios' => $items['precios'],
                        'descripcionarticulos' => $items['descripciones'],
                        'omitir_percepciones' => true,
                    ];
                    $this->aplicarNumeracionAlPayloadProcesoCierre(
                        $payload,
                        $secuenciaNumeracion,
                        $emisionCaea,
                        $puntoventaModel,
                        $tipo,
                        $letraComprobante,
                    );
                    $this->receptorFacturacionService->aplicarReceptorAlPayloadFacturacion($payload, $receptor);

                    $resultadoFactura = $this->facturacionGastronomiaService->emitirComprobanteProcesoCierre($payload);
                    if (! empty($resultadoFactura['error'])) {
                        throw new InvalidArgumentException((string) $resultadoFactura['error']);
                    }

                    $ventaId = (int) ($resultadoFactura['venta_id'] ?? 0);
                    $venta = $ventaId > 0 ? Venta::query()->find($ventaId) : null;
                    if (! $venta) {
                        throw new RuntimeException('No se recuperó la venta tras emitir el lote '.$numeroLote.'.');
                    }

                    CierreJornadaProcesoFacturaFechajornadaSupport::asegurarEnVenta($venta, $fechaJornada);
                    CaeaEmisionFechaCorrelatividadSupport::assertVentaFechaNoRompeCorrelatividad($venta->fresh());

                    $cobRes = $this->cobranzaGastronomiaService->registrarCobranzaPos(
                        $venta->fresh(),
                        $mediosPago,
                        $cfg,
                    );

                    if (! empty($resultadoFactura['cae_pendiente']) && is_array($resultadoFactura['cae_pendiente'])) {
                        $vencaePendienteLote = $this->facturacionGastronomiaService->completarSolicitudCaePendiente(
                            $resultadoFactura['cae_pendiente'],
                        );
                        if (is_array($vencaePendienteLote)) {
                            $vencaePendientes[] = $vencaePendienteLote;
                        }
                    }

                    $this->consumoFormulaService->registrarMovimientosIngredientesDesdeVentaEmitida(
                        $venta->fresh(['venta_emisiones.articulos']),
                        $cfg,
                        $tipoFacturaId,
                        $nombreTipo,
                        $fechaFactura,
                        $monedaId,
                        $fechaJornada,
                    );

                    $comandasRef = CierreJornadaProcesoFacturaComandasSupport::referenciasComandasParaPersistencia(
                        $comandas,
                        $ordenesPorId,
                    );
                    $waitryOrderIdUnico = count($comandasRef) === 1
                        ? (int) ($comandasRef[0]['waitry_order_id'] ?? 0)
                        : 0;

                    VentaGastronomiaEmision::updateOrCreate(
                        ['venta_id' => $venta->id],
                        [
                            'cuenta_gastronomia_id' => null,
                            'waitry_order_id' => $waitryOrderIdUnico > 0 ? $waitryOrderIdUnico : null,
                            'waitry_comandas_json' => $comandasRef,
                            'cierre_jornada_proceso_lote' => $numeroLote,
                            'identificador_pc' => self::IDENTIFICADOR_PC_PROCESO,
                            'configuracion_puntoventa_gastronomia_id' => $cfg->id,
                        ],
                    );

                    $facturasEmitidas[] = [
                        'lote' => $numeroLote,
                        'venta_id' => $venta->id,
                        'factura' => (string) ($resultadoFactura['factura'] ?? $venta->codigo),
                        'cobranza_id' => (int) ($cobRes['cobranza_id'] ?? 0),
                        'total' => (float) $lote['total'],
                        'waitry_order_ids' => $items['waitry_order_ids'],
                        'comandas' => $comandasRef,
                        'cantidad_comandas' => count($comandas),
                    ];

                    if (! empty($resultadoFactura['anita_pendiente']) && is_array($resultadoFactura['anita_pendiente'])) {
                        $anitaPendientes[] = $resultadoFactura['anita_pendiente'];
                    }
                    if (! empty($resultadoFactura['vencae_pendiente']) && is_array($resultadoFactura['vencae_pendiente'])) {
                        $vencaePendientes[] = $resultadoFactura['vencae_pendiente'];
                    }
                }

                $comandasAjuste = $plan['comandas_ajuste'] ?? [];
                $comandasAjusteRef = CierreJornadaProcesoFacturaComandasSupport::referenciasComandasParaPersistencia(
                    $comandasAjuste,
                    $ordenesPorId,
                );
                $ajuste = null;
                if ($comandasAjuste !== []) {
                    $insumos = CierreJornadaProcesoFacturaItemsSupport::lineasInsumosComandasCompletas(
                        $comandasAjuste,
                        $ordenesPorId,
                        $cfg,
                        $this->waitryOrdenesService,
                        $this->cuentaService,
                        $this->consumoFormulaService,
                    );

                    $ajuste = CierreJornadaProcesoInsumoAjusteSupport::registrar(
                        array_map(
                            static fn (array $ln) => [
                                'articulo_id' => (int) $ln['articulo_id'],
                                'cantidad' => (float) $ln['cantidad'],
                            ],
                            $insumos['lineas'],
                        ),
                        (int) $jornada->empresa_id,
                        GastronomiaDepositoConfigSupport::depositoInsumosId($cfg),
                        $fechaFactura,
                        $fechaJornada,
                        'Cierre Waitry — insumos comandas no facturadas (efectivo)',
                        $this->articuloMovimientoService,
                    );
                }

                $waitryIds = [];
                foreach ($facturasEmitidas as $fac) {
                    foreach ($fac['waitry_order_ids'] as $wid) {
                        $waitryIds[] = (int) $wid;
                    }
                }

                $this->persistirEmisionEnSnapshot($snapshot, [
                    'venta_id' => $facturasEmitidas[0]['venta_id'] ?? null,
                    'factura' => $facturasEmitidas[0]['factura'] ?? null,
                    'facturas' => $facturasEmitidas,
                    'puntoventa_id' => $puntoventaEmisionId,
                    'cantidad_lotes' => count($facturasEmitidas),
                    'cobranza_id' => $facturasEmitidas[0]['cobranza_id'] ?? 0,
                    'porcentaje' => round($porcentaje, 4),
                    'waitry_order_ids' => array_values(array_unique($waitryIds)),
                    'cantidad_comandas' => (int) $plan['cantidad_comandas_factura'],
                    'cantidad_comandas_ajuste' => (int) $plan['cantidad_comandas_ajuste'],
                    'total_factura' => (float) $plan['total_factura'],
                    'total_ajuste' => (float) $plan['total_ajuste'],
                    'comandas_ajuste' => $comandasAjusteRef,
                    'ajuste_insumos' => $ajuste,
                    'emitido_en' => now()->toIso8601String(),
                ], descartarRecuperacionArchivada: true);

                return [
                    'facturas' => $facturasEmitidas,
                    'ajuste_insumos' => $ajuste,
                    'anita_pendientes' => $anitaPendientes,
                    'vencae_pendientes' => $vencaePendientes,
                ];
            });

            $this->ejecutarPendientesAnitaPostCommit(
                $resultado,
                'gastronomia.cierre_jornada.factura_proceso.anita_post_commit',
            );

            $facturas = $resultado['facturas'] ?? [];
            $cantLotes = count($facturas);
            $primera = $facturas[0] ?? [];
            $mensaje = $cantLotes === 1
                ? 'Factura del proceso emitida: '.($primera['factura'] ?? '')
                : 'Se emitieron '.$cantLotes.' facturas CF del proceso.';
            if (is_array($correlatividad) && ! empty($correlatividad['ajustada']) && ! empty($correlatividad['mensaje'])) {
                $mensaje .= ' '.$correlatividad['mensaje'];
            }

            return [
                'ok' => true,
                'emision_nueva' => true,
                'mensaje' => $mensaje,
                'venta_id' => (int) ($primera['venta_id'] ?? 0),
                'factura' => (string) ($primera['factura'] ?? ''),
                'facturas' => $facturas,
                'cantidad_lotes' => $cantLotes,
                'pdf_url' => ! empty($primera['venta_id'])
                    ? url('ventas/listaunafactura/'.$primera['venta_id'])
                    : null,
                'pdf_urls' => array_values(array_filter(array_map(
                    static fn (array $f) => ! empty($f['venta_id'])
                        ? url('ventas/listaunafactura/'.$f['venta_id'])
                        : null,
                    $facturas,
                ))),
                'cobranza_id' => $primera['cobranza_id'] ?? null,
                'ajuste_insumos' => $resultado['ajuste_insumos'] ?? null,
                'caea_fecha_correlatividad' => $correlatividad,
                'jornada_proceso' => $this->contextoJornadaProcesoTrasEmision($jornadaId),
            ];
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                GastronomiaMovimientoStockSupport::mensajeErrorEmision($e),
                0,
                $e,
            );
        } finally {
            GastronomiaPuntoventaEmisionLock::liberar($lockPv);
        }
    }

    /**
     * Re-emite facturas del proceso desde factura_proceso_emision_recuperacion (mismas comandas y numeración).
     * Solo debe invocarse de forma explícita (artisan o API con usar_recuperacion_snapshot=1).
     *
     * @return array<string, mixed>
     */
    public function reemitirDesdeRecuperacionSnapshot(
        int $jornadaId,
        int $puntoventaId,
        string $fechaFactura,
    ): array {
        $jornada = JornadaGastronomia::query()->findOrFail($jornadaId);
        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();

        CierreJornadaProcesoJornadaSupport::assertPuedeEmitirFacturaProceso($jornada, $snapshot);

        $payloadSnap = is_array($snapshot?->payload) ? $snapshot->payload : [];
        if (self::emisionYaRegistrada($payloadSnap['factura_proceso_emision'] ?? null)) {
            throw new InvalidArgumentException(
                'Ya hay una emisión activa en el snapshot. Ejecute la limpieza antes de re-emitir.',
            );
        }

        $recuperacion = CierreJornadaProcesoJornadaSupport::recuperacionEmisionDesdePayload($payloadSnap);
        if ($recuperacion === null || empty($recuperacion['facturas'])) {
            throw new InvalidArgumentException('No hay datos en factura_proceso_emision_recuperacion.');
        }

        $porcentaje = CierreJornadaProcesoJornadaSupport::resolverPorcentajeOperacion(
            $snapshot,
            (float) ($recuperacion['porcentaje'] ?? 0),
            true,
        );
        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $fechaCierre = CaeaEmisionFechaCorrelatividadSupport::fechaCalendarioCierre(
            $jornada->cierre_en,
            $jornada->fecha_jornada,
        );
        $fechaFactura = trim($fechaFactura) !== '' ? $fechaFactura : $fechaCierre;

        $pv = $this->validarPuntoventa($puntoventaId, $empresaId);
        $cfg = $this->resolverCfgOperativa($empresaId);
        $clasificacion = $this->procesoService->clasificacionActual($jornadaId, $porcentaje);
        $lotes = CierreJornadaProcesoFacturaRecuperacionSupport::armarLotesDesdeRecuperacion(
            $recuperacion['facturas'],
            $clasificacion['movimientos'] ?? [],
        );

        $ordenesPorId = $this->mapOrdenesWaitry($jornada, $this->waitryOrderIdsDesdeLotes($lotes));
        $tipoFacturaId = $this->resolverTipoFacturaId($cfg);
        $puntoventaEmisionId = (int) $pv['id'];
        $monedaId = (int) config('gastronomia.moneda_factura_id', 1) ?: 1;
        $tipo = Tipotransaccion::query()->find($tipoFacturaId);
        $nombreTipo = $tipo !== null ? (string) ($tipo->nombre ?? 'Venta') : 'Venta';
        $receptor = $this->receptorFacturacionService->datosVentaReceptorConsumidorFinal();
        $receptor['cliente_id'] = (int) config('facturacion.CLIENTE_CONSUMIDOR_FINAL_ID', 1);

        $puntoventaModel = Puntoventa::query()->findOrFail($puntoventaEmisionId);
        $letraComprobante = $this->letraComprobanteDesdeReceptor($receptor);
        $emisionCaea = ($puntoventaModel->modofacturacion ?? '') === 'A';
        $fechaFacturaPedida = $fechaFactura;
        $correlatividad = null;

        $ajustePrevio = is_array($recuperacion['ajuste_insumos'] ?? null) ? $recuperacion['ajuste_insumos'] : null;
        $ajusteMovId = (int) ($ajustePrevio['movimientostock_id'] ?? 0);
        $conservarAjuste = $ajusteMovId > 0 && MovimientoStock::query()->whereKey($ajusteMovId)->exists();

        $lockPv = null;
        try {
            $lockPv = GastronomiaPuntoventaEmisionLock::adquirir($puntoventaEmisionId);

            $correlatividad = $this->resolverFechaFacturaCaeaCorrelatividad(
                $puntoventaModel,
                $tipo,
                $letraComprobante,
                $fechaFacturaPedida,
                $fechaJornada,
                $emisionCaea,
                $empresaId,
            );
            $fechaFactura = (string) $correlatividad['fechafactura'];

            $resultado = DB::transaction(function () use (
                $lotes,
                $recuperacion,
                $cfg,
                $jornada,
                $snapshot,
                $ordenesPorId,
                $fechaFactura,
                $fechaJornada,
                $porcentaje,
                $tipoFacturaId,
                $nombreTipo,
                $puntoventaEmisionId,
                $receptor,
                $monedaId,
                $conservarAjuste,
                $ajustePrevio,
                $puntoventaModel,
                $tipo,
                $letraComprobante,
                $emisionCaea,
            ) {
                $facturasEmitidas = [];
                $anitaPendientes = [];
                $vencaePendientes = [];

                foreach ($lotes as $lote) {
                    $comandas = $lote['comandas'];
                    $numeroLote = (int) $lote['numero'];
                    $numeroForzado = (int) ($lote['numerocomprobante_forzado'] ?? 0);

                    $items = CierreJornadaProcesoFacturaItemsSupport::construirItemsFactura(
                        $comandas,
                        $ordenesPorId,
                        $cfg,
                        $this->waitryOrdenesService,
                        $this->cuentaService,
                    );

                    if ($items['articulo_ids'] === []) {
                        $msg = $items['errores'][0] ?? 'No se pudieron armar ítems para el lote '.$numeroLote.'.';
                        throw new InvalidArgumentException($msg);
                    }

                    $erroresItems = array_filter($items['errores']);
                    if ($erroresItems !== []) {
                        throw new InvalidArgumentException(implode(' ', array_slice($erroresItems, 0, 5)));
                    }

                    $mediosPago = CierreJornadaProcesoAsientosPreviewSupport::mediosCobroConsolidadosComandas(
                        $comandas,
                        (int) $jornada->empresa_id,
                    );
                    if ($mediosPago === []) {
                        throw new InvalidArgumentException(
                            'No se pudieron resolver medios de cobro para el lote '.$numeroLote.'.',
                        );
                    }

                    $payload = [
                        'tipotransaccion_id' => $tipoFacturaId,
                        'puntoventa_id' => $puntoventaEmisionId,
                        'fechafactura' => $fechaFactura,
                        'fechajornada' => $fechaJornada,
                        'leyendafactura' => 'Cierre Waitry '.$fechaJornada.' — lote '.$numeroLote,
                        'actividad_arca_id' => (int) (Actividad_Arca::query()->orderBy('id')->value('id') ?? 1),
                        'cliente_id' => $receptor['cliente_id'],
                        'moneda_id' => $monedaId,
                        'listaprecio_id' => (int) ($cfg->listaprecio_id ?? 1),
                        'descuentopie' => 0.,
                        'descuentoimportepie' => 0.,
                        'descuentolinea' => 0.,
                        'articulo_ids' => $items['articulo_ids'],
                        'cantidades' => $items['cantidades'],
                        'precios' => $items['precios'],
                        'descripcionarticulos' => $items['descripciones'],
                        'omitir_percepciones' => true,
                        'numerocomprobante_forzado' => $numeroForzado,
                    ];
                    $this->aplicarNumeracionAlPayloadProcesoCierre(
                        $payload,
                        null,
                        $emisionCaea,
                        $puntoventaModel,
                        $tipo,
                        $letraComprobante,
                    );
                    $this->receptorFacturacionService->aplicarReceptorAlPayloadFacturacion($payload, $receptor);

                    $resultadoFactura = $this->facturacionGastronomiaService->emitirComprobanteProcesoCierre($payload);
                    if (! empty($resultadoFactura['error'])) {
                        throw new InvalidArgumentException((string) $resultadoFactura['error']);
                    }

                    $ventaId = (int) ($resultadoFactura['venta_id'] ?? 0);
                    $venta = $ventaId > 0 ? Venta::query()->find($ventaId) : null;
                    if (! $venta) {
                        throw new RuntimeException('No se recuperó la venta tras re-emitir el lote '.$numeroLote.'.');
                    }

                    CierreJornadaProcesoFacturaFechajornadaSupport::asegurarEnVenta($venta, $fechaJornada);
                    CaeaEmisionFechaCorrelatividadSupport::assertVentaFechaNoRompeCorrelatividad($venta->fresh());

                    $cobRes = $this->cobranzaGastronomiaService->registrarCobranzaPos(
                        $venta->fresh(),
                        $mediosPago,
                        $cfg,
                    );

                    if (! empty($resultadoFactura['cae_pendiente']) && is_array($resultadoFactura['cae_pendiente'])) {
                        $vencaePendienteLote = $this->facturacionGastronomiaService->completarSolicitudCaePendiente(
                            $resultadoFactura['cae_pendiente'],
                        );
                        if (is_array($vencaePendienteLote)) {
                            $vencaePendientes[] = $vencaePendienteLote;
                        }
                    }

                    $this->consumoFormulaService->registrarMovimientosIngredientesDesdeVentaEmitida(
                        $venta->fresh(['venta_emisiones.articulos']),
                        $cfg,
                        $tipoFacturaId,
                        $nombreTipo,
                        $fechaFactura,
                        $monedaId,
                        $fechaJornada,
                    );

                    $comandasRef = CierreJornadaProcesoFacturaComandasSupport::referenciasComandasParaPersistencia(
                        $comandas,
                        $ordenesPorId,
                    );
                    $waitryOrderIdUnico = count($comandasRef) === 1
                        ? (int) ($comandasRef[0]['waitry_order_id'] ?? 0)
                        : 0;

                    VentaGastronomiaEmision::updateOrCreate(
                        ['venta_id' => $venta->id],
                        [
                            'cuenta_gastronomia_id' => null,
                            'waitry_order_id' => $waitryOrderIdUnico > 0 ? $waitryOrderIdUnico : null,
                            'waitry_comandas_json' => $comandasRef,
                            'cierre_jornada_proceso_lote' => $numeroLote,
                            'identificador_pc' => self::IDENTIFICADOR_PC_PROCESO,
                            'configuracion_puntoventa_gastronomia_id' => $cfg->id,
                        ],
                    );

                    $facturasEmitidas[] = [
                        'lote' => $numeroLote,
                        'venta_id' => $venta->id,
                        'factura' => (string) ($resultadoFactura['factura'] ?? $venta->codigo),
                        'cobranza_id' => (int) ($cobRes['cobranza_id'] ?? 0),
                        'total' => (float) $lote['total'],
                        'waitry_order_ids' => $items['waitry_order_ids'],
                        'comandas' => $comandasRef,
                        'cantidad_comandas' => count($comandas),
                    ];

                    if (! empty($resultadoFactura['anita_pendiente']) && is_array($resultadoFactura['anita_pendiente'])) {
                        $anitaPendientes[] = $resultadoFactura['anita_pendiente'];
                    }
                    if (! empty($resultadoFactura['vencae_pendiente']) && is_array($resultadoFactura['vencae_pendiente'])) {
                        $vencaePendientes[] = $resultadoFactura['vencae_pendiente'];
                    }
                }

                $ajuste = $conservarAjuste ? $ajustePrevio : null;
                $comandasAjusteRef = is_array($recuperacion['comandas_ajuste'] ?? null)
                    ? $recuperacion['comandas_ajuste']
                    : [];

                $this->persistirEmisionEnSnapshot($snapshot, [
                    'venta_id' => $facturasEmitidas[0]['venta_id'] ?? null,
                    'factura' => $facturasEmitidas[0]['factura'] ?? null,
                    'facturas' => $facturasEmitidas,
                    'puntoventa_id' => $puntoventaEmisionId,
                    'cantidad_lotes' => count($facturasEmitidas),
                    'cobranza_id' => $facturasEmitidas[0]['cobranza_id'] ?? 0,
                    'porcentaje' => round($porcentaje, 4),
                    'waitry_order_ids' => $recuperacion['waitry_order_ids'] ?? [],
                    'cantidad_comandas' => (int) ($recuperacion['cantidad_comandas'] ?? 0),
                    'cantidad_comandas_ajuste' => (int) ($recuperacion['cantidad_comandas_ajuste'] ?? 0),
                    'total_factura' => (float) ($recuperacion['total_factura'] ?? 0),
                    'total_ajuste' => (float) ($recuperacion['total_ajuste'] ?? 0),
                    'comandas_ajuste' => $comandasAjusteRef,
                    'ajuste_insumos' => $ajuste,
                    'emitido_en' => now()->toIso8601String(),
                    'recuperado_desde_snapshot' => true,
                ]);

                return [
                    'facturas' => $facturasEmitidas,
                    'ajuste_insumos' => $ajuste,
                    'anita_pendientes' => $anitaPendientes,
                    'vencae_pendientes' => $vencaePendientes,
                ];
            });

            $this->ejecutarPendientesAnitaPostCommit(
                $resultado,
                'gastronomia.cierre_jornada.factura_proceso.recuperacion.anita_post_commit',
            );

            $facturas = $resultado['facturas'] ?? [];
            $mensaje = 'Se re-emitieron '.count($facturas).' facturas del proceso (recuperación snapshot).';
            if (is_array($correlatividad) && ! empty($correlatividad['ajustada']) && ! empty($correlatividad['mensaje'])) {
                $mensaje .= ' '.$correlatividad['mensaje'];
            }

            return [
                'ok' => true,
                'mensaje' => $mensaje,
                'facturas' => $facturas,
                'venta_id' => (int) ($facturas[0]['venta_id'] ?? 0),
                'factura' => (string) ($facturas[0]['factura'] ?? ''),
                'caea_fecha_correlatividad' => $correlatividad,
                'jornada_proceso' => $this->contextoJornadaProcesoTrasEmision($jornadaId),
            ];
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException(
                GastronomiaMovimientoStockSupport::mensajeErrorEmision($e),
                0,
                $e,
            );
        } finally {
            GastronomiaPuntoventaEmisionLock::liberar($lockPv);
        }
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function ejecutarPendientesAnitaPostCommit(array $resultado, string $logKey): void
    {
        foreach ($resultado['anita_pendientes'] ?? [] as $anitaPendiente) {
            if (! is_array($anitaPendiente)) {
                continue;
            }
            try {
                $this->facturacionGastronomiaService->ejecutarAnitaPendienteGastronomia($anitaPendiente);
            } catch (Throwable $e) {
                Log::error($logKey, [
                    'venta_id' => $anitaPendiente['venta']['id'] ?? null,
                    'referencia_factura' => $anitaPendiente['referencia_factura'] ?? null,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        foreach ($resultado['vencae_pendientes'] ?? [] as $vencaePendiente) {
            if (! is_array($vencaePendiente)) {
                continue;
            }
            try {
                $this->facturacionGastronomiaService->ejecutarVencaePendienteGastronomia($vencaePendiente);
            } catch (Throwable $e) {
                Log::error($logKey.'.vencae', [
                    'msg' => $e->getMessage(),
                    'vencae' => $vencaePendiente,
                ]);
            }
        }
    }

    /**
     * @return list<array{id:int,codigo:string,nombre:string,modofacturacion:?string}>
     */
    public function listarPuntosVentaElectronicos(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        return Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) {
                $q->whereNull('modofacturacion')->orWhere('modofacturacion', '!=', 'M');
            })
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'modofacturacion'])
            ->map(fn (Puntoventa $pv) => [
                'id' => (int) $pv->id,
                'codigo' => (string) $pv->codigo,
                'nombre' => (string) $pv->nombre,
                'modofacturacion' => $pv->modofacturacion !== null ? (string) $pv->modofacturacion : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  mixed  $emision
     */
    private static function emisionYaRegistrada(mixed $emision): bool
    {
        if (! is_array($emision)) {
            return false;
        }

        if (! empty($emision['omitida'])) {
            return true;
        }

        if (! empty($emision['facturas']) && is_array($emision['facturas'])) {
            return true;
        }

        return ! empty($emision['venta_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function registrarEmisionOmitidaSinComandas(
        JornadaGastronomia $jornada,
        ?GastronomiaCierreJornadaProcesoSnapshot $snapshot,
        float $porcentaje,
        string $fechaFactura,
        string $fechaJornada,
    ): array {
        $emision = CierreJornadaProcesoJornadaSupport::emisionOmitidaPayload($porcentaje);
        $emision['fecha_factura'] = $fechaFactura;
        $emision['fecha_jornada'] = $fechaJornada;
        $this->persistirEmisionEnSnapshot($snapshot, $emision, true);

        return [
            'ok' => true,
            'mensaje' => 'No hay comandas Waitry sin facturar ni ajuste de insumos. '
                .'Se omitió la facturación del proceso; ya puede grabar los asientos contables.',
            'emision_omitida' => true,
            'facturas' => [],
            'total_factura' => 0.,
            'total_ajuste' => 0.,
            'jornada_proceso' => $this->contextoJornadaProcesoTrasEmision((int) $jornada->id),
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function emitirSoloAjusteInsumos(
        JornadaGastronomia $jornada,
        ?GastronomiaCierreJornadaProcesoSnapshot $snapshot,
        ConfiguracionPuntoventaGastronomia $cfg,
        array $plan,
        array $ordenesPorId,
        string $fechaFactura,
        string $fechaJornada,
        float $porcentaje,
    ): array {
        $comandasAjuste = $plan['comandas_ajuste'] ?? [];
        $insumos = CierreJornadaProcesoFacturaItemsSupport::lineasInsumosComandasCompletas(
            $comandasAjuste,
            $ordenesPorId,
            $cfg,
            $this->waitryOrdenesService,
            $this->cuentaService,
            $this->consumoFormulaService,
        );

        $ajuste = CierreJornadaProcesoInsumoAjusteSupport::registrar(
            array_map(
                static fn (array $ln) => [
                    'articulo_id' => (int) $ln['articulo_id'],
                    'cantidad' => (float) $ln['cantidad'],
                ],
                $insumos['lineas'],
            ),
            (int) $jornada->empresa_id,
            GastronomiaDepositoConfigSupport::depositoInsumosId($cfg),
            $fechaFactura,
            $fechaJornada,
            'Cierre Waitry — insumos comandas no facturadas (efectivo)',
            $this->articuloMovimientoService,
        );

        $comandasAjusteRef = CierreJornadaProcesoFacturaComandasSupport::referenciasComandasParaPersistencia(
            $comandasAjuste,
            $ordenesPorId,
        );

        $this->persistirEmisionEnSnapshot($snapshot, [
            'venta_id' => null,
            'factura' => null,
            'facturas' => [],
            'cantidad_lotes' => 0,
            'porcentaje' => round($porcentaje, 4),
            'waitry_order_ids' => array_values(array_unique(array_filter(
                array_map(static fn (array $c) => (int) ($c['waitry_order_id'] ?? 0), $comandasAjusteRef),
                static fn (int $id) => $id > 0,
            ))),
            'cantidad_comandas' => 0,
            'cantidad_comandas_ajuste' => count($comandasAjuste),
            'total_factura' => 0.,
            'total_ajuste' => (float) $plan['total_ajuste'],
            'comandas_ajuste' => $comandasAjusteRef,
            'ajuste_insumos' => $ajuste,
            'solo_ajuste' => true,
            'emitido_en' => now()->toIso8601String(),
        ], descartarRecuperacionArchivada: true);

        return [
            'ok' => true,
            'mensaje' => 'No había comandas para facturar; se registró el ajuste de insumos.',
            'venta_id' => 0,
            'factura' => '',
            'facturas' => [],
            'cantidad_lotes' => 0,
            'ajuste_insumos' => $ajuste,
            'jornada_proceso' => $this->contextoJornadaProcesoTrasEmision((int) $jornada->id),
        ];
    }

    private function resolverTipoFacturaId(ConfiguracionPuntoventaGastronomia $cfg): int
    {
        $tipoFacturaId = (int) ($cfg->tipotransaccion_id ?? 0);
        if ($tipoFacturaId <= 0) {
            $tipoFacturaId = (int) config('gastronomia.tipotransaccion_factura_id', 0);
        }
        if ($tipoFacturaId <= 0) {
            throw new InvalidArgumentException('Configure tipotransaccion_id en configuración PV gastronomía.');
        }

        return $tipoFacturaId;
    }

    /**
     * @return array{id:int,codigo:string,nombre:string}
     */
    private function validarPuntoventa(int $puntoventaId, int $empresaId): array
    {
        if ($puntoventaId <= 0) {
            $pv = CierreJornadaProcesoPuntoventaSupport::resolverOError($empresaId);

            return ['id' => $pv['id'], 'codigo' => $pv['codigo'], 'nombre' => $pv['nombre']];
        }

        $pvModel = Puntoventa::query()
            ->whereKey($puntoventaId)
            ->where('empresa_id', $empresaId)
            ->first();

        if ($pvModel === null || ($pvModel->modofacturacion ?? '') === 'M') {
            throw new InvalidArgumentException('Punto de venta inválido o manual para facturación electrónica.');
        }

        return [
            'id' => (int) $pvModel->id,
            'codigo' => (string) $pvModel->codigo,
            'nombre' => (string) $pvModel->nombre,
        ];
    }

    private function resolverCfgOperativa(int $empresaId): ConfiguracionPuntoventaGastronomia
    {
        $cfg = ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->orderBy('id')
            ->first();

        if ($cfg === null) {
            throw new InvalidArgumentException(
                'No hay configuración de punto de venta gastronomía para la empresa '.$empresaId.'.',
            );
        }

        return $cfg;
    }

    /**
     * @param  list<int>  $waitryOrderIdsFallback
     * @return array<int, array<string, mixed>>
     */
    private function mapOrdenesWaitry(JornadaGastronomia $jornada, array $waitryOrderIdsFallback = []): array
    {
        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        if ($fecha === '') {
            return [];
        }

        $resuelto = WaitryCierreJornadaVentanaSupport::resolverParaCierreJornada(
            $fecha,
            $jornada->apertura_en,
            $jornada->cierre_en,
        );
        $rango = $resuelto['rango_calendario'];
        $waitry = $this->analyticsOrdenesService->ordenesPorRangoFecha(
            (int) $jornada->empresa_id,
            $rango['desde'],
            $rango['hasta'],
        );
        if ($waitry['ok'] ?? false) {
            $porId = [];
            foreach ($waitry['ordenes'] ?? [] as $orden) {
                if (! is_array($orden)) {
                    continue;
                }
                $id = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);
                if ($id > 0) {
                    $porId[$id] = $orden;
                }
            }

            return $porId;
        }

        if ($waitryOrderIdsFallback !== []) {
            $porPos = $this->waitryOrdenesService->mapOrdenesPorIdsConciliacion(
                (int) $jornada->empresa_id,
                $waitryOrderIdsFallback,
                $fecha,
                $jornada->apertura_en,
                $jornada->cierre_en,
            );
            if ($porPos !== []) {
                return $porPos;
            }
        }

        throw new InvalidArgumentException($waitry['error'] ?? 'No se pudieron consultar órdenes Waitry.');
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<int>
     */
    private function waitryOrderIdsParaEmision(array $plan): array
    {
        $ids = [];
        foreach ($plan['lotes'] ?? [] as $lote) {
            if (! is_array($lote)) {
                continue;
            }
            foreach ($lote['comandas'] ?? [] as $comanda) {
                if (! is_array($comanda)) {
                    continue;
                }
                $id = (int) ($comanda['waitry_order_id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        foreach ($plan['comandas_ajuste'] ?? [] as $comanda) {
            if (! is_array($comanda)) {
                continue;
            }
            $id = (int) ($comanda['waitry_order_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<array<string, mixed>>  $lotes
     * @return list<int>
     */
    private function waitryOrderIdsDesdeLotes(array $lotes): array
    {
        $ids = [];
        foreach ($lotes as $lote) {
            if (! is_array($lote)) {
                continue;
            }
            foreach ($lote['comandas'] ?? [] as $comanda) {
                if (! is_array($comanda)) {
                    continue;
                }
                $id = (int) ($comanda['waitry_order_id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            foreach ($lote['waitry_order_ids'] ?? [] as $orderId) {
                $id = (int) $orderId;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $emision
     */
    private function persistirEmisionEnSnapshot(
        ?GastronomiaCierreJornadaProcesoSnapshot $snapshot,
        array $emision,
        bool $descartarRecuperacionArchivada = false,
    ): void {
        if ($snapshot === null) {
            return;
        }

        $payload = $snapshot->payload;
        if (! is_array($payload)) {
            $payload = [];
        }
        $payload['factura_proceso_emision'] = $emision;
        if ($descartarRecuperacionArchivada) {
            unset($payload['factura_proceso_emision_recuperacion']);
        }
        $snapshot->payload = $payload;
        $snapshot->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function contextoJornadaProcesoTrasEmision(int $jornadaId): array
    {
        $jornada = JornadaGastronomia::query()->find($jornadaId);
        if ($jornada === null) {
            return [];
        }

        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();

        return CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);
    }

    /**
     * Numeración ERP para facturas del cierre Waitry (CAEA o CAE multi-lote).
     *
     * @param  array<string, mixed>  $payload
     */
    private function aplicarNumeracionAlPayloadProcesoCierre(
        array &$payload,
        ?CierreJornadaProcesoFacturaNumeracionSupport $secuenciaNumeracion,
        bool $emisionCaea,
        Puntoventa $puntoventaModel,
        ?Tipotransaccion $tipo,
        string $letraComprobante,
    ): void {
        if (! empty($payload['numerocomprobante_forzado'])) {
            CaeaEmisionNumeracionSupport::marcarNumerocomprobanteForzadoEnPayload(
                $payload,
                (int) $payload['numerocomprobante_forzado'],
            );

            return;
        }

        if ($secuenciaNumeracion !== null) {
            CaeaEmisionNumeracionSupport::marcarNumerocomprobanteForzadoEnPayload(
                $payload,
                $secuenciaNumeracion->siguiente(),
            );

            return;
        }

        if ($emisionCaea && $tipo !== null) {
            $errorNumeracion = CaeaEmisionNumeracionSupport::aplicarReservaNumeracionAlPayload(
                $payload,
                $puntoventaModel,
                $tipo,
                $letraComprobante,
                true,
            );
            if ($errorNumeracion !== null) {
                throw new InvalidArgumentException($errorNumeracion);
            }
        }
    }

    /**
     * PV CAEA: si la última factura del mismo PV+tipo tiene fecha mayor a la propuesta,
     * eleva solo fechafactura (ARCA 704). fechajornada no se modifica.
     *
     * @return array{
     *     fechafactura: string,
     *     fechajornada: string,
     *     ajustada: bool,
     *     aplica_caea: bool,
     *     ultima_fecha: ?string,
     *     ultimo_numero: ?int,
     *     mensaje: ?string,
     *     fecha_pedida: string
     * }
     */
    private function resolverFechaFacturaCaeaCorrelatividad(
        Puntoventa $puntoventa,
        ?Tipotransaccion $tipo,
        string $letraComprobante,
        string $fechaFactura,
        string $fechaJornada,
        bool $emisionCaea,
        int $empresaId,
    ): array {
        $fechaPedida = $fechaFactura;
        if (! $emisionCaea || $tipo === null) {
            return [
                'fechafactura' => $fechaFactura,
                'fechajornada' => $fechaJornada,
                'ajustada' => false,
                'aplica_caea' => false,
                'ultima_fecha' => null,
                'ultimo_numero' => null,
                'mensaje' => null,
                'fecha_pedida' => $fechaPedida,
            ];
        }

        $resuelto = CaeaEmisionFechaCorrelatividadSupport::resolverFechas(
            $puntoventa,
            $fechaFactura,
            $fechaJornada,
            $tipo,
            $letraComprobante,
            $empresaId > 0 ? $empresaId : null,
            null,
            null,
        );

        if ($resuelto['ajustada']) {
            Log::info('cierre_jornada_waitry.caea_fecha_correlatividad', [
                'puntoventa_id' => (int) $puntoventa->id,
                'fecha_factura_pedida' => $fechaPedida,
                'fecha_jornada' => $fechaJornada,
                'fecha_factura' => $resuelto['fechafactura'],
                'ultima_fecha' => $resuelto['ultima_fecha'],
                'ultimo_numero' => $resuelto['ultimo_numero'],
            ]);
        }

        return [
            'fechafactura' => (string) $resuelto['fechafactura'],
            'fechajornada' => (string) $resuelto['fechajornada'],
            'ajustada' => (bool) $resuelto['ajustada'],
            'aplica_caea' => (bool) ($resuelto['aplica_caea'] ?? true),
            'ultima_fecha' => $resuelto['ultima_fecha'],
            'ultimo_numero' => $resuelto['ultimo_numero'],
            'mensaje' => $resuelto['mensaje'],
            'fecha_pedida' => $fechaPedida,
        ];
    }

    /**
     * Preview / modal de emisión: evalúa si hay que elevar CbteFch por correlatividad CAEA.
     *
     * @return array<string, mixed>
     */
    public function evaluarCorrelatividadFechaEmision(
        int $empresaId,
        int $puntoventaId,
        string $fechaFactura,
        string $fechaJornada,
    ): array {
        $pv = $this->validarPuntoventa($puntoventaId, $empresaId);
        $puntoventaModel = Puntoventa::query()->findOrFail((int) $pv['id']);
        $cfg = $this->resolverCfgOperativa($empresaId);
        $tipo = Tipotransaccion::query()->find($this->resolverTipoFacturaId($cfg));
        $receptor = $this->receptorFacturacionService->datosVentaReceptorConsumidorFinal();
        $letra = $this->letraComprobanteDesdeReceptor($receptor);
        $emisionCaea = ($puntoventaModel->modofacturacion ?? '') === 'A';

        return $this->resolverFechaFacturaCaeaCorrelatividad(
            $puntoventaModel,
            $tipo,
            $letra,
            $fechaFactura,
            $fechaJornada,
            $emisionCaea,
            $empresaId,
        );
    }

    /**
     * @param  array<string, mixed>  $receptor
     */
    private function letraComprobanteDesdeReceptor(array $receptor): string
    {
        $letra = 'B';
        $clienteId = (int) ($receptor['cliente_id'] ?? 0);

        if ($clienteId > 0) {
            $cliente = Cliente::query()->find($clienteId);
            if ($cliente !== null && $cliente->condicioniva_id) {
                $letra = (string) (Condicioniva::query()
                    ->whereKey($cliente->condicioniva_id)
                    ->value('letra') ?? 'B');
            }
        }

        $letra = trim($letra);

        return $letra !== '' ? $letra : 'B';
    }
}
