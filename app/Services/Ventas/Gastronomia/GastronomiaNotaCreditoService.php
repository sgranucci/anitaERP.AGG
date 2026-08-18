<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Configuracion\Actividad_Arca;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Models\Ventas\VentaGastronomiaNcOrigen;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaColaSupport;
use App\Support\Ventas\Gastronomia\GastronomiaFacturaItemsPayloadSupport;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use App\Support\Ventas\GastronomiaEmisionProfiler;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\GastronomiaMovimientoStockSupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\NcjetdirectSalidaSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Emite nota de crédito desde el informe Facturas del día (gastronomía).
 * Réplica FacturacionService (signo negativo en ERP) y Anita (importes positivos).
 */
final class GastronomiaNotaCreditoService
{
    public function __construct(
        private readonly GastronomiaFacturacionService $facturacionGastronomiaService,
        private readonly GastronomiaFormulaConsumoService $consumoFormulaService,
        private readonly GastronomiaCobranzaService $cobranzaGastronomiaService,
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly GastronomiaTurnoOperativoService $turnoOperativoService,
        private readonly GastronomiaInsumoStkmovAnitaService $insumoStkmovAnitaService,
        private readonly GastronomiaCuentaService $cuentaService,
        private readonly GastronomiaFacturaTicketService $facturaTicketService,
    ) {
    }

