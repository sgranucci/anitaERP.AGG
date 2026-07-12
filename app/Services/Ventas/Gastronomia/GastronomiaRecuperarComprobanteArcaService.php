<?php

declare(strict_types=1);

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Arca\ArcaWsfeFacturaElectronicaService;
use App\Services\Ventas\FacturacionService;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\Gastronomia\RecalcularAsientoVentasMedioRealCierreJornadaSupport;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\VentaNumeracionEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Recupera en ERP (+ Anita) un comprobante ya autorizado en ARCA cuyo rollback dejó hueco de numeración.
 */
final class GastronomiaRecuperarComprobanteArcaService
{
    public function __construct(
        private readonly ArcaWsfeFacturaElectronicaService $arcaWsfeService,
        private readonly GastronomiaFacturacionService $facturacionGastronomiaService,
        private readonly FacturacionService $facturacionService,
        private readonly GastronomiaReplicarVentasAnitaErpService $replicarAnitaService,
        private readonly GastronomiaReceptorFacturacionService $receptorFacturacionService,
        private readonly GastronomiaChequeoVentasAnitaErpService $chequeoAnitaService,
        private readonly GastronomiaCobranzaService $cobranzaGastronomiaService,
        private readonly RecalcularAsientoVentasMedioRealCierreJornadaSupport $recalcularAsientoCierreSupport,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function recuperar(
        int $empresaId,
        string $pvCodigo,
        int $numeroComprobante,
        int $tipotransaccionId = 1,
        ?int $cuentaReferenciaId = null,
        ?int $ventaReferenciaId = null,
        bool $dryRun = false,
    ): array {
        $pvCodigo = str_pad(trim($pvCodigo), 5, '0', STR_PAD_LEFT);
        $empresa = Empresa::query()->findOrFail($empresaId);
        $pv = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $pvCodigo)
            ->first();
        if ($pv === null) {
            throw new InvalidArgumentException('PV '.$pvCodigo.' inexistente para empresa '.$empresaId.'.');
        }

        $tipo = Tipotransaccion::query()->find($tipotransaccionId);
        if ($tipo === null) {
            throw new InvalidArgumentException('Tipo transacción '.$tipotransaccionId.' inexistente.');
        }

        $codigoAfip = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision((int) ($tipo->codigo ?? 0), 'B');
        $arca = $this->consultarArca($empresaId, (int) $pv->codigo, $codigoAfip, $numeroComprobante);

        $existente = Venta::query()
            ->where('puntoventa_id', $pv->id)
            ->where('numerocomprobante', $numeroComprobante)
            ->whereNull('deleted_at')
            ->first();
        if ($existente !== null) {
            throw new InvalidArgumentException(
                'Ya existe venta ERP '.$existente->codigo.' (id '.$existente->id.').',
            );
        }

        $payload = $this->armarPayloadRecuperacion(
            $pv,
            $tipo,
            $numeroComprobante,
            $arca,
            $cuentaReferenciaId,
            $ventaReferenciaId,
        );

        if ($dryRun) {
            return [
                'ok' => true,
                'dry_run' => true,
                'arca' => $arca,
                'payload_resumen' => [
                    'puntoventa_id' => $payload['puntoventa_id'],
                    'numerocomprobante_forzado' => $payload['numerocomprobante_forzado'],
                    'fechafactura' => $payload['fechafactura'],
                    'fechajornada' => $payload['fechajornada'] ?? null,
                    'total_esperado_arca' => $arca['imp_total'],
                    'items' => count($payload['articulo_ids'] ?? []),
                ],
            ];
        }

        return DB::transaction(function () use ($payload, $arca, $pv, $cuentaReferenciaId, $empresaId): array {
            $resultado = $this->facturacionService->generaComprobanteGeneral($payload);
            if (! empty($resultado['error'])) {
                throw new RuntimeException((string) $resultado['error']);
            }

            $ventaId = (int) ($resultado['venta_id'] ?? 0);
            if ($ventaId <= 0) {
                throw new RuntimeException('generaComprobanteGeneral no devolvió venta_id.');
            }

            $esCortesia = ! empty($resultado['factura_cortesia_total']) || ! empty($payload['factura_cortesia_total']);
            if ($esCortesia) {
                $this->normalizarCortesia($resultado, $ventaId);
            }

            $caePendiente = $resultado['cae_pendiente'] ?? null;
            if (! is_array($caePendiente)) {
                throw new RuntimeException('Se esperaba cae_pendiente en la emisión de recuperación.');
            }

            $caeRecuperado = [
                'cae' => (string) $arca['cae'],
                'fechavencimientocae' => (string) $arca['fechavencimientocae'],
            ];

            $vencaePendiente = $this->facturacionGastronomiaService->completarSolicitudCaePendiente(
                $caePendiente,
                $caeRecuperado,
            );

            $venta = Venta::query()->findOrFail($ventaId);

            if (isset($resultado['anita_pendiente']) && is_array($resultado['anita_pendiente'])) {
                $this->facturacionGastronomiaService->ejecutarAnitaPendienteGastronomia($resultado['anita_pendiente']);
            } else {
                $this->asegurarCabeceraAnita($venta->fresh(), $tipo);
            }

            if (is_array($vencaePendiente)) {
                $this->facturacionGastronomiaService->ejecutarVencaePendienteGastronomia($vencaePendiente);
            }

            $this->registrarEmisionRecuperacion($venta, $cuentaReferenciaId, $pv);

            $cobranzaId = $this->generarCobranzaEfectivo($venta->fresh(), $empresaId, $cuentaReferenciaId, $esCortesia);

            $asientoCierre = $this->actualizarAsientoCierreJornadaSiExiste($venta->fresh(), $empresaId);

            Log::info('gastronomia.recuperar_comprobante_arca.ok', [
                'venta_id' => $venta->id,
                'codigo' => $venta->codigo,
                'cae' => $venta->cae,
                'cuenta_referencia_id' => $cuentaReferenciaId,
                'cobranza_id' => $cobranzaId,
                'asiento_cierre' => $asientoCierre,
            ]);

            return [
                'ok' => true,
                'dry_run' => false,
                'venta_id' => (int) $venta->id,
                'codigo' => (string) $venta->codigo,
                'cae' => (string) $venta->cae,
                'total' => round((float) $venta->total, 2),
                'cobranza_id' => $cobranzaId,
                'asiento_cierre' => $asientoCierre,
                'arca' => $arca,
            ];
        });
    }

