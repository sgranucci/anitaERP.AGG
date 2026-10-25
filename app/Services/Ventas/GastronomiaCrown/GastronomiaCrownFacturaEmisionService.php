<?php

namespace App\Services\Ventas\GastronomiaCrown;

use App\Models\Configuracion\Actividad_Arca;
use App\Models\Configuracion\Condicioniva;
use App\Models\Stock\Formula_Articulo;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuenta;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuentaEvento;
use App\Models\Ventas\GastronomiaCrown\GastroCrownCuentaLinea;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Services\Ventas\Gastronomia\GastronomiaCobranzaService;
use App\Services\Ventas\Gastronomia\GastronomiaFacturacionService;
use App\Services\Ventas\Gastronomia\GastronomiaFormulaConsumoService;
use App\Services\Ventas\Gastronomia\GastronomiaFormulaOpcionalesService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaReceptorFacturacionService;
use App\Support\Stock\ArticuloSeleccionOperativaSupport;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use App\Support\Ventas\CaeaEmisionFechaCorrelatividadSupport;
use App\Support\Ventas\CaeaEmisionNumeracionSupport;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion;
use App\Support\Ventas\GastronomiaMovimientoStockSupport;
use App\Support\Ventas\PuntoventaEmisionLock;
use App\Support\Ventas\VentaNumerocomprobanteUnicidadSupport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Emisión fiscal ARCA del POS Crown: factura + cobranza + CAE/CAEA + cierre de cuenta en una transacción.
 *
 * Espeja {@see \App\Services\Ventas\Gastronomia\GastronomiaFacturaEmisionService} (AGG) en lo que aplica a
 * Crown: resiliencia CAE → CAEA ({@see ArcaWsfeEmisionResiliencia}), lock de punto de venta, reserva de
 * numeración CAEA en el ERP y consumo de fórmula tras el CAE.
 *
 * Fuera de alcance en Crown (no existen en su modelo de datos): Waitry/KDS externo, canjes de ticket /
 * fidelidad, descuentos `DescuentoGastronomia` (Crown usa un porcentaje en la cuenta) y receptor manual
 * sobre el tope de Consumidor Final (se exige cliente del maestro).
 */
