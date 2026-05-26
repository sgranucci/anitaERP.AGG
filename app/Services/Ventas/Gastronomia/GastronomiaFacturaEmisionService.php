<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Actividad_Arca;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Ventas\GastronomiaEmisionProfiler;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use App\Support\Ventas\GastronomiaMovimientoStockSupport;
use App\Support\Ventas\GastronomiaPuntoventaEmisionLock;
use App\Services\Ventas\Gastronomia\GastronomiaTicketTarjetaCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaCategoriafidelidadCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketCanjePremioService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryComandaService;
use App\Services\Ventas\Gastronomia\Waitry\WaitrySyncStatusPosService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Orquesta la emisión de factura desde una cuenta gastronómica (validaciones, PV, receptor, persistencia).
 * Factura + cobranza + ingredientes + cierre de cuenta en una sola transacción: ante cualquier fallo no queda nada grabado.
 */
final class GastronomiaFacturaEmisionService
{
    public function __construct(
        private readonly GastronomiaFacturacionService $facturacionGastronomiaService,
        private readonly GastronomiaFormulaOpcionalesService $opcionalesService,
        private readonly GastronomiaFormulaConsumoService $consumoFormulaService,
        private readonly GastronomiaCuentaService $cuentaService,
        private readonly GastronomiaReceptorFacturacionService $receptorFacturacionService,
        private readonly GastronomiaCobranzaService $cobranzaGastronomiaService,
        private readonly GastronomiaPreflightEmisionService $preflightEmisionService,
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly GastronomiaFacturaTicketService $facturaTicketService,
        private readonly GastronomiaInsumoStkmovAnitaService $insumoStkmovAnitaService,
        private readonly WaitryComandaService $waitryComandaService,
        private readonly WaitrySyncStatusPosService $waitrySyncStatusPosService,
        private readonly GastronomiaTicketTarjetaCanjeService $ticketTarjetaCanjeService,
        private readonly GastronomiaTicketCanjePremioService $ticketCanjePremioService,
        private readonly GastronomiaCategoriafidelidadCanjeService $categoriafidelidadCanjeService,
    ) {
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return list<string>
     */
    public function erroresPreflightEmision(
        CuentaGastronomia $cuenta,
        int $monedaId,
        array $mediosPago,
        bool $facturacionConDescuento = false,
    ): array {
        $cuenta->loadMissing([
            'lineas.articulo',
            'cliente',
            'descuentoGastronomia',
            'mesa',
            'configuracionPuntoventa',
        ]);

        $cfg = $cuenta->configuracionPuntoventa ?? $this->cuentaService->resolverConfiguracionPv();
        if (! $cfg) {
            return $this->preflightEmisionService->erroresAntesDeEmitir(
                $cuenta,
                null,
                $monedaId,
                $mediosPago,
                [],
                $facturacionConDescuento,
            );
        }

        [$articuloIds, $cantidades, $precios, $descripciones, $opcionalesPorItem, $omitirStkmovAnitaPorItem]
            = $this->construirArraysFactura($cuenta);

        $tipoFacturaId = (int) ($cfg->tipotransaccion_id ?? 0);
        if ($tipoFacturaId <= 0) {
            $tipoFacturaId = (int) config('gastronomia.tipotransaccion_factura_id', 0);
        }

        try {
            $pvResolucion = ArcaWsfeEmisionResiliencia::resolverPuntoventaEmision(
                (int) $cfg->puntoventa_cae_id,
                (int) $cfg->puntoventa_caea_id,
                false
            );
            $puntoventaId = $pvResolucion['puntoventa_id'];
        } catch (InvalidArgumentException) {
            $puntoventaId = 0;
        }

        try {
            $receptor = $this->receptorFacturacionService->resolverParaFacturar($cuenta);
        } catch (InvalidArgumentException $e) {
            return array_merge(
                $this->preflightEmisionService->erroresAntesDeEmitir(
                    $cuenta,
                    $cfg,
                    $monedaId,
                    $mediosPago,
                    [],
                    $facturacionConDescuento,
                ),
                [$e->getMessage()]
            );
        }

        $leyenda = $this->leyendaCuenta($cuenta);

        $payload = $this->armarPayloadFacturaBase(
            $cfg,
            $tipoFacturaId,
            $puntoventaId,
            $leyenda,
            $receptor,
            $monedaId,
            $articuloIds,
            $cantidades,
            $precios,
            $descripciones,
            null,
            $opcionalesPorItem,
            $omitirStkmovAnitaPorItem,
        );

        try {
            $payload = $this->jornadaService->aplicarFechasAlPayload($payload, (int) $cuenta->empresa_id);
        } catch (InvalidArgumentException $e) {
            return [$e->getMessage()];
        }

        return $this->preflightEmisionService->erroresAntesDeEmitir(
            $cuenta,
            $cfg,
            $monedaId,
            $mediosPago,
            $payload,
            $facturacionConDescuento,
        );
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{venta_id?:int,factura?:string,warn?:string,error?:string,sin_cobranza?:bool,cobranza_id?:int}
     */
    public function emitirFacturaDesdeCuenta(
        CuentaGastronomia $cuenta,
        int $monedaId,
        ?int $actividadArcaId = null,
        bool $forzarPvCaea = false,
        array $mediosPago = [],
        bool $bloqueoPvYaAdquirido = false,
        bool $facturacionConDescuento = false,
    ): array {
        $profiler = GastronomiaEmisionProfiler::iniciarSiConfigurado();

        $cuenta->loadMissing([
            'lineas.articulo.formula_articulo.formula_articulo_hijos',
            'cliente',
            'descuentoGastronomia',
            'mesa',
            'configuracionPuntoventa',
        ]);

        if ($cuenta->lineas->isEmpty()) {
            return ['error' => 'La cuenta no tiene consumos cargados.'];
        }

        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            return ['error' => 'La cuenta no está abierta.'];
        }

        foreach ($cuenta->lineas as $linea) {
            $art = $linea->articulo;
            if (! $art) {
                return ['error' => 'Artículo inexistente en línea '.$linea->id.'.'];
            }

            if (FormulaArticuloGastronomia::opcionalesHabilitados()) {
                $grupos = $this->opcionalesService->gruposOpcionalesPorArticulo($art);
                if ($grupos !== []) {
                    $opcMap = [];
                    foreach (($linea->opcionales_json ?? []) as $k => $v) {
                        $opcMap[(string) $k] = $v !== null ? (int) $v : null;
                    }
                    try {
                        $this->opcionalesService->validarSeleccionOpcionales($art, $opcMap);
                    } catch (InvalidArgumentException $e) {
                        return ['error' => $e->getMessage()];
                    }
                }
            }
        }

        $cfg = $cuenta->configuracionPuntoventa ?? $this->cuentaService->resolverConfiguracionPv();
        if (! $cfg) {
            return ['error' => 'No hay configuración de punto de venta gastronomía para este equipo ('.GastronomiaIdentificadorPc::resolver().').'];
        }

        $tipoFacturaId = (int) ($cfg->tipotransaccion_id ?? 0);
        if ($tipoFacturaId <= 0) {
            $tipoFacturaId = (int) config('gastronomia.tipotransaccion_factura_id', 0);
        }
        if ($tipoFacturaId <= 0) {
            return ['error' => 'Configure el tipo de transacción (factura) en la configuración del punto de venta gastronomía de esta terminal.'];
        }

        $pvResolucion = ArcaWsfeEmisionResiliencia::resolverPuntoventaEmision(
            (int) $cfg->puntoventa_cae_id,
            (int) $cfg->puntoventa_caea_id,
            $forzarPvCaea
        );
        $puntoventaId = $pvResolucion['puntoventa_id'];
        $usaCaea = $pvResolucion['usa_caea'];

        [$articuloIds, $cantidades, $precios, $descripciones, $opcionalesPorItem, $omitirStkmovAnitaPorItem]
            = $this->construirArraysFactura($cuenta);

        try {
            $receptor = $this->receptorFacturacionService->resolverParaFacturar($cuenta);
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        $payload = $this->armarPayloadFacturaBase(
            $cfg,
            $tipoFacturaId,
            $puntoventaId,
            $this->leyendaCuenta($cuenta),
            $receptor,
            $monedaId,
            $articuloIds,
            $cantidades,
            $precios,
            $descripciones,
            $actividadArcaId,
            $opcionalesPorItem,
            $omitirStkmovAnitaPorItem,
        );

        try {
            $payload = $this->jornadaService->aplicarFechasAlPayload($payload, (int) $cuenta->empresa_id);
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        try {
            $this->preflightEmisionService->exigirListoParaEmitir(
                $cuenta,
                $cfg,
                $monedaId,
                $mediosPago,
                $payload,
                $facturacionConDescuento,
            );
        } catch (InvalidArgumentException $e) {
            return $this->adjuntarProfileEmision(['error' => $e->getMessage()], $profiler, $cuenta);
        }

        $profiler?->marcar('preflight_ok');

        $lockPv = null;
        if (! $bloqueoPvYaAdquirido) {
            try {
                $profiler?->marcar('antes_lock_pv');
                $lockPv = GastronomiaPuntoventaEmisionLock::adquirir($puntoventaId);
                $profiler?->marcar('despues_lock_pv');
            } catch (InvalidArgumentException $e) {
                GastronomiaEmisionProfiler::finalizar($profiler, ['cuenta_id' => $cuenta->id, 'error' => 'lock']);

                return ['error' => $e->getMessage()];
            }
        }

        try {
            try {
                $profiler?->marcar('antes_transaccion');
                $resultado = DB::transaction(function () use (
                    $cuenta,
                    $cfg,
                    $payload,
                    $mediosPago,
                    $monedaId,
                    $tipoFacturaId,
                    $puntoventaId,
                    $profiler,
                ) {
                    return $this->ejecutarEmisionEnTransaccion(
                        $cuenta,
                        $cfg,
                        $payload,
                        $mediosPago,
                        $monedaId,
                        $tipoFacturaId,
                        $puntoventaId,
                        $profiler,
                    );
                });
                $profiler?->marcar('despues_transaccion');

                $profiler?->marcar('antes_ticket');
                $resultado = $this->aplicarImpresionTicketTrasEmision($resultado, $cfg, $cuenta);
                $profiler?->marcar('despues_ticket');

                if (
                    config('gastronomia.waitry_tras_respuesta', true)
                    && config('waitry.habilitado', false)
                ) {
                    $this->encolarWaitryTrasRespuesta($resultado, $cuenta, $mediosPago);
                    $profiler?->marcar('waitry_encolado');

                    return $this->adjuntarProfileEmision($resultado, $profiler, $cuenta);
                }

                $profiler?->marcar('antes_waitry');
                $resultado = $this->aplicarWaitryComandaTrasEmision($resultado, $cuenta, $mediosPago);
                $profiler?->marcar('despues_waitry');

                return $this->adjuntarProfileEmision($resultado, $profiler, $cuenta);
            } catch (Throwable $e) {
                Log::error('gastronomia.emitir_factura.fallo', [
                    'cuenta_id' => $cuenta->id,
                    'msg' => $e->getMessage(),
                ]);

                if (! $forzarPvCaea && ArcaWsfeEmisionResiliencia::debeReintentarTransaccionConCaea($e->getMessage(), $usaCaea)) {
                    return $this->emitirFacturaDesdeCuenta(
                        $cuenta,
                        $monedaId,
                        $actividadArcaId,
                        true,
                        $mediosPago,
                        true,
                        $facturacionConDescuento,
                    );
                }

                return $this->adjuntarProfileEmision(
                    ['error' => GastronomiaMovimientoStockSupport::mensajeErrorEmision($e)],
                    $profiler,
                    $cuenta,
                );
            }
        } finally {
            if (! $bloqueoPvYaAdquirido) {
                GastronomiaPuntoventaEmisionLock::liberar($lockPv);
            }
        }
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{venta_id:int,factura:string,warn?:string,sin_cobranza?:bool,cobranza_id?:int}
     */
    private function ejecutarEmisionEnTransaccion(
        CuentaGastronomia $cuenta,
        ConfiguracionPuntoventaGastronomia $cfg,
        array $payload,
        array $mediosPago,
        int $monedaId,
        int $tipoFacturaId,
        int $puntoventaId,
        ?GastronomiaEmisionProfiler $profiler = null,
    ): array {
        $ventaAnitaRevertir = null;

        try {
            $profiler?->marcar('tx_emitir_comprobante_inicio');
            // 1) Número ARCA + grabación venta/ítems (CAE diferido vía omitir_solicitud_arca_cae).
            $resultado = $this->facturacionGastronomiaService->emitirComprobante($payload, $cuenta);
            $profiler?->marcar('tx_emitir_comprobante_fin');

            if (! empty($resultado['error'])) {
                throw new InvalidArgumentException((string) $resultado['error']);
            }

            $facturaTxt = trim((string) ($resultado['factura'] ?? ''));
            if ($facturaTxt === '') {
                throw new RuntimeException('El servicio de facturación no devolvió el número de comprobante.');
            }

            $ventaId = (int) ($resultado['venta_id'] ?? 0);
            $venta = $ventaId > 0 ? Venta::query()->find($ventaId) : null;
            if (! $venta) {
                $venta = $this->resolverVentaPorEtiqueta($puntoventaId, $facturaTxt);
            }
            if (! $venta) {
                throw new RuntimeException(
                    'No se pudo recuperar la venta interna tras emitir el comprobante '.$facturaTxt
                    .'. Revise ARCA y la tabla venta antes de reintentar.'
                );
            }

            if (config('gastronomia.sincronizar_anita_al_facturar', true)) {
                $ventaAnitaRevertir = $venta;
            }

            $sinCobranza = ! empty($resultado['sin_cobranza']);
            $cobranzaId = null;

            // 2) Cobranza
            if (! $sinCobranza) {
                $profiler?->marcar('tx_cobranza_inicio');
                $cobRes = $this->cobranzaGastronomiaService->registrarCobranzaPos(
                    $venta->fresh(),
                    $mediosPago,
                    $cfg,
                );
                $cobranzaId = isset($cobRes['cobranza_id']) ? (int) $cobRes['cobranza_id'] : null;
                $profiler?->marcar('tx_cobranza_fin');
            }

            if (! $sinCobranza) {
                $this->ticketTarjetaCanjeService->registrarCanjesTrasCobranza(
                    $venta->fresh(),
                    $mediosPago,
                    (int) $cfg->empresa_id,
                );
            }

            $this->ticketCanjePremioService->registrarTrasEmision(
                $venta->fresh(),
                $cuenta->fresh(),
            );

            $this->categoriafidelidadCanjeService->registrarTrasEmision(
                $venta->fresh(),
                $cuenta->fresh(),
            );

            // 3) Ingredientes por fórmula
            $tipo = Tipotransaccion::query()->find($tipoFacturaId);
            $nombreTipo = $tipo !== null ? (string) ($tipo->nombre ?? 'Venta') : 'Venta';

            $profiler?->marcar('tx_ingredientes_inicio');
            $this->consumoFormulaService->registrarMovimientosIngredientes(
                $venta,
                $cuenta->fresh(['lineas.articulo']),
                $cfg,
                $tipoFacturaId,
                $nombreTipo,
                (string) $payload['fechafactura'],
                $monedaId,
                (string) ($payload['fechajornada'] ?? $payload['fechafactura']),
            );

            $this->insumoStkmovAnitaService->replicarMovimientosInsumos(
                $venta->fresh(),
                $cfg,
                (float) ($payload['descuentopie'] ?? 0),
            );
            $profiler?->marcar('tx_ingredientes_anita_fin');

            // 4) CAE/CAEA en ARCA (último paso fiscal, con recuperación si falla la comunicación).
            if (! empty($resultado['cae_pendiente']) && is_array($resultado['cae_pendiente'])) {
                $profiler?->marcar('tx_vencae_inicio');
                $this->facturacionGastronomiaService->completarSolicitudCaePendiente($resultado['cae_pendiente']);
                $profiler?->marcar('tx_vencae_fin');
            }

            VentaGastronomiaEmision::updateOrCreate(
                ['venta_id' => $venta->id],
                [
                    'cuenta_gastronomia_id' => $cuenta->id,
                    'identificador_pc' => GastronomiaIdentificadorPc::resolver(),
                    'configuracion_puntoventa_gastronomia_id' => $cfg->id,
                ],
            );

            // 5) Cerrar cuenta/mesa
            $this->cuentaService->marcarFacturada($cuenta->fresh(), $venta->id);

            $ventaAnitaRevertir = null;

            $warn = ArcaWsfeEmisionResiliencia::mensajeAvisoModoCaeaForzado();
            if (! empty($resultado['factura_cortesia_total'])) {
                $warnCortesia = 'Factura de cortesía ($'.number_format(
                    GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA,
                    2,
                    ',',
                    '.',
                ).'); no requiere cobranza.';
                $warn = $warn ? $warn."\n\n".$warnCortesia : $warnCortesia;
            }

            return array_filter([
                'venta_id' => $venta->id,
                'factura' => $facturaTxt,
                'warn' => $warn,
                'sin_cobranza' => $sinCobranza,
                'cobranza_id' => $cobranzaId,
            ], fn ($v) => $v !== null && $v !== '' && $v !== false);
        } catch (Throwable $e) {
            if ($ventaAnitaRevertir !== null) {
                $this->facturacionGastronomiaService->revertirVentaEnAnitaSiHabilitado($ventaAnitaRevertir);
            }

            throw $e;
        }
    }

    /**
     * @param  array{cliente_id:int}  $receptor
     * @param  list<int>  $articuloIds
     * @param  list<float|int|string>  $cantidades
     * @param  list<float|int|string>  $precios
     * @param  list<string>  $descripciones
     * @param  array<int, array<string|int, int|null>>  $opcionalesPorItem
     * @param  list<bool>  $omitirStkmovAnitaPorItem
     * @return array<string, mixed>
     */
    private function armarPayloadFacturaBase(
        ConfiguracionPuntoventaGastronomia $cfg,
        int $tipoFacturaId,
        int $puntoventaId,
        string $leyenda,
        array $receptor,
        int $monedaId,
        array $articuloIds,
        array $cantidades,
        array $precios,
        array $descripciones,
        ?int $actividadArcaId,
        array $opcionalesPorItem = [],
        array $omitirStkmovAnitaPorItem = [],
    ): array {
        $payload = [
            'tipotransaccion_id' => (int) $tipoFacturaId,
            'puntoventa_id' => (int) $puntoventaId,
            'fechafactura' => now()->format('Y-m-d'),
            'leyendafactura' => $leyenda,
            'actividad_arca_id' => $actividadArcaId ?? (int) (Actividad_Arca::query()->orderBy('id')->value('id') ?? 1),
            'cliente_id' => $receptor['cliente_id'],
            'moneda_id' => $monedaId,
            'listaprecio_id' => (int) ($cfg->listaprecio_id ?? 1),
            'descuentolinea' => 0.,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descripcionarticulos' => $descripciones,
            'opcionales_por_item' => $opcionalesPorItem,
            'omitir_stkmov_anita_por_item' => $omitirStkmovAnitaPorItem,
        ];

        $this->receptorFacturacionService->aplicarReceptorAlPayloadFacturacion($payload, $receptor);

        return $payload;
    }

    private function leyendaCuenta(CuentaGastronomia $cuenta): string
    {
        if ($cuenta->tipo === CuentaGastronomia::TIPO_MESA && $cuenta->mesa) {
            return 'Mesa '.$cuenta->mesa->numeromesa.' '.$cuenta->mesa->nombre;
        }

        return 'Cuenta gastronomía #'.$cuenta->id;
    }

    /**
     * @return array{
     *   0:list<int>,
     *   1:list<float|string>,
     *   2:list<float|string>,
     *   3:list<string>,
     *   4:array<int, array<string|int, int|null>>,
     *   5:list<bool>
     * }
     */
    private function construirArraysFactura(CuentaGastronomia $cuenta): array
    {
        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];
        // Selección de opcionales por índice de item (solo para la línea padre,
        // no para las filas $0 que se agregan a continuación). Se usa para
        // resolver el coeficiente de impuesto interno por insumo CIGARRILLO.
        $opcionalesPorItem = [];
        // Bandera por renglón: true para los $0 visuales de opcionales (no deben
        // escribir stkmov en Anita; el stock real lo descuenta la expansión de
        // fórmula en el depósito de insumos vía GastronomiaInsumoStkmovAnitaService).
        $omitirStkmovAnita = [];

        foreach ($cuenta->lineas as $linea) {
            $pct = (float) $linea->descuento_linea_pct;
            $precioNet = (float) $linea->precio_unitario * (1 - $pct / 100);

            $indexPadre = count($articuloIds);
            $articuloIds[] = (int) $linea->articulo_id;
            $cantidades[] = (float) $linea->cantidad;
            $precios[] = $precioNet;
            $descripciones[] = '';
            $omitirStkmovAnita[] = false;

            $opcionalesLinea = is_array($linea->opcionales_json) ? $linea->opcionales_json : [];
            $opcionalesPorItem[$indexPadre] = [];
            foreach ($opcionalesLinea as $orden => $aid) {
                $opcionalesPorItem[$indexPadre][(string) $orden] = $aid !== null && $aid !== ''
                    ? (int) $aid
                    : null;
            }

            foreach ($opcionalesLinea as $aid) {
                if (! $aid) {
                    continue;
                }

                $articuloIds[] = (int) $aid;
                $cantidades[] = (float) $linea->cantidad;
                $precios[] = 0.;
                $descripciones[] = '';
                $omitirStkmovAnita[] = true;
            }
        }

        return [$articuloIds, $cantidades, $precios, $descripciones, $opcionalesPorItem, $omitirStkmovAnita];
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    private function adjuntarProfileEmision(
        array $resultado,
        ?GastronomiaEmisionProfiler $profiler,
        CuentaGastronomia $cuenta,
    ): array {
        $etapas = GastronomiaEmisionProfiler::finalizar($profiler, ['cuenta_id' => $cuenta->id]);
        if (
            $etapas !== null
            && filter_var(config('gastronomia.emision_profile_en_respuesta', false), FILTER_VALIDATE_BOOLEAN)
        ) {
            $resultado['emision_profile'] = $etapas;
            $resultado['emision_profile_total_ms'] = $etapas !== []
                ? (float) end($etapas)['acum_ms']
                : 0.;
        }

        return $resultado;
    }

    /**
     * @param  array{venta_id?:int,factura?:string,warn?:string,sin_cobranza?:bool,cobranza_id?:int}  $resultado
     * @return array{venta_id?:int,factura?:string,warn?:string,sin_cobranza?:bool,cobranza_id?:int,impresion_ticket?:string,impresion_ticket_mensaje?:string}
     */
    private function aplicarImpresionTicketTrasEmision(
        array $resultado,
        ConfiguracionPuntoventaGastronomia $cfg,
        CuentaGastronomia $cuenta,
    ): array {
        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        if ($ventaId <= 0) {
            return $resultado;
        }

        $imp = $this->facturaTicketService->imprimirTrasEmision($ventaId, $cfg, $cuenta);
        if (! empty($imp['omitida'])) {
            return $resultado;
        }

        if (! empty($imp['ok'])) {
            $resultado['impresion_ticket'] = 'ok';

            return $resultado;
        }

        $mensaje = trim((string) ($imp['mensaje'] ?? 'No se pudo imprimir el ticket térmico.'));
        $resultado['impresion_ticket'] = 'error';
        $resultado['impresion_ticket_mensaje'] = $mensaje;
        $warnPrevio = trim((string) ($resultado['warn'] ?? ''));
        $avisoTicket = 'Factura emitida; impresión térmica: '.$mensaje;
        $resultado['warn'] = $warnPrevio !== '' ? $warnPrevio."\n\n".$avisoTicket : $avisoTicket;

        return $resultado;
    }

    /**
     * Waitry fuera del request HTTP (defer Laravel): no retrasa emitir-factura ni la grabación en Anita.
     *
     * @param  array<string, mixed>  $resultado
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     */
    private function encolarWaitryTrasRespuesta(array $resultado, CuentaGastronomia $cuenta, array $mediosPago): void
    {
        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        if ($ventaId <= 0) {
            return;
        }

        $cuentaId = (int) $cuenta->id;
        $mediosPagoCopia = $mediosPago;

        defer(function () use ($resultado, $cuentaId, $mediosPagoCopia): void {
            $cuenta = CuentaGastronomia::query()->find($cuentaId);
            if ($cuenta === null) {
                Log::warning('gastronomia.waitry.defer.cuenta_inexistente', ['cuenta_id' => $cuentaId]);

                return;
            }

            try {
                $this->aplicarWaitryComandaTrasEmision($resultado, $cuenta, $mediosPagoCopia);
            } catch (Throwable $e) {
                Log::error('gastronomia.waitry.defer.excepcion', [
                    'cuenta_id' => $cuentaId,
                    'venta_id' => $resultado['venta_id'] ?? null,
                    'msg' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Envío a cocina Waitry: nunca revierte la factura; solo aviso en warn.
     *
     * @param  array{venta_id?:int,factura?:string,warn?:string,sin_cobranza?:bool,cobranza_id?:int,impresion_ticket?:string,impresion_ticket_mensaje?:string}  $resultado
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array<string, mixed>
     */
    private function aplicarWaitryComandaTrasEmision(
        array $resultado,
        CuentaGastronomia $cuenta,
        array $mediosPago,
    ): array {
        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        if ($ventaId <= 0) {
            return $resultado;
        }

        if (! config('waitry.habilitado', false)) {
            return $resultado;
        }

        $cuenta->refresh();
        $waitryOrderId = (int) ($cuenta->waitry_order_id ?? 0);
        $sinCobranza = ! empty($resultado['sin_cobranza']);
        $pagada = ! $sinCobranza && $mediosPago !== [];

        if ($waitryOrderId > 0) {
            if (isset($resultado['waitry_order_id']) === false) {
                $resultado['waitry_order_id'] = $waitryOrderId;
            }

            if ($pagada) {
                $resultado = $this->aplicarWaitrySyncPagoTrasEmision($resultado, $cuenta, $mediosPago);
            }

            return $resultado;
        }

        $facturaTxt = trim((string) ($resultado['factura'] ?? ''));
        try {
            $waitry = $this->waitryComandaService->enviarComandaTrasFactura(
                $ventaId,
                $cuenta,
                $facturaTxt,
                $pagada,
            );
        } catch (Throwable $e) {
            Log::error('gastronomia.waitry.excepcion', [
                'venta_id' => $ventaId,
                'msg' => $e->getMessage(),
            ]);
            $waitry = [
                'ok' => false,
                'mensaje' => 'Waitry: error inesperado al enviar comanda.',
            ];
        }

        if (! empty($waitry['omitida'])) {
            return $resultado;
        }

        if (! empty($waitry['ok'])) {
            $resultado['waitry_comanda'] = 'ok';
            if (isset($waitry['waitry_order_id'])) {
                $resultado['waitry_order_id'] = $waitry['waitry_order_id'];
            }

            return $resultado;
        }

        $mensaje = trim((string) ($waitry['mensaje'] ?? 'No se pudo enviar la comanda a Waitry.'));
        $resultado['waitry_comanda'] = 'error';
        $resultado['waitry_comanda_mensaje'] = $mensaje;
        $warnPrevio = trim((string) ($resultado['warn'] ?? ''));
        $aviso = 'Factura emitida; comanda Waitry: '.$mensaje;
        $resultado['warn'] = $warnPrevio !== '' ? $warnPrevio."\n\n".$aviso : $aviso;

        return $resultado;
    }

    /**
     * Orden importada desde Waitry: notifica cobro vía syncStatusPOS (cash | credit_card | debit_card).
     *
     * @param  array<string, mixed>  $resultado
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array<string, mixed>
     */
    private function aplicarWaitrySyncPagoTrasEmision(
        array $resultado,
        CuentaGastronomia $cuenta,
        array $mediosPago,
    ): array {
        try {
            $sync = $this->waitrySyncStatusPosService->sincronizarPagoTrasFactura($cuenta, $mediosPago);
        } catch (Throwable $e) {
            Log::error('gastronomia.waitry.sync_pago.excepcion', [
                'cuenta_id' => $cuenta->id,
                'waitry_order_id' => $cuenta->waitry_order_id,
                'msg' => $e->getMessage(),
            ]);
            $sync = [
                'ok' => false,
                'mensaje' => 'Waitry: error inesperado al registrar el pago.',
            ];
        }

        if (! empty($sync['omitida'])) {
            return $resultado;
        }

        if (! empty($sync['ok'])) {
            $resultado['waitry_pago'] = 'ok';

            return $resultado;
        }

        $mensaje = trim((string) ($sync['mensaje'] ?? 'No se pudo registrar el pago en Waitry.'));
        $resultado['waitry_pago'] = 'error';
        $resultado['waitry_pago_mensaje'] = $mensaje;
        $warnPrevio = trim((string) ($resultado['warn'] ?? ''));
        $aviso = 'Factura emitida; pago Waitry: '.$mensaje;
        $resultado['warn'] = $warnPrevio !== '' ? $warnPrevio."\n\n".$aviso : $aviso;

        return $resultado;
    }

    private function resolverVentaPorEtiqueta(int $puntoventaId, string $facturaTxt): ?Venta
    {
        $facturaTxt = trim($facturaTxt);
        if ($facturaTxt === '') {
            return null;
        }

        if (! preg_match('/^\S+\s+\S\s+(\d+)-(\d+)$/u', $facturaTxt, $m)) {
            return null;
        }

        $numero = (int) $m[2];

        return Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('numerocomprobante', $numero)
            ->orderByDesc('id')
            ->first();
    }
}