    /**
     * @return array{cae:string,fechavencimientocae:string,imp_total:float,fecha_proceso:string,fecha_comprobante:string}
     */
    private function consultarArca(int $empresaId, int $ptoVta, int $cbteTipo, int $numero): array
    {
        $result = $this->arcaWsfeService->feCompConsultar($empresaId, $ptoVta, $cbteTipo, $numero);
        $rg = $result->ResultGet ?? null;
        if ($rg === null) {
            throw new InvalidArgumentException('ARCA no devolvió ResultGet para el comprobante '.$numero.'.');
        }

        $res = (string) ($rg->Resultado ?? '');
        if (! in_array($res, ['A', 'P'], true)) {
            throw new InvalidArgumentException('Comprobante '.$numero.' no autorizado en ARCA (Resultado='.$res.').');
        }

        $cae = trim((string) ($rg->CodAutorizacion ?? ''));
        $vto = trim((string) ($rg->FchVto ?? ''));
        if ($cae === '' || $vto === '') {
            throw new InvalidArgumentException('ARCA sin CAE/vencimiento para comprobante '.$numero.'.');
        }

        $fchProceso = (string) ($rg->FchProceso ?? '');
        $fchCbte = (string) ($rg->CbteFch ?? '');

        return [
            'cae' => $cae,
            'fechavencimientocae' => $vto,
            'imp_total' => round((float) ($rg->ImpTotal ?? 0), 2),
            'fecha_proceso' => $this->formatearFechaArca($fchProceso),
            'fecha_comprobante' => $this->formatearFechaArca($fchCbte),
        ];
    }