final class GastronomiaCrownFacturaEmisionService
{
    public function __construct(
        private readonly GastronomiaFacturacionService $facturacionGastronomiaService,
        private readonly GastronomiaFormulaOpcionalesService $opcionalesService,
        private readonly GastronomiaFormulaConsumoService $consumoFormulaService,
        private readonly GastronomiaReceptorFacturacionService $receptorFacturacionService,
        private readonly GastronomiaCobranzaService $cobranzaGastronomiaService,
        private readonly GastronomiaJornadaService $jornadaService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $extra  actividad_arca_id, observacion_cobro, sin_cobranza
     * @return array{
     *     venta_id?:int,
     *     factura?:string,
     *     warn?:string,
     *     mensaje?:string,
     *     error?:string,
     *     clase_error?:string,
     *     sin_cobranza?:bool,
     *     cobranza_id?:int,
     *     totales?:array<string, float>
     * }
     */
    public function emitirDesdeCuenta(
        GastroCrownCuenta $cuenta,
        ConfiguracionPuntoventaGastronomia $cfg,
        string $tipoCobro,
        array $extra = [],
        bool $forzarPvCaea = false,
    ): array {
        $cuenta->loadMissing([
            'lineas.articulo.formula_articulo.formula_articulo_hijos',
            'cliente',
            'mesa',
        ]);

        if (! in_array($cuenta->estado, [GastroCrownCuenta::ESTADO_ABIERTA, GastroCrownCuenta::ESTADO_COBRANDO], true)) {
            return ['error' => 'La cuenta no está abierta.'];
        }

        $lineasCobrables = $cuenta->lineas
            ->reject(fn (GastroCrownCuentaLinea $l) => (bool) $l->es_separador)
            ->values();

        if ($lineasCobrables->isEmpty()) {
            return ['error' => 'La cuenta no tiene ítems para cobrar.'];
        }

        $mensajeInactivos = $this->mensajeArticulosInactivos($cuenta);
        if ($mensajeInactivos !== null) {
            return ['error' => $mensajeInactivos];
        }

        $erroresOpcionales = $this->opcionalesService->erroresOpcionalesEnLineas($lineasCobrables);
        if ($erroresOpcionales !== []) {
            return ['error' => $erroresOpcionales[0]];
        }

        $empresaId = (int) $cuenta->empresa_id;

        $tipoFacturaId = (int) ($cfg->tipotransaccion_id ?? 0);
        if ($tipoFacturaId <= 0) {
            $tipoFacturaId = (int) config('gastronomia.tipotransaccion_factura_id', 0);
        }
        if ($tipoFacturaId <= 0) {
            return ['error' => 'Configure el tipo de transacción (factura) en la configuración del punto de venta gastronomía de esta terminal.'];
        }

        try {
            $pvResolucion = ArcaWsfeEmisionResiliencia::resolverPuntoventaEmision(
                (int) $cfg->puntoventa_cae_id,
                (int) $cfg->puntoventa_caea_id,
                $forzarPvCaea,
                null,
                (int) $cfg->empresa_id,
            );
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        $puntoventaId = (int) $pvResolucion['puntoventa_id'];
        $usaCaea = (bool) $pvResolucion['usa_caea'];
        if ($puntoventaId <= 0) {
            return [
                'error' => $usaCaea
                    ? 'Configure el punto de venta CAEA en la configuración del punto de venta gastronomía de esta terminal.'
                    : 'Configure el punto de venta electrónico (CAE) en la configuración del punto de venta gastronomía de esta terminal.',
            ];
        }

        $totales = $this->totalesCuenta($cuenta);
        [$articuloIds, $cantidades, $precios, $descripciones, $opcionalesPorItem, $omitirStkmovAnita]
            = $this->construirArraysFactura($lineasCobrables);

        try {
            $receptor = $this->receptorFacturacionService->resolverParaFacturarPorClienteId(
                $cuenta->cliente_id !== null ? (int) $cuenta->cliente_id : null,
                $totales['total'],
            );
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        $payload = $this->armarPayloadFactura(
            $cfg,
            $cuenta,
            $tipoFacturaId,
            $puntoventaId,
            $receptor,
            $totales,
            $articuloIds,
            $cantidades,
            $precios,
            $descripciones,
            $opcionalesPorItem,
            $omitirStkmovAnita,
            isset($extra['actividad_arca_id']) ? (int) $extra['actividad_arca_id'] : null,
        );

        $payload = $this->aplicarFechasAlPayload(
            $payload,
            $empresaId,
            $cfg->sala_id !== null ? (int) $cfg->sala_id : null,
        );

        $monedaId = (int) config('gastronomia.moneda_factura_id', 1) ?: 1;
        $mediosPago = [];
        $sinCobranza = $totales['factura_cortesia'] || ! $this->tipoCobroRegistraEfectivo($tipoCobro, $extra);

        if (! $sinCobranza) {
            $errorCuentacaja = GastronomiaCuentacajaEfectivo::mensajeErrorResolucion($empresaId);
            if ($errorCuentacaja !== null) {
                return ['error' => $errorCuentacaja];
            }

            $cuentacaja = GastronomiaCuentacajaEfectivo::cuentaParaEmpresa($empresaId);
            if ($cuentacaja === null) {
                return [
                    'error' => 'No se pudo resolver la cuenta de caja de efectivo de la empresa '.$empresaId
                        .' (GASTRONOMIA_CUENTACAJA_EFECTIVO_POR_EMPRESA).',
                ];
            }

            $mediosPago = [[
                'cuentacaja_id' => (int) $cuentacaja['id'],
                'moneda_id' => (int) ($cuentacaja['moneda_id'] ?: $monedaId),
                'monto' => $totales['total'],
                'observacion' => 'POS Crown cuenta #'.$cuenta->id.' ('.$tipoCobro.')',
            ]];

            if (config('gastronomia.genera_contabilidad_al_facturar', true)) {
                $payload['_asiento_medios_pago'] = $mediosPago;
            }
        }

        $puntoventa = Puntoventa::query()->find($puntoventaId);
        $emisionCaea = $puntoventa !== null && ($puntoventa->modofacturacion ?? '') === 'A';
        $tipoFacturaCaea = $emisionCaea ? Tipotransaccion::query()->find($tipoFacturaId) : null;
        if ($emisionCaea && $tipoFacturaCaea === null) {
            return ['error' => 'Tipo de transacción de factura inexistente.'];
        }

        try {
            $lockPv = PuntoventaEmisionLock::adquirir($puntoventaId);
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        $resultado = null;
        $fallo = null;

        try {
            if ($emisionCaea) {
                $errorReserva = CaeaEmisionNumeracionSupport::aplicarReservaNumeracionAlPayload(
                    $payload,
                    $puntoventa,
                    $tipoFacturaCaea,
                    $this->letraComprobanteDesdePayload($payload),
                    true,
                );
                if ($errorReserva !== null) {
                    return ['error' => $errorReserva];
                }
            }

            $resultado = $this->ejecutarConReintentoNumeracion(
                $cuenta,
                $cfg,
                $payload,
                $mediosPago,
                $tipoCobro,
                $tipoFacturaId,
                $puntoventaId,
                $monedaId,
                $totales,
                $extra,
                $emisionCaea ? $puntoventa : null,
                $tipoFacturaCaea,
            );
        } catch (Throwable $e) {
            $fallo = $e;
        } finally {
            PuntoventaEmisionLock::liberar($lockPv);
        }

        // Reintento CAEA fuera del lock del PV CAE: el PV de contingencia tiene su propia clave.
        if ($fallo !== null) {
            return $this->manejarFalloEmision(
                $fallo,
                $cuenta,
                $cfg,
                $tipoCobro,
                $extra,
                $puntoventaId,
                $usaCaea,
                $forzarPvCaea,
            );
        }

        if (! is_array($resultado)) {
            return ['error' => 'La emisión no devolvió resultado.'];
        }

        $resultado = $this->completarAnitaPostCommit($resultado);

        $facturaTxt = trim((string) ($resultado['factura'] ?? ''));
        $resultado['mensaje'] = 'Factura '.$facturaTxt.' emitida correctamente.';
        $resultado['totales'] = [
            'subtotal' => $totales['subtotal'],
            'descuento' => $totales['descuento'],
            'total' => $totales['total'],
            'pct' => $totales['pct'],
        ];

        return $resultado;
    }

    /**
     * Reintento único de numeración CAEA ante colisión del índice de `numerocomprobante`.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $mediosPago
     * @param  array{subtotal:float,descuento:float,total:float,pct:float,factura_cortesia:bool}  $totales
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function ejecutarConReintentoNumeracion(
        GastroCrownCuenta $cuenta,
        ConfiguracionPuntoventaGastronomia $cfg,
        array $payload,
        array $mediosPago,
        string $tipoCobro,
        int $tipoFacturaId,
        int $puntoventaId,
        int $monedaId,
        array $totales,
        array $extra,
        ?Puntoventa $puntoventaCaea,
        ?Tipotransaccion $tipoFacturaCaea,
    ): array {
        $reintentos = 0;

        while (true) {
            try {
                return DB::transaction(fn () => $this->ejecutarEmisionEnTransaccion(
                    $cuenta,
                    $cfg,
                    $payload,
                    $mediosPago,
                    $tipoCobro,
                    $tipoFacturaId,
                    $monedaId,
                    $totales,
                    $extra,
                ));
            } catch (QueryException $e) {
                if (
                    $puntoventaCaea === null
                    || $tipoFacturaCaea === null
                    || $reintentos >= 1
                    || ! VentaNumerocomprobanteUnicidadSupport::esViolacionNumerocomprobante($e)
                ) {
                    throw $e;
                }

                $errorRenumeracion = VentaNumerocomprobanteUnicidadSupport::renumerarPayloadCaeaTrasColision(
                    $payload,
                    $puntoventaCaea,
                    $tipoFacturaCaea,
                    $this->letraComprobanteDesdePayload($payload),
                    true,
                );
                if ($errorRenumeracion !== null) {
                    throw new InvalidArgumentException($errorRenumeracion, 0, $e);
                }

                $reintentos++;
                Log::warning('gastronomia_crown.emitir_factura.numeracion_duplicada_reintento', [
                    'cuenta_id' => $cuenta->id,
                    'puntoventa_id' => $puntoventaId,
                    'numerocomprobante' => (int) ($payload['numerocomprobante_forzado'] ?? 0),
                    'intento' => $reintentos,
                ]);
            }
        }
    }

    /**
     * Factura → cobranza → CAE/CAEA → insumos → cierre de cuenta. Si ARCA falla, rollback total.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $mediosPago
     * @param  array{subtotal:float,descuento:float,total:float,pct:float,factura_cortesia:bool}  $totales
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function ejecutarEmisionEnTransaccion(
        GastroCrownCuenta $cuenta,
        ConfiguracionPuntoventaGastronomia $cfg,
        array $payload,
        array $mediosPago,
        string $tipoCobro,
        int $tipoFacturaId,
        int $monedaId,
        array $totales,
        array $extra,
    ): array {
        $ventaAnitaRevertir = null;

        $cuenta = GastroCrownCuenta::query()
            ->whereKey($cuenta->id)
            ->lockForUpdate()
            ->firstOrFail();

        try {
            $resultado = $this->facturacionGastronomiaService->emitirComprobanteSinCuentaGastronomia(
                $payload,
                (int) $cuenta->empresa_id,
                $this->facturaComoConsumidorFinal($cuenta),
                preservarFechasExplicitas: true,
            );

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
                throw new RuntimeException(
                    'No se pudo recuperar la venta interna tras emitir el comprobante '.$facturaTxt
                    .'. Revise ARCA y la tabla venta antes de reintentar.'
                );
            }

            if (config('gastronomia.sincronizar_anita_al_facturar', true) && empty($resultado['anita_pendiente'])) {
                $ventaAnitaRevertir = $venta;
            }

            $sinCobranza = ! empty($resultado['sin_cobranza']) || $mediosPago === [];
            $cobranzaId = null;

            if (! $sinCobranza) {
                $cobRes = $this->cobranzaGastronomiaService->registrarCobranzaPos(
                    $venta->fresh(),
                    $mediosPago,
                    $cfg,
                );
                $cobranzaId = isset($cobRes['cobranza_id']) ? (int) $cobRes['cobranza_id'] : null;
            }

            $vencaePendiente = null;
            if (! empty($resultado['cae_pendiente']) && is_array($resultado['cae_pendiente'])) {
                $vencaePendiente = $this->facturacionGastronomiaService
                    ->completarSolicitudCaePendiente($resultado['cae_pendiente']);
            }

            // Insumos después del CAE: el lock de articulo_saldo_deposito no se sostiene durante el SOAP a ARCA.
            $tipo = Tipotransaccion::query()->find($tipoFacturaId);
            $this->consumoFormulaService->registrarMovimientosIngredientesDesdeVentaEmitida(
                $venta,
                $cfg,
                $tipoFacturaId,
                $tipo !== null ? (string) ($tipo->nombre ?? 'Venta') : 'Venta',
                (string) $payload['fechafactura'],
                $monedaId,
                (string) ($payload['fechajornada'] ?? $payload['fechafactura']),
            );

            $cuenta->venta_id = (int) $venta->id;
            $cuenta->estado = GastroCrownCuenta::ESTADO_CERRADA;
            $cuenta->tipo_cobro = $tipoCobro;
            $cuenta->importe_cobrado = $sinCobranza ? 0.0 : $totales['total'];
            $cuenta->cerrada_at = now();
            if (! empty($extra['observacion_cobro'])) {
                $cuenta->observacion = mb_substr(
                    trim(($cuenta->observacion ? $cuenta->observacion.' | ' : '').$extra['observacion_cobro']),
                    0,
                    255,
                );
            }
            $cuenta->save();

            GastroCrownCuentaEvento::query()->create([
                'gastro_crown_cuenta_id' => $cuenta->id,
                'evento' => 'pay',
                'usuario_id' => Auth::id(),
                'detalle' => strtoupper($tipoCobro).' '.$facturaTxt
                    .' $ '.number_format($totales['total'], 2, ',', '.'),
                'created_at' => now(),
            ]);

            $ventaAnitaRevertir = null;

            $warn = ArcaWsfeEmisionResiliencia::mensajeAvisoModoCaeaForzado(null, (int) $cuenta->empresa_id);
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
                'venta_id' => (int) $venta->id,
                'factura' => $facturaTxt,
                'warn' => $warn,
                'sin_cobranza' => $sinCobranza,
                'cobranza_id' => $cobranzaId,
                'anita_pendiente' => $resultado['anita_pendiente'] ?? null,
                'vencae_pendiente' => $vencaePendiente,
            ], fn ($v) => $v !== null && $v !== '' && $v !== false);
        } catch (Throwable $e) {
            if ($ventaAnitaRevertir !== null) {
                $this->facturacionGastronomiaService->revertirVentaEnAnitaSiHabilitado($ventaAnitaRevertir);
            }

            throw $e;
        }
    }

    /**
     * Mismo criterio que AGG: transporte ARCA → notificar failover y reintentar la transacción con PV CAEA.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function manejarFalloEmision(
        Throwable $e,
        GastroCrownCuenta $cuenta,
        ConfiguracionPuntoventaGastronomia $cfg,
        string $tipoCobro,
        array $extra,
        int $puntoventaId,
        bool $usaCaea,
        bool $forzarPvCaea,
    ): array {
        $claseError = ArcaWsfeEmisionResiliencia::clasificarError($e->getMessage());

        if ($claseError === ArcaWsfeEmisionResiliencia::CLASE_TRANSPORTE) {
            ArcaWsfeEmisionResiliencia::notificarFallaTransporteEmision(
                $e->getMessage(),
                null,
                ['probe' => 'gastronomia_crown.emitir_factura'],
            );
        }

        Log::error('gastronomia_crown.emitir_factura.fallo', [
            'cuenta_id' => $cuenta->id,
            'pv_intentado' => $puntoventaId,
            'usa_caea' => $usaCaea,
            'clase_error' => $claseError,
            'msg' => $e->getMessage(),
        ]);

        if (! $forzarPvCaea && ArcaWsfeEmisionResiliencia::debeReintentarTransaccionConCaea($e->getMessage(), $usaCaea)) {
            Log::warning('gastronomia_crown.emitir_factura.reintento_caea', [
                'cuenta_id' => $cuenta->id,
                'pv_cae_fallado' => $puntoventaId,
                'clase_error' => $claseError,
                'motivo' => 'error de '.ArcaWsfeEmisionResiliencia::etiquetaClaseError($claseError),
            ]);

            return $this->emitirDesdeCuenta($cuenta->fresh() ?? $cuenta, $cfg, $tipoCobro, $extra, true);
        }

        return [
            'error' => GastronomiaMovimientoStockSupport::mensajeErrorEmision($e, [
                'intento_caea' => $forzarPvCaea || $usaCaea,
                'reintento_caea_habilitado' => ArcaWsfeEmisionResiliencia::reintentarCaeaSiFallaComunicacion(),
            ]),
            'clase_error' => $claseError,
        ];
    }

    /**
     * Replicación Informix post-commit: nunca revierte la factura; solo agrega aviso en `warn`.
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    private function completarAnitaPostCommit(array $resultado): array
    {
        try {
            if (! empty($resultado['anita_pendiente']) && is_array($resultado['anita_pendiente'])) {
                $this->facturacionGastronomiaService->ejecutarAnitaPendienteGastronomia($resultado['anita_pendiente']);
            }

            if (! empty($resultado['vencae_pendiente']) && is_array($resultado['vencae_pendiente'])) {
                $this->facturacionGastronomiaService->ejecutarVencaePendienteGastronomia($resultado['vencae_pendiente']);
            }
        } catch (Throwable $e) {
            Log::error('gastronomia_crown.emitir_factura.post_commit.fallo', [
                'venta_id' => $resultado['venta_id'] ?? null,
                'msg' => $e->getMessage(),
            ]);
            $aviso = 'Factura emitida con CAE; falló la replicación en Anita: '.$e->getMessage();
            $warnPrevio = trim((string) ($resultado['warn'] ?? ''));
            $resultado['warn'] = $warnPrevio !== '' ? $warnPrevio."\n\n".$aviso : $aviso;
        }

        unset($resultado['anita_pendiente'], $resultado['vencae_pendiente']);

        return $resultado;
    }

    /**
     * Fecha fiscal = día calendario; fecha de jornada = jornada abierta de la sala si existe
     * (no se exige para no frenar el POS). Correlatividad CAEA igual que AGG.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function aplicarFechasAlPayload(array $payload, int $empresaId, ?int $salaId = null): array
    {
        $hoy = now()->format('Y-m-d');
        $jornada = $this->jornadaService->jornadaAbierta($empresaId, $salaId);

        $payload['fechafactura'] = $hoy;
        $payload['fechajornada'] = $jornada?->fecha_jornada?->format('Y-m-d') ?? $hoy;
        if ($jornada !== null) {
            $payload['jornada_gastronomia_id'] = (int) $jornada->id;
        }

        return CaeaEmisionFechaCorrelatividadSupport::aplicarAlPayload($payload);
    }

    /**
     * @param  array{cliente_id:int}  $receptor
     * @param  array{subtotal:float,descuento:float,total:float,pct:float,factura_cortesia:bool}  $totales
     * @param  list<int>  $articuloIds
     * @param  list<float>  $cantidades
     * @param  list<float>  $precios
     * @param  list<string>  $descripciones
     * @param  array<int, array<string, mixed>>  $opcionalesPorItem
     * @param  list<bool>  $omitirStkmovAnita
     * @return array<string, mixed>
     */
    private function armarPayloadFactura(
        ConfiguracionPuntoventaGastronomia $cfg,
        GastroCrownCuenta $cuenta,
        int $tipoFacturaId,
        int $puntoventaId,
        array $receptor,
        array $totales,
        array $articuloIds,
        array $cantidades,
        array $precios,
        array $descripciones,
        array $opcionalesPorItem,
        array $omitirStkmovAnita,
        ?int $actividadArcaId,
    ): array {
        $payload = [
            'tipotransaccion_id' => $tipoFacturaId,
            'puntoventa_id' => $puntoventaId,
            'fechafactura' => now()->format('Y-m-d'),
            'leyendafactura' => $this->leyendaCuenta($cuenta),
            'actividad_arca_id' => $actividadArcaId
                ?: (int) (Actividad_Arca::query()->orderBy('id')->value('id') ?? 1),
            'cliente_id' => (int) $receptor['cliente_id'],
            'moneda_id' => (int) config('gastronomia.moneda_local_id', 1),
            'listaprecio_id' => (int) ($cuenta->listaprecio_id ?: $cfg->listaprecio_id ?: 1),
            'descuentolinea' => 0.,
            'descuentopie' => $totales['descuentopie'],
            'descuentoimportepie' => 0.,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descripcionarticulos' => $descripciones,
            'opcionales_por_item' => $opcionalesPorItem,
            'omitir_stkmov_anita_por_item' => $omitirStkmovAnita,
        ];

        if ($totales['factura_cortesia']) {
            $payload['factura_cortesia_total'] = true;
        }

        $this->receptorFacturacionService->aplicarReceptorAlPayloadFacturacion($payload, $receptor);

        return $payload;
    }

    /**
     * Totales fiscales de la cuenta Crown. El descuento de mesa (porcentaje) viaja como descuento de pie;
     * 100 % se emite como factura de cortesía ($0,01), igual que AGG.
     *
     * @return array{
     *     subtotal:float,
     *     descuento:float,
     *     total:float,
     *     pct:float,
     *     descuentopie:float,
     *     factura_cortesia:bool
     * }
     */
    private function totalesCuenta(GastroCrownCuenta $cuenta): array
    {
        $cuenta->loadMissing('lineas');

        $subtotal = 0.0;
        foreach ($cuenta->lineas as $linea) {
            if ($linea->es_separador) {
                continue;
            }
            $subtotal += (float) $linea->cantidad * (float) $linea->precio - (float) $linea->descuento_importe;
        }
        $subtotal = round(max(0.0, $subtotal), 2);

        $pct = max(0.0, min(100.0, (float) ($cuenta->porcentaje_descuento ?? 0)));
        $minimo = GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA;
        $cortesia = $pct >= 100.0 && $subtotal > 0.0;

        if ($cortesia) {
            return [
                'subtotal' => $subtotal,
                'descuento' => round($subtotal - $minimo, 2),
                'total' => $minimo,
                'pct' => $pct,
                'descuentopie' => $subtotal > $minimo ? 100.0 * (1.0 - $minimo / $subtotal) : 0.0,
                'factura_cortesia' => true,
            ];
        }

        $descuento = round($subtotal * ($pct / 100.0), 2);

        return [
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'total' => round(max(0.0, $subtotal - $descuento), 2),
            'pct' => $pct,
            'descuentopie' => $pct,
            'factura_cortesia' => false,
        ];
    }

    /**
     * Padres y opcionales elegidos: ninguno puede facturarse si el maestro está inactivo.
     */
    public function mensajeArticulosInactivos(GastroCrownCuenta $cuenta): ?string
    {
        $cuenta->loadMissing('lineas');
        $ids = [];
        foreach ($cuenta->lineas as $linea) {
            if ($linea->es_separador || ! $linea->articulo_id) {
                continue;
            }
            $ids[] = (int) $linea->articulo_id;
            foreach ($linea->opcionales_json as $valor) {
                $opcionalId = $this->articuloIdDeOpcional($valor);
                if ($opcionalId !== null) {
                    $ids[] = $opcionalId;
                }
            }
        }

        $mensajes = ArticuloSeleccionOperativaSupport::mensajesNoFacturablesPorIds($ids);

        return $mensajes === [] ? null : implode(' ', $mensajes);
    }

    /**
     * Renglones de factura: ítem padre con precio neto de su descuento de línea y, a continuación,
     * un renglón $0 por cada opcional elegido (igual que AGG, para que figure en el comprobante).
     *
     * @param  \Illuminate\Support\Collection<int, GastroCrownCuentaLinea>  $lineas
     * @return array{0:list<int>,1:list<float>,2:list<float>,3:list<string>,4:array<int, array<string, mixed>>,5:list<bool>}
     */
    private function construirArraysFactura($lineas): array
    {
        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];
        $opcionalesPorItem = [];
        $omitirStkmovAnita = [];

        foreach ($lineas as $linea) {
            $cantidad = (float) $linea->cantidad;
            $descuentoLinea = (float) $linea->descuento_importe;
            $precioNet = (float) $linea->precio;
            if ($cantidad > 0 && $descuentoLinea > 0) {
                $precioNet = max(0.0, $precioNet - $descuentoLinea / $cantidad);
            }

            $indexPadre = count($articuloIds);
            $articuloIds[] = (int) $linea->articulo_id;
            $cantidades[] = $cantidad;
            $precios[] = round($precioNet, 4);
            $descripciones[] = '';
            $omitirStkmovAnita[] = false;

            $opcionalesLinea = $linea->opcionales_json;
            $opcionalesPorItem[$indexPadre] = [];
            foreach ($opcionalesLinea as $orden => $valor) {
                $opcionalesPorItem[$indexPadre][(string) $orden] = GastronomiaFormulaOpcionalSeleccion::estaVacio($valor)
                    ? null
                    : $valor;
            }

            foreach ($opcionalesLinea as $valor) {
                $articuloOpcionalId = $this->articuloIdDeOpcional($valor);
                if ($articuloOpcionalId === null) {
                    continue;
                }

                $articuloIds[] = $articuloOpcionalId;
                $cantidades[] = $cantidad;
                $precios[] = 0.;
                $descripciones[] = '';
                $omitirStkmovAnita[] = true;
            }
        }

        return [$articuloIds, $cantidades, $precios, $descripciones, $opcionalesPorItem, $omitirStkmovAnita];
    }

    private function articuloIdDeOpcional(mixed $valor): ?int
    {
        $decoded = GastronomiaFormulaOpcionalSeleccion::decodificar($valor);
        if ($decoded === null) {
            return null;
        }

        $articuloId = null;
        if ($decoded['tipo'] === 'articulo') {
            $articuloId = (int) $decoded['id'];
        } elseif ($decoded['tipo'] === 'formula_hija') {
            $sub = Formula_Articulo::query()->find($decoded['id']);
            if ($sub && $sub->articulo_id) {
                $articuloId = (int) $sub->articulo_id;
            }
        }

        return $articuloId !== null && $articuloId > 0 ? $articuloId : null;
    }

    /**
     * Crown no tiene receptor manual ni cliente interno de descuento: CF salvo cliente del maestro válido.
     */
    private function facturaComoConsumidorFinal(GastroCrownCuenta $cuenta): bool
    {
        return $this->receptorFacturacionService->normalizarClienteIdFacturacion(
            (int) ($cuenta->cliente_id ?? 0),
        ) === null;
    }

    /**
     * Tipos de cobro que generan movimiento de caja (efectivo). Interna / hotel facturan sin cobranza:
     * el consumo se imputa al centro interno o a la habitación.
     *
     * @param  array<string, mixed>  $extra
     */
    private function tipoCobroRegistraEfectivo(string $tipoCobro, array $extra): bool
    {
        if (! empty($extra['sin_cobranza'])) {
            return false;
        }

        $tipos = config('gastronomia.crown.tipos_cobro_con_cobranza_efectivo', []);

        return is_array($tipos) && in_array($tipoCobro, array_map('strval', $tipos), true);
    }

    private function leyendaCuenta(GastroCrownCuenta $cuenta): string
    {
        if ($cuenta->mesa !== null) {
            return trim('Mesa '.$cuenta->mesa->codigo.' '.$cuenta->mesa->nombre);
        }

        return 'Cuenta gastronomía Crown #'.$cuenta->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function letraComprobanteDesdePayload(array $payload): string
    {
        $clienteId = (int) ($payload['cliente_id'] ?? 0);
        $letra = 'B';

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