    /**
     * @param  string  $leyendaUsuario  Leyenda manual ingresada en el modal; se graba como leyenda del comprobante NC.
     * @param  array{
     *   ajuste_fiscal?:bool,
     *   fecha_factura?:string,
     *   fecha_jornada?:string,
     *   identificador_pc?:string,
     *   omitir_stock?:bool,
     *   omitir_impresion?:bool
     * }  $opciones
     * @return array{ok:bool,venta_id?:int,factura?:string,mensaje?:string,error?:string}
     */
    public function generarDesdeFactura(
        int $ventaFacturaId,
        ?Request $request = null,
        string $leyendaUsuario = '',
        array $opciones = [],
    ): array {
        $profiler = GastronomiaEmisionProfiler::iniciarSiConfigurado();
        $ajusteFiscal = ! empty($opciones['ajuste_fiscal']);
        $fechaFacturaForzada = trim((string) ($opciones['fecha_factura'] ?? ''));
        $fechaJornadaForzada = trim((string) ($opciones['fecha_jornada'] ?? ''));
        if ($ajusteFiscal) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFacturaForzada)
                || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaJornadaForzada)) {
                return ['ok' => false, 'error' => 'El ajuste fiscal requiere fecha de comprobante y jornada válidas.'];
            }
        }

        $emision = VentaGastronomiaEmision::query()
            ->where('venta_id', $ventaFacturaId)
            ->with([
                'venta.clientes',
                'venta.puntoventas',
                'venta.venta_emisiones.articulos',
                'venta.tipotransacciones',
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
                'cuenta',
                'configuracionPuntoventa',
            ])
            ->first();

        if (! $emision || ! $emision->venta) {
            return ['ok' => false, 'error' => 'La venta no corresponde a una emisión gastronomía.'];
        }

        if ($emision->venta_factura_origen_id !== null) {
            return ['ok' => false, 'error' => 'No puede generar nota de crédito sobre otro comprobante de ajuste.'];
        }

        if (self::notaCreditoExistenteParaFactura($ventaFacturaId) !== null) {
            return ['ok' => false, 'error' => 'Ya existe una nota de crédito para esta factura.'];
        }

        $ventaOrigen = $emision->venta;
        if ((float) $ventaOrigen->total < 0.01) {
            return ['ok' => false, 'error' => 'El comprobante no es una factura con importe positivo.'];
        }

        $tipoFactura = $ventaOrigen->tipotransacciones;
        if ($tipoFactura && $tipoFactura->signo !== 'S') {
            return ['ok' => false, 'error' => 'El comprobante origen no es una factura de venta.'];
        }

        $cfg = $this->cuentaService->resolverConfiguracionPv($request)
            ?? $emision->configuracionPuntoventa;

        if (! $cfg) {
            return ['ok' => false, 'error' => 'No hay configuración de punto de venta gastronomía para esta terminal.'];
        }

        $empresaId = (int) $cfg->empresa_id;
        $identificadorPc = trim((string) ($opciones['identificador_pc'] ?? ''));
        if ($identificadorPc === '') {
            $identificadorPc = trim((string) ($emision->identificador_pc ?? ''));
        }
        if ($identificadorPc === '') {
            $identificadorPc = GastronomiaIdentificadorPc::resolver($request);
        }

        if (! $ajusteFiscal) {
            try {
                if (config('gastronomia.jornada_obligatoria', true)) {
                    $this->jornadaService->exigirJornadaAbierta($empresaId);
                }
                $this->turnoOperativoService->exigirTurnoHabilitadoSiConfigurado($identificadorPc, $empresaId);
            } catch (InvalidArgumentException $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        }

        $tipoNcId = self::resolverTipotransaccionNotaCreditoId($cfg);
        $tipoNc = Tipotransaccion::query()->find($tipoNcId);
        if (! $tipoNc) {
            return ['ok' => false, 'error' => 'Tipo de transacción de nota de crédito inexistente.'];
        }
        if ($tipoNc->signo === 'S') {
            return ['ok' => false, 'error' => 'El tipo de transacción configurado para nota de crédito debe tener signo Resta.'];
        }

        $cuenta = $emision->cuenta;
        if (! $cuenta instanceof CuentaGastronomia) {
            return ['ok' => false, 'error' => 'No se encontró la cuenta gastronómica asociada a la factura.'];
        }

        $cuenta->loadMissing('lineas');
        $payload = $this->armarPayloadNotaCredito(
            $ventaOrigen,
            $tipoNcId,
            $cfg,
            $leyendaUsuario,
            $cuenta,
            $ajusteFiscal ? $fechaFacturaForzada : null,
            $ajusteFiscal ? $fechaJornadaForzada : null,
        );
        $mediosPago = $this->armarMediosCobranzaDevolucion($ventaOrigen);

        $profiler?->marcar('preparacion_payload');

        try {
            $profiler?->marcar('antes_transaccion');
            $resultadoTx = DB::transaction(function () use (
                $ventaFacturaId,
                $ventaOrigen,
                $cuenta,
                $cfg,
                $tipoNcId,
                $tipoNc,
                $payload,
                $mediosPago,
                $profiler,
                $identificadorPc,
                $ajusteFiscal,
                $opciones,
            ) {
                $ventaAnitaRevertir = null;
                $vencaePendiente = null;

                $profiler?->marcar('nc_emitir_comprobante_inicio');
                $resultado = $this->facturacionGastronomiaService->emitirComprobante(
                    $payload,
                    $cuenta,
                    $ajusteFiscal,
                );
                $profiler?->marcar('nc_emitir_comprobante_fin');

                if (! empty($resultado['error'])) {
                    throw new InvalidArgumentException((string) ($resultado['mensaje'] ?? $resultado['error']));
                }

                $ventaNc = $this->resolverVentaEmitida($ventaOrigen, $resultado);
                if (config('gastronomia.sincronizar_anita_al_facturar', true)) {
                    $ventaAnitaRevertir = $ventaNc;
                }

                $sinCobranza = ! empty($resultado['sin_cobranza']);
                if (! $sinCobranza && $mediosPago !== []) {
                    $profiler?->marcar('nc_cobranza_inicio');
                    $this->cobranzaGastronomiaService->registrarCobranzaPos(
                        $ventaNc->fresh(),
                        $mediosPago,
                        $cfg,
                        esDevolucion: true,
                    );
                    $profiler?->marcar('nc_cobranza_fin');
                }

                if (empty($opciones['omitir_stock'])) {
                    $nombreTipo = (string) ($tipoNc->nombre ?? 'Nota de crédito');
                    $profiler?->marcar('nc_reverso_stock_inicio');
                    $this->consumoFormulaService->revertirMovimientosStockDesdeFactura(
                        $ventaNc,
                        $ventaOrigen,
                        $cfg,
                        $tipoNcId,
                        $nombreTipo,
                        (string) $payload['fechafactura'],
                        (int) $ventaOrigen->moneda_id,
                        (string) ($payload['fechajornada'] ?? $payload['fechafactura']),
                    );
                    $profiler?->marcar('nc_reverso_stock_fin');
                }

                if (! $ajusteFiscal) {
                    $profiler?->marcar('nc_anita_insumos_inicio');
                    $this->insumoStkmovAnitaService->replicarMovimientosInsumos(
                        $ventaNc->fresh(),
                        $cfg,
                        (float) ($payload['descuentopie'] ?? 0),
                    );
                    $profiler?->marcar('nc_anita_insumos_fin');
                }

                if (! empty($resultado['cae_pendiente']) && is_array($resultado['cae_pendiente'])) {
                    $profiler?->marcar('nc_cae_diferido_inicio');
                    $vencaePendiente = $this->facturacionGastronomiaService->completarSolicitudCaePendiente($resultado['cae_pendiente']);
                    $profiler?->marcar('nc_cae_diferido_fin');
                }

                VentaGastronomiaEmision::updateOrCreate(
                    ['venta_id' => $ventaNc->id],
                    array_merge([
                        'cuenta_gastronomia_id' => $cuenta->id,
                        'identificador_pc' => $identificadorPc,
                        'configuracion_puntoventa_gastronomia_id' => $cfg->id,
                        'venta_factura_origen_id' => $ventaFacturaId,
                    ], $ajusteFiscal ? ['origen_pos' => 'recuperacion_arca_ajuste'] : []),
                );

                $ventaAnitaRevertir = null;

                $facturaTxt = trim((string) ($resultado['factura'] ?? $ventaNc->codigo));

                return [
                    'ok' => true,
                    'venta_id' => $ventaNc->id,
                    'factura' => $facturaTxt,
                    'mensaje' => 'Nota de crédito '.$facturaTxt.' generada correctamente.',
                    'vencae_pendiente' => $vencaePendiente ?? null,
                    'anita_pendiente' => (! empty($resultado['anita_pendiente']) && is_array($resultado['anita_pendiente']))
                        ? $resultado['anita_pendiente']
                        : null,
                ];
            });
            $profiler?->marcar('despues_transaccion');

            if (! $ajusteFiscal) {
                $this->completarAnitaDiferidoTrasNotaCredito($resultadoTx, $cfg);
            }

            $resultadoFinal = $resultadoTx;
            if (empty($opciones['omitir_impresion'])) {
                $profiler?->marcar('nc_ticket_inicio');
                $resultadoFinal = $this->aplicarImpresionTicketTrasNotaCredito($resultadoTx, $cfg, $cuenta);
                $profiler?->marcar('nc_ticket_fin');
            }

            GastronomiaEmisionProfiler::finalizar($profiler, [
                'flujo' => 'nota_credito',
                'venta_factura_id' => $ventaFacturaId,
                'venta_nc_id' => $resultadoTx['venta_id'] ?? null,
            ]);

            return $resultadoFinal;
        } catch (Throwable $e) {
            $claseError = ArcaWsfeEmisionResiliencia::clasificarError($e->getMessage());
            GastronomiaEmisionProfiler::finalizar($profiler, [
                'flujo' => 'nota_credito',
                'venta_factura_id' => $ventaFacturaId,
                'clase_error' => $claseError,
                'error' => $e->getMessage(),
            ]);
            Log::error('gastronomia.nota_credito.fallo', [
                'venta_factura_id' => $ventaFacturaId,
                'clase_error' => $claseError,
                'msg' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => GastronomiaMovimientoStockSupport::mensajeErrorEmision($e),
                'clase_error' => $claseError,
            ];
        }
    }

    public static function notaCreditoExistenteParaFactura(int $ventaFacturaId): ?int
    {
        $id = VentaGastronomiaEmision::query()
            ->where('venta_factura_origen_id', $ventaFacturaId)
            ->value('venta_id');
        if ($id !== null) {
            return (int) $id;
        }

        $id = VentaGastronomiaNcOrigen::query()
            ->where('venta_factura_id', $ventaFacturaId)
            ->value('venta_nc_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Emite una NC consolidada por el total de varias FAC recuperadas (PeriodoAsoc = jornada).
     *
     * @param  list<int>  $ventaFacturaIds
     * @param  array{
     *   fecha_factura:string,
     *   fecha_jornada:string,
     *   identificador_pc?:string,
     *   omitir_stock?:bool,
     *   omitir_impresion?:bool
     * }  $opciones
     * @return array{ok:bool,venta_id?:int,factura?:string,mensaje?:string,error?:string}
     */
    public function generarLoteAjusteFiscal(array $ventaFacturaIds, string $leyendaUsuario, array $opciones): array
    {
        $ventaFacturaIds = array_values(array_unique(array_filter(array_map('intval', $ventaFacturaIds))));
        if ($ventaFacturaIds === []) {
            return ['ok' => false, 'error' => 'El lote no tiene facturas.'];
        }

        $fechaFactura = trim((string) ($opciones['fecha_factura'] ?? ''));
        $fechaJornada = trim((string) ($opciones['fecha_jornada'] ?? ''));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFactura)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaJornada)) {
            return ['ok' => false, 'error' => 'El lote fiscal requiere fecha de comprobante y jornada válidas.'];
        }

        foreach ($ventaFacturaIds as $facId) {
            if (self::notaCreditoExistenteParaFactura($facId) !== null) {
                return ['ok' => false, 'error' => 'Ya existe NC para la factura id '.$facId.'.'];
            }
        }

        $primeraId = $ventaFacturaIds[0];
        $emision = VentaGastronomiaEmision::query()
            ->where('venta_id', $primeraId)
            ->with([
                'venta.clientes',
                'venta.puntoventas',
                'venta.venta_emisiones.articulos',
                'venta.tipotransacciones',
                'cuenta',
                'configuracionPuntoventa',
            ])
            ->first();
        if (! $emision || ! $emision->venta) {
            return ['ok' => false, 'error' => 'La primera factura del lote no es emisión gastronomía.'];
        }

        $cfg = $emision->configuracionPuntoventa;
        if (! $cfg) {
            return ['ok' => false, 'error' => 'Sin configuración de PV gastronomía para el lote.'];
        }

        $cuenta = $emision->cuenta;
        if (! $cuenta instanceof CuentaGastronomia) {
            return ['ok' => false, 'error' => 'Sin cuenta gastronómica de referencia para el lote.'];
        }

        $tipoNcId = self::resolverTipotransaccionNotaCreditoId($cfg);
        $tipoNc = Tipotransaccion::query()->find($tipoNcId);
        if (! $tipoNc || $tipoNc->signo === 'S') {
            return ['ok' => false, 'error' => 'Tipo de NC inválido.'];
        }

        $identificadorPc = trim((string) ($opciones['identificador_pc'] ?? $emision->identificador_pc ?? ''));
        if ($identificadorPc === '') {
            $identificadorPc = GastronomiaIdentificadorPc::resolver(null);
        }

        $ventas = Venta::query()
            ->with(['venta_emisiones.articulos', 'cobranzasDirectas', 'caja_movimientos.cobranzas'])
            ->whereIn('id', $ventaFacturaIds)
            ->get()
            ->keyBy('id');

        $itemsMerged = [
            'articulo_ids' => [],
            'cantidades' => [],
            'precios' => [],
            'descripcionarticulos' => [],
            'opcionales_por_item' => [],
            'omitir_stkmov_anita_por_item' => [],
            'impuesto_ids' => [],
            'incluyeimpuestos' => [],
        ];
        $totalAbs = 0.0;
        $codigos = [];
        foreach ($ventaFacturaIds as $facId) {
            $v = $ventas->get($facId);
            if (! $v) {
                return ['ok' => false, 'error' => 'Factura id '.$facId.' inexistente.'];
            }
            $totalAbs += abs((float) $v->total);
            $codigos[] = (string) $v->codigo;
            $part = GastronomiaFacturaItemsPayloadSupport::desdeVentaEmisiones($v);
            $offset = count($itemsMerged['articulo_ids']);
            foreach ($part['articulo_ids'] as $i => $artId) {
                $itemsMerged['articulo_ids'][] = $artId;
                $itemsMerged['cantidades'][] = $part['cantidades'][$i] ?? 0;
                $itemsMerged['precios'][] = $part['precios'][$i] ?? 0;
                $itemsMerged['descripcionarticulos'][] = $part['descripcionarticulos'][$i] ?? '';
                $itemsMerged['omitir_stkmov_anita_por_item'][] = $part['omitir_stkmov_anita_por_item'][$i] ?? true;
                $itemsMerged['impuesto_ids'][] = $part['impuesto_ids'][$i] ?? 0;
                $itemsMerged['incluyeimpuestos'][] = $part['incluyeimpuestos'][$i] ?? 'S';
                if (isset($part['opcionales_por_item'][$i])) {
                    $itemsMerged['opcionales_por_item'][$offset + $i] = $part['opcionales_por_item'][$i];
                }
            }
        }

        $ventaOrigen = $emision->venta;
        $ymdJornada = str_replace('-', '', $fechaJornada);
        $leyendaNc = trim($leyendaUsuario);
        if ($leyendaNc === '') {
            $leyendaNc = 'NC lote saneamiento ARCA: '.implode(', ', $codigos);
        }
        if (mb_strlen($leyendaNc) > 255) {
            $leyendaNc = mb_substr($leyendaNc, 0, 255);
        }

        $payload = [
            'venta_id' => (int) $ventaOrigen->id,
            'tipotransaccion_id' => $tipoNcId,
            'puntoventa_id' => (int) $ventaOrigen->puntoventa_id,
            'fechafactura' => $fechaFactura,
            'fechajornada' => $fechaJornada,
            'fechaasignaciondesde' => $ymdJornada,
            'fechaasignacionhasta' => $ymdJornada,
            'leyendafactura' => $leyendaNc,
            'actividad_arca_id' => (int) ($ventaOrigen->actividad_arca_id ?? Actividad_Arca::query()->orderBy('id')->value('id') ?? 1),
            'cliente_id' => (int) $ventaOrigen->cliente_id,
            'moneda_id' => (int) $ventaOrigen->moneda_id,
            'listaprecio_id' => (int) ($cfg->listaprecio_id ?? 1),
            'descuentolinea' => 0.,
            'descuentopie' => 0.,
            'descuentoimportepie' => 0.,
            'articulo_ids' => $itemsMerged['articulo_ids'],
            'cantidades' => $itemsMerged['cantidades'],
            'precios' => $itemsMerged['precios'],
            'descripcionarticulos' => $itemsMerged['descripcionarticulos'],
            'opcionales_por_item' => $itemsMerged['opcionales_por_item'],
            'omitir_stkmov_anita_por_item' => $itemsMerged['omitir_stkmov_anita_por_item'],
            '_omitir_sincronizacion_anita' => true,
            '_omitir_descuento_cuenta' => true,
        ];
        if (self::tieneImpuestosExplicitos($itemsMerged['impuesto_ids'])) {
            $payload['impuesto_ids'] = $itemsMerged['impuesto_ids'];
            $payload['incluyeimpuestos'] = $itemsMerged['incluyeimpuestos'];
        }
        if (trim((string) ($ventaOrigen->nombre ?? '')) !== '' || trim((string) ($ventaOrigen->numerodocumento ?? '')) !== '') {
            $payload['venta_receptor'] = [
                'nombre' => $ventaOrigen->nombre,
                'numerodocumento' => $ventaOrigen->numerodocumento,
                'domicilio' => $ventaOrigen->domicilio,
            ];
            $payload['arca_receptor'] = array_filter([
                'nombre' => $ventaOrigen->nombre,
                'numerodocumento' => $ventaOrigen->numerodocumento,
                'domicilio' => $ventaOrigen->domicilio,
            ], fn ($v) => $v !== null && $v !== '');
        }

        $mediosPago = $this->armarMediosDevolucionEfectivoLote((int) $cfg->empresa_id, $totalAbs);

        try {
            $resultadoTx = DB::transaction(function () use (
                $ventaFacturaIds,
                $ventaOrigen,
                $cuenta,
                $cfg,
                $tipoNc,
                $payload,
                $mediosPago,
                $identificadorPc,
                $primeraId,
            ) {
                $resultado = $this->facturacionGastronomiaService->emitirComprobante(
                    $payload,
                    $cuenta,
                    true,
                );
                if (! empty($resultado['error'])) {
                    throw new InvalidArgumentException((string) ($resultado['mensaje'] ?? $resultado['error']));
                }

                $ventaNc = $this->resolverVentaEmitida($ventaOrigen, $resultado);

                if (! empty($mediosPago) && empty($resultado['sin_cobranza'])) {
                    $this->cobranzaGastronomiaService->registrarCobranzaPos(
                        $ventaNc->fresh(),
                        $mediosPago,
                        $cfg,
                        esDevolucion: true,
                    );
                }

                if (! empty($resultado['cae_pendiente']) && is_array($resultado['cae_pendiente'])) {
                    $this->facturacionGastronomiaService->completarSolicitudCaePendiente($resultado['cae_pendiente']);
                }

                VentaGastronomiaEmision::updateOrCreate(
                    ['venta_id' => $ventaNc->id],
                    [
                        'cuenta_gastronomia_id' => $cuenta->id,
                        'identificador_pc' => $identificadorPc,
                        'configuracion_puntoventa_gastronomia_id' => $cfg->id,
                        'venta_factura_origen_id' => $primeraId,
                        'origen_pos' => 'recuperacion_arca_ajuste',
                    ],
                );

                foreach ($ventaFacturaIds as $facId) {
                    VentaGastronomiaNcOrigen::query()->updateOrCreate(
                        [
                            'venta_nc_id' => $ventaNc->id,
                            'venta_factura_id' => $facId,
                        ],
                        [],
                    );
                }

                return [
                    'ok' => true,
                    'venta_id' => $ventaNc->id,
                    'factura' => trim((string) ($resultado['factura'] ?? $ventaNc->codigo)),
                    'mensaje' => 'Nota de crédito consolidada generada.',
                    'importe_lote' => round(abs((float) $ventaNc->total), 2),
                ];
            });

            return $resultadoTx;
        } catch (Throwable $e) {
            Log::error('gastronomia.nc_lote_ajuste.error', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float,observacion?:string}>
     */
    private function armarMediosDevolucionEfectivoLote(int $empresaId, float $monto): array
    {
        $monto = round(abs($monto), 2);
        if ($monto < 0.01) {
            return [];
        }
        $cuentacajaId = (int) (\App\Support\Ventas\GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId) ?? 0);
        if ($cuentacajaId <= 0) {
            return [];
        }

        return [[
            'cuentacaja_id' => $cuentacajaId,
            'moneda_id' => 1,
            'monto' => $monto,
            'cotizacion' => 1.,
            'observacion' => 'Devolución NC lote saneamiento ARCA',
        ]];
    }

    public static function resolverTipotransaccionNotaCreditoId(ConfiguracionPuntoventaGastronomia $cfg): int
    {
        $id = (int) ($cfg->tipotransaccion_nota_credito_id ?? 0);
        if ($id <= 0) {
            $id = (int) config('gastronomia.tipotransaccion_nota_credito_id', 0);
        }
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Configure el tipo de transacción de nota de crédito en Ventas → Configuración punto de venta gastronomía'
                .' o defina GASTRONOMIA_TIPO_TRANSACCION_NOTA_CREDITO_ID.'
            );
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function armarPayloadNotaCredito(
        Venta $ventaOrigen,
        int $tipoNcId,
        ConfiguracionPuntoventaGastronomia $cfg,
        string $leyendaUsuario = '',
        ?CuentaGastronomia $cuenta = null,
        ?string $fechaFacturaForzada = null,
        ?string $fechaJornadaForzada = null,
    ): array {
        $items = $cuenta instanceof CuentaGastronomia && $cuenta->lineas->isNotEmpty()
            ? GastronomiaFacturaItemsPayloadSupport::desdeCuenta($cuenta)
            : GastronomiaFacturaItemsPayloadSupport::desdeVentaEmisiones($ventaOrigen);

        $fechaHoy = $fechaFacturaForzada ?? now()->format('Y-m-d');
        $leyendaManual = trim($leyendaUsuario);
        $referenciaCompro = (string) ($ventaOrigen->codigo ?? $ventaOrigen->id);
        if ($leyendaManual !== '') {
            $leyendaNc = $leyendaManual.' (NC por comprobante '.$referenciaCompro.')';
        } else {
            $leyendaNc = 'NC por comprobante '.$referenciaCompro;
            $leyendaOrigen = trim((string) ($ventaOrigen->leyenda ?? ''));
            if ($leyendaOrigen !== '') {
                $leyendaNc .= ' — '.$leyendaOrigen;
            }
        }
        if (mb_strlen($leyendaNc) > 255) {
            $leyendaNc = mb_substr($leyendaNc, 0, 255);
        }

        $payload = [
            'venta_id' => (int) $ventaOrigen->id,
            'tipotransaccion_id' => $tipoNcId,
            'puntoventa_id' => (int) $ventaOrigen->puntoventa_id,
            'fechafactura' => $fechaHoy,
            'leyendafactura' => $leyendaNc,
            'actividad_arca_id' => (int) ($ventaOrigen->actividad_arca_id ?? Actividad_Arca::query()->orderBy('id')->value('id') ?? 1),
            'cliente_id' => (int) $ventaOrigen->cliente_id,
            'moneda_id' => (int) $ventaOrigen->moneda_id,
            'listaprecio_id' => (int) ($cfg->listaprecio_id ?? 1),
            'descuentolinea' => 0.,
            'descuentopie' => (float) ($ventaOrigen->descuento ?? 0),
            'descuentoimportepie' => 0.,
            'articulo_ids' => $items['articulo_ids'],
            'cantidades' => $items['cantidades'],
            'precios' => $items['precios'],
            'descripcionarticulos' => $items['descripcionarticulos'],
            'opcionales_por_item' => $items['opcionales_por_item'],
            'omitir_stkmov_anita_por_item' => $items['omitir_stkmov_anita_por_item'],
        ];

        if (self::tieneImpuestosExplicitos($items['impuesto_ids'])) {
            $payload['impuesto_ids'] = $items['impuesto_ids'];
            $payload['incluyeimpuestos'] = $items['incluyeimpuestos'];
        }

        if (trim((string) ($ventaOrigen->nombre ?? '')) !== '' || trim((string) ($ventaOrigen->numerodocumento ?? '')) !== '') {
            $payload['venta_receptor'] = [
                'nombre' => $ventaOrigen->nombre,
                'numerodocumento' => $ventaOrigen->numerodocumento,
                'domicilio' => $ventaOrigen->domicilio,
            ];
            $payload['arca_receptor'] = array_filter([
                'nombre' => $ventaOrigen->nombre,
                'numerodocumento' => $ventaOrigen->numerodocumento,
                'domicilio' => $ventaOrigen->domicilio,
            ], fn ($v) => $v !== null && $v !== '');
        }

        $empresaId = (int) ($ventaOrigen->empresa_id ?: $cfg->empresa_id);

        if ($fechaFacturaForzada !== null && $fechaJornadaForzada !== null) {
            $payload['fechafactura'] = $fechaFacturaForzada;
            $payload['fechajornada'] = $fechaJornadaForzada;

            return $payload;
        }

        return $this->jornadaService->aplicarFechasAlPayload($payload, $empresaId);
    }

    /**
     * @param  list<int>  $impuestoIds
     */
    private static function tieneImpuestosExplicitos(array $impuestoIds): bool
    {
        foreach ($impuestoIds as $impuestoId) {
            if ((int) $impuestoId > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float,observacion?:string}>
     */
    private function armarMediosCobranzaDevolucion(Venta $ventaOrigen): array
    {
        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($ventaOrigen);
        if ($cobranzas->isEmpty()) {
            return [];
        }

        $movimientos = Caja_Movimiento::query()
            ->whereIn('cobranza_id', $cobranzas->pluck('id'))
            ->with(['caja_movimiento_cuentacajas'])
            ->get();

        $lineas = [];
        foreach ($movimientos as $mov) {
            foreach ($mov->caja_movimiento_cuentacajas as $cc) {
                $cuentacajaId = (int) ($cc->cuentacaja_id ?? 0);
                $monedaId = (int) ($cc->moneda_id ?? 1);
                $monto = (float) ($cc->monto ?? 0);
                if ($cuentacajaId <= 0 || $monto <= 0.) {
                    continue;
                }
                $lineas[] = [
                    'cuentacaja_id' => $cuentacajaId,
                    'moneda_id' => $monedaId > 0 ? $monedaId : 1,
                    'monto' => $monto,
                    'cotizacion' => (float) ($cc->cotizacion ?? 1.) ?: 1.,
                    'observacion' => 'Devolución NC gastronomía',
                ];
            }
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function resolverVentaEmitida(Venta $ventaOrigen, array $resultado): Venta
    {
        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        if ($ventaId > 0) {
            $venta = Venta::query()->find($ventaId);
            if ($venta) {
                return $venta;
            }
        }

        $facturaTxt = trim((string) ($resultado['factura'] ?? ''));
        if ($facturaTxt === '') {
            throw new RuntimeException('No se pudo recuperar la nota de crédito generada.');
        }

        if (preg_match('/^\S+\s+\S\s+(\d+)-(\d+)$/u', $facturaTxt, $m)) {
            $numero = (int) $m[2];
            $venta = Venta::query()
                ->where('puntoventa_id', $ventaOrigen->puntoventa_id)
                ->where('numerocomprobante', $numero)
                ->orderByDesc('id')
                ->first();
            if ($venta) {
                return $venta;
            }
        }

        throw new RuntimeException('No se pudo recuperar la venta interna de la nota de crédito '.$facturaTxt.'.');
    }

    /**
     * Imprime el ticket fiscal sincrónicamente tras emitir la NC, alineado con la
     * emisión normal de factura (GastronomiaFacturaEmisionService). No usa
     * defer()/imprimirTrasEmisionEncolado: el cliente espera que el ticket salga
     * por la impresora térmica antes de cerrar el modal, igual que en F5/F8.
     *
     * @param  array{ok:bool,venta_id?:int,factura?:string,mensaje?:string}  $resultado
     * @return array{ok:bool,venta_id?:int,factura?:string,mensaje?:string,warn?:string,impresion_ticket?:string,impresion_ticket_mensaje?:string}
     */
    private function aplicarImpresionTicketTrasNotaCredito(
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
            $resultado = NcjetdirectSalidaSupport::anexarAvisoWarnSinConfirmacionPapel($resultado, $imp);

            return $resultado;
        }

        $mensajeImp = trim((string) ($imp['mensaje'] ?? 'No se pudo imprimir el ticket térmico.'));
        $resultado['impresion_ticket'] = 'error';
        $resultado['impresion_ticket_mensaje'] = $mensajeImp;
        $aviso = 'Nota de crédito generada; impresión térmica: '.$mensajeImp;
        $mensajeBase = trim((string) ($resultado['mensaje'] ?? ''));
        $resultado['mensaje'] = $mensajeBase !== '' ? $mensajeBase.' '.$aviso : $aviso;
        $resultado['warn'] = $aviso;

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $resultadoTx
     */
    private function completarAnitaDiferidoTrasNotaCredito(array $resultadoTx, ConfiguracionPuntoventaGastronomia $cfg): void
    {
        $ventaId = (int) ($resultadoTx['venta_id'] ?? 0);
        if ($ventaId <= 0) {
            return;
        }

        $anitaPendiente = (! empty($resultadoTx['anita_pendiente']) && is_array($resultadoTx['anita_pendiente']))
            ? $resultadoTx['anita_pendiente']
            : null;
        $vencaePendiente = (! empty($resultadoTx['vencae_pendiente']) && is_array($resultadoTx['vencae_pendiente']))
            ? $resultadoTx['vencae_pendiente']
            : null;

        if ($anitaPendiente === null && $vencaePendiente === null) {
            return;
        }

        if (GastronomiaAnitaColaSupport::despacharReplicacionDiferida(
            $ventaId,
            $anitaPendiente,
            $vencaePendiente,
            (int) $cfg->id,
            0.0,
            false,
            'nota_credito',
        )) {
            return;
        }

        $ejecutar = function () use ($anitaPendiente, $vencaePendiente, $ventaId, $cfg): void {
            app(GastronomiaAnitaDeferEjecucionService::class)->ejecutar(
                $ventaId,
                $anitaPendiente,
                $vencaePendiente,
                (int) $cfg->id,
                0.0,
                false,
                'nota_credito_terminating',
            );
        };

        if (filter_var(config('gastronomia.anita_tras_respuesta', true), FILTER_VALIDATE_BOOLEAN)) {
            app()->terminating($ejecutar);
        } else {
            $ejecutar();
        }
    }
}