    /**
     * @param  array{cae:string,fechavencimientocae:string,imp_total:float,fecha_proceso:string,fecha_comprobante:string}  $arca
     * @return array<string, mixed>
     */
    private function armarPayloadRecuperacion(
        Puntoventa $pv,
        Tipotransaccion $tipo,
        int $numeroComprobante,
        array $arca,
        ?int $cuentaReferenciaId,
        ?int $ventaReferenciaId,
    ): array {
        $fechaFactura = $arca['fecha_comprobante'] !== '' ? $arca['fecha_comprobante'] : $arca['fecha_proceso'];
        if ($fechaFactura === '') {
            $fechaFactura = now()->format('Y-m-d');
        }

        $leyendaBase = 'Recuperación ARCA '.VentaNumeracionEmpresaSupport::formatearCodigoVenta(
            (string) $tipo->abreviatura,
            'B',
            (string) $pv->codigo,
            $numeroComprobante,
        ).' (rollback deadlock).';

        if ($cuentaReferenciaId !== null && $cuentaReferenciaId > 0) {
            $cuenta = CuentaGastronomia::query()
                ->with(['lineas', 'descuentoGastronomia', 'configuracionPuntoventa'])
                ->find($cuentaReferenciaId);
            if ($cuenta === null) {
                throw new InvalidArgumentException('Cuenta referencia '.$cuentaReferenciaId.' inexistente.');
            }

            [$articuloIds, $cantidades, $precios, $descripciones, $opcionalesPorItem, $omitirStkmov] = $this->arraysDesdeCuenta($cuenta);
            $receptor = $this->receptorFacturacionService->resolverParaFacturar($cuenta);
            $cfgListaprecio = (int) ($cuenta->configuracionPuntoventa?->listaprecio_id ?? 2);

            $payload = [
                'tipotransaccion_id' => (int) $tipo->id,
                'puntoventa_id' => (int) $pv->id,
                'fechafactura' => $fechaFactura,
                'fechajornada' => $fechaFactura,
                'leyendafactura' => $leyendaBase.' '.$this->leyendaCuenta($cuenta),
                'actividad_arca_id' => (int) config('gastronomia.actividad_arca_id', 1),
                'cliente_id' => (int) $receptor['cliente_id'],
                'moneda_id' => (int) config('gastronomia.moneda_id', 1),
                'listaprecio_id' => $cfgListaprecio,
                'descuentolinea' => 0.,
                'descuentopie' => 0.,
                'descuentoimportepie' => 0.,
                'articulo_ids' => $articuloIds,
                'cantidades' => $cantidades,
                'precios' => $precios,
                'descripcionarticulos' => $descripciones,
                'opcionales_por_item' => $opcionalesPorItem,
                'omitir_stkmov_anita_por_item' => $omitirStkmov,
                'numerocomprobante_forzado' => $numeroComprobante,
            ];

            $this->receptorFacturacionService->aplicarReceptorAlPayloadFacturacion($payload, $receptor);

            if ($cuenta->descuento_gastronomia_id) {
                $desc = $cuenta->descuentoGastronomia;
                $pct = 0.;
                if ($desc !== null && (string) ($desc->tipovalor ?? 'P') === 'P') {
                    $pct = (float) ($desc->valor ?? 0);
                }
                if ($pct >= 99.99 && (float) ($arca['imp_total'] ?? 0) <= 0.02) {
                    $payload = $this->aplicarCortesiaPayload($payload);
                } elseif ($pct > 0 && $pct < 99.99) {
                    $payload['descuentopie'] = $pct;
                    $payload['descuentoimportepie'] = 0.;
                }
            }
        } elseif ($ventaReferenciaId !== null && $ventaReferenciaId > 0) {
            $payload = $this->payloadDesdeVentaReferencia($ventaReferenciaId, $pv, $tipo, $numeroComprobante, $fechaFactura, $leyendaBase);
        } else {
            throw new InvalidArgumentException('Indique --cuenta o --venta-referencia para armar ítems.');
        }

        $opciones = [
            'omitir_movimiento_stock' => true,
            'omitir_contabilidad' => ! config('gastronomia.genera_contabilidad_al_facturar', true),
            'omitir_cuenta_corriente' => true,
            'omitir_sincronizacion_anita' => ! config('gastronomia.sincronizar_anita_al_facturar', true),
            'anita_modo_minimo' => (bool) config('gastronomia.anita_modo_minimo', true),
            'omitir_stkmov_anita' => filter_var(config('gastronomia.anita_omitir_stkmov', true), FILTER_VALIDATE_BOOLEAN),
            'omitir_solicitud_arca_cae' => true,
            'omitir_numera_anita_fin' => true,
            'fechajornada' => $fechaFactura,
        ];
        $payload['opciones_emision'] = $opciones;

        return $payload;
    }

    /**
     * @return array{0:list<int>,1:list<float>,2:list<float>,3:list<string>,4:array<int,array<string|int,int|null>>,5:list<bool>}
     */
    private function arraysDesdeCuenta(CuentaGastronomia $cuenta): array
    {
        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];
        $opcionalesPorItem = [];
        $omitirStkmov = [];

        foreach ($cuenta->lineas as $linea) {
            $pct = (float) $linea->descuento_linea_pct;
            $precioNet = (float) $linea->precio_unitario * (1 - $pct / 100);
            $indexPadre = count($articuloIds);

            $articuloIds[] = (int) $linea->articulo_id;
            $cantidades[] = (float) $linea->cantidad;
            $precios[] = $precioNet;
            $descripciones[] = '';
            $omitirStkmov[] = false;

            $opcionalesLinea = is_array($linea->opcionales_json) ? $linea->opcionales_json : [];
            $opcionalesPorItem[$indexPadre] = [];
            foreach ($opcionalesLinea as $orden => $valor) {
                $opcionalesPorItem[$indexPadre][(string) $orden] = \App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion::estaVacio($valor)
                    ? null
                    : $valor;
            }
        }

        return [$articuloIds, $cantidades, $precios, $descripciones, $opcionalesPorItem, $omitirStkmov];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadDesdeVentaReferencia(
        int $ventaReferenciaId,
        Puntoventa $pv,
        Tipotransaccion $tipo,
        int $numeroComprobante,
        string $fechaFactura,
        string $leyendaBase,
    ): array {
        $ref = Venta::query()->with(['venta_emisiones'])->find($ventaReferenciaId);
        if ($ref === null) {
            throw new InvalidArgumentException('Venta referencia '.$ventaReferenciaId.' inexistente.');
        }

        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];

        foreach ($ref->venta_emisiones as $emision) {
            $cant = abs((float) $emision->cantidad);
            if ($cant <= 0) {
                continue;
            }
            $articuloIds[] = (int) $emision->articulo_id;
            $cantidades[] = $cant;
            $precios[] = abs((float) $emision->precio);
            $descripciones[] = (string) ($emision->detalle ?? '');
        }

        $payload = [
            'tipotransaccion_id' => (int) $tipo->id,
            'puntoventa_id' => (int) $pv->id,
            'fechafactura' => $fechaFactura,
            'fechajornada' => (string) ($ref->fechajornada ?? $fechaFactura),
            'leyendafactura' => $leyendaBase.' Ref venta '.$ref->codigo,
            'actividad_arca_id' => (int) ($ref->actividad_arca_id ?? 1),
            'cliente_id' => (int) $ref->cliente_id,
            'moneda_id' => (int) $ref->moneda_id,
            'listaprecio_id' => (int) config('gastronomia.listaprecio_id', 2),
            'descuentolinea' => (float) ($ref->descuento ?? 0),
            'descuentopie' => (float) ($ref->descuento ?? 0),
            'descuentoimportepie' => 0.,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descripcionarticulos' => $descripciones,
            'numerocomprobante_forzado' => $numeroComprobante,
        ];

        if ((float) $ref->total <= 0.02) {
            $payload = $this->aplicarCortesiaPayload($payload);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function aplicarCortesiaPayload(array $payload): array
    {
        $exentoId = (int) config('gastronomia.impuesto_exento_id', 1);
        $payload['impuesto_ids'] = array_fill(0, count($payload['articulo_ids'] ?? []), $exentoId);
        $payload['incluyeimpuestos'] = array_fill(0, count($payload['articulo_ids'] ?? []), 'N');
        $payload['factura_cortesia_total'] = true;
        $payload['leyendafactura'] = trim((string) ($payload['leyendafactura'] ?? '')).' Consumo bonificado (descuento 100%).';

        return $payload;
    }

    private function leyendaCuenta(CuentaGastronomia $cuenta): string
    {
        return 'Cuenta gastronomía #'.$cuenta->id;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function normalizarCortesia(array &$resultado, int $ventaId): void
    {
        $venta = Venta::query()->find($ventaId);
        if ($venta === null) {
            return;
        }

        $minimo = GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA;
        $totalActual = round((float) $venta->total, 2);

        if (abs($totalActual - $minimo) > 0.001) {
            $delta = round($minimo - $totalActual, 2);
            $venta->total = $minimo;
            $venta->descuento = 100.;
            $venta->save();

            $exento = \App\Models\Ventas\Venta_Impuesto::query()
                ->where('venta_id', $ventaId)
                ->where('concepto', 'Exento')
                ->first();
            if ($exento !== null) {
                $exento->importe = round(max(0., (float) $exento->importe + $delta), 2);
                $exento->save();
            } else {
                \App\Models\Ventas\Venta_Impuesto::query()->create([
                    'venta_id' => $ventaId,
                    'concepto' => 'Exento',
                    'baseimponible' => 0.,
                    'tasa' => 0.,
                    'importe' => $minimo,
                    'provincia_id' => null,
                    'impuesto_id' => null,
                ]);
            }
        }

        if (isset($resultado['cae_pendiente']) && is_array($resultado['cae_pendiente'])) {
            $dataCae = $resultado['cae_pendiente']['data_cae'] ?? null;
            if (is_array($dataCae)) {
                $ventaTmp = ['total' => $minimo];
                \App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::aplicarCortesiaMinimaEnPayloadAnita($ventaTmp, $dataCae, true);
                $resultado['cae_pendiente']['data_cae'] = $dataCae;
            }
        }

        if (isset($resultado['anita_pendiente']) && is_array($resultado['anita_pendiente'])) {
            $ventaPayload = $resultado['anita_pendiente']['venta'] ?? null;
            $dataCae = $resultado['anita_pendiente']['data_cae'] ?? null;
            if (is_array($ventaPayload) && is_array($dataCae)) {
                \App\Support\Ventas\Gastronomia\GastronomiaAnitaVenGravadoSupport::aplicarCortesiaMinimaEnPayloadAnita($ventaPayload, $dataCae, true);
                $resultado['anita_pendiente']['venta'] = $ventaPayload;
                $resultado['anita_pendiente']['data_cae'] = $dataCae;
            }
        }
    }

    private function registrarEmisionRecuperacion(Venta $venta, ?int $cuentaReferenciaId, Puntoventa $pv): void
    {
        VentaGastronomiaEmision::updateOrCreate(
            ['venta_id' => $venta->id],
            [
                'cuenta_gastronomia_id' => $cuentaReferenciaId,
                'origen_pos' => 'recuperacion_arca',
                'identificador_pc' => 'recuperacion-arca',
                'configuracion_puntoventa_gastronomia_id' => null,
            ],
        );
    }

    /**
     * Genera la cobranza en efectivo por el total de la venta recuperada.
     *
     * Motivo: al recuperar un comprobante ARCA (rollback/deadlock) se reconstruía la factura pero no
     * la cobranza, dejando la venta facturada sin cobro. El cierre de jornada tomaba esa venta en el
     * HABER (ventas + IVA) sin contrapartida en el DEBE (medios de cobro), descuadrando el asiento
     * consolidado contra Diferencia de caja. Registrando el cobro en efectivo, el cierre cuadra solo.
     *
     * Se omite en cortesías $0,01 (no tienen cobranza; se imputan a Diferencia de caja como invitación).
     */
    private function generarCobranzaEfectivo(
        Venta $venta,
        int $empresaId,
        ?int $cuentaReferenciaId,
        bool $esCortesia,
    ): ?int {
        if (! (bool) config('gastronomia.recuperacion_arca_genera_cobranza_efectivo', true)) {
            return null;
        }

        $total = round((float) $venta->total, 2);
        if ($esCortesia || $total <= 0.02) {
            return null;
        }

        // Idempotencia: si la venta ya tiene cobranza directa, no duplicar.
        if ($venta->cobranzasDirectas()->exists()) {
            return null;
        }

        $efectivo = GastronomiaCuentacajaEfectivo::cuentaParaEmpresa($empresaId);
        if ($efectivo === null || (int) ($efectivo['id'] ?? 0) <= 0) {
            Log::warning('gastronomia.recuperar_comprobante_arca.sin_cuenta_efectivo', [
                'venta_id' => $venta->id,
                'empresa_id' => $empresaId,
            ]);

            return null;
        }

        $cfg = $this->resolverConfiguracionPuntoventa($empresaId, $cuentaReferenciaId);

        $mediosPago = [[
            'cuentacaja_id' => (int) $efectivo['id'],
            'moneda_id' => (int) ($efectivo['moneda_id'] ?? 1),
            'monto' => $total,
            'cotizacion' => 1.,
            'observacion' => 'Recuperación ARCA (efectivo)',
        ]];

        $res = $this->cobranzaGastronomiaService->registrarCobranzaPos($venta, $mediosPago, $cfg);

        return (int) ($res['cobranza_id'] ?? 0) ?: null;
    }

    /**
     * Si el cierre de jornada Waitry ya grabó el asiento «ventas_medio_real» para la fecha de la venta,
     * lo recalcula con todas las emisiones/cobranzas actuales (incluye la factura recuperada) en ERP + Anita.
     *
     * @return array<string, mixed>|null null si no hay asiento de cierre para esa jornada
     */
    private function actualizarAsientoCierreJornadaSiExiste(Venta $venta, int $empresaId): ?array
    {
        if (! (bool) config('gastronomia.recuperacion_arca_actualiza_asiento_cierre_jornada', true)) {
            return null;
        }

        $fechaJornada = $venta->fechajornada !== null
            ? Carbon::parse($venta->fechajornada)->format('Y-m-d')
            : ($venta->fecha !== null ? Carbon::parse($venta->fecha)->format('Y-m-d') : '');

        if ($fechaJornada === '') {
            Log::warning('gastronomia.recuperar_comprobante_arca.sin_fecha_jornada', [
                'venta_id' => $venta->id,
                'empresa_id' => $empresaId,
            ]);

            return null;
        }

        return $this->recalcularAsientoCierreSupport->actualizarSiExiste($empresaId, $fechaJornada);
    }

    private function resolverConfiguracionPuntoventa(int $empresaId, ?int $cuentaReferenciaId): ConfiguracionPuntoventaGastronomia
    {
        if ($cuentaReferenciaId !== null && $cuentaReferenciaId > 0) {
            $cuenta = CuentaGastronomia::query()->with('configuracionPuntoventa')->find($cuentaReferenciaId);
            $cfg = $cuenta?->configuracionPuntoventa;
            if ($cfg instanceof ConfiguracionPuntoventaGastronomia) {
                return $cfg;
            }
        }

        $cfg = ConfiguracionPuntoventaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->first();
        if ($cfg instanceof ConfiguracionPuntoventaGastronomia) {
            return $cfg;
        }

        // Config transitoria: registrarCobranzaPos solo necesita empresa_id + resolución de tipotransaccion
        // de caja (cae al respaldo GASTRONOMIA_TIPO_TRANSACCION_CAJA_ID / fallback por operación).
        $cfg = new ConfiguracionPuntoventaGastronomia();
        $cfg->empresa_id = $empresaId;

        return $cfg;
    }

    private function asegurarCabeceraAnita(Venta $venta, Tipotransaccion $tipo): void
    {
        if (! config('gastronomia.sincronizar_anita_al_facturar', true)) {
            return;
        }

        $venta->loadMissing('puntoventas');
        $pv = $venta->puntoventas;
        if ($pv === null) {
            return;
        }

        $letra = 'B';
        $sucursal = (int) preg_replace('/\D+/', '', (string) $pv->codigo);
        $numero = (int) $venta->numerocomprobante;
        $tipoAnita = (string) $tipo->abreviatura;

        if ($this->chequeoAnitaService->existeCabeceraEnAnita($tipoAnita, $letra, $sucursal, $numero)) {
            return;
        }

        $this->replicarAnitaService->replicarVenta($venta, false, true);
    }

    private function formatearFechaArca(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || strlen($raw) !== 8) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Ymd', $raw)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }
}
