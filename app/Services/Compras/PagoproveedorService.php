<?php

namespace App\Services\Compras;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Estado;
use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Compras\Proveedor;
use App\Repositories\Caja\Caja_Movimiento_CuentacajaRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_EstadoRepositoryInterface;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Caja\ChequeRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Compras\PagoproveedorRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Caja\ChequePropioInstrumentoSupport;
use App\Support\Caja\ChequeTerceroEndosoAnitaSupport;
use App\Support\Caja\IngresoEgresoAnitaNumeracionSupport;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaNumeracionSupport;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaRetencionEscrituraSupport;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaRetencionNumeracionSupport;
use App\Support\Compras\PagoproveedorAplicacionCuentacorrienteSupport;
use App\Support\Compras\PagoproveedorAsientoArmadoSupport;
use App\Support\Compras\PagoproveedorEdicionCandadoSupport;
use App\Support\Compras\ProveedorCbuPagoSupport;
use App\Support\Compras\Retencion\PagoproveedorRetencionPersistenciaSupport;
use App\Support\Contable\AsientoBalanceSupport;
use App\Support\Contable\AsientoCargaManualSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Numerico\NumeroDecimalLocalSupport;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PagoproveedorService
{
    public function __construct(
        private PagoproveedorRepositoryInterface $pagoproveedorRepository,
        private Caja_MovimientoRepositoryInterface $cajaMovimientoRepository,
        private Caja_Movimiento_CuentacajaRepositoryInterface $cajaMovimientoCuentacajaRepository,
        private Caja_Movimiento_EstadoRepositoryInterface $cajaMovimientoEstadoRepository,
        private ChequeRepositoryInterface $chequeRepository,
        private AsientoRepositoryInterface $asientoRepository,
        private Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private TipoasientoRepositoryInterface $tipoasientoRepository,
        private RetencionesPagoCalculator $retencionesPagoCalculator,
        private RetencionesPagoContextoBuilder $retencionesPagoContextoBuilder,
        private CuentacajaRepositoryInterface $cuentacajaRepository,
        private CuentacontableRepositoryInterface $cuentacontableRepository,
        private ProveedorCuentacorrienteAplicacionAnitaSyncService $cuentacorrienteAnitaSyncService,
    ) {}

    /**
     * Preview AJAX del asiento TES (no graba).
     *
     * @param  array<string, mixed>  $data
     * @return array{mensaje: string, asiento: list<array<string, mixed>>}
     */
    public function generaAsientoContable(array $data): array
    {
        try {
            $decode = static function ($raw): array {
                if (is_array($raw)) {
                    return $raw;
                }
                $decoded = json_decode((string) ($raw ?? '[]'));

                return is_array($decoded) ? $decoded : [];
            };

            $asiento = PagoproveedorAsientoArmadoSupport::armar(
                $decode($data['datoscaja'] ?? []),
                $decode($data['datoscontables'] ?? []),
                $decode($data['datoscheques_emitidos'] ?? []),
                $decode($data['datoscheques_recibidos'] ?? []),
                $decode($data['datoscomprobantes'] ?? []),
                $decode($data['datosretenciones'] ?? []),
                (int) ($data['empresa_id'] ?? 0),
                (int) ($data['proveedor_id'] ?? 0),
                (string) ($data['fecha'] ?? date('Y-m-d')),
                $this->cuentacajaRepository,
                $this->cuentacontableRepository,
                (string) ($data['proveedor_nombre'] ?? ''),
                (string) ($data['numerotransaccion'] ?? $data['numero_op'] ?? ''),
                (int) ($data['moneda_id'] ?? 1),
                NumeroDecimalLocalSupport::aFloat($data['cotizacion'] ?? 1, 1.0),
            );

            return ['mensaje' => 'ok', 'asiento' => $asiento];
        } catch (\Throwable $e) {
            Log::warning('pagoproveedor.genera_asiento.fallo', [
                'mensaje' => $e->getMessage(),
            ]);

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }
    }

    /**
     * @return array{mensaje?:string,errores?:string,pagoproveedor_id?:int}
     */
    public function guardaPago(Request $request): array
    {
        $data = $request->all();
        $empresaId = (int) ($data['empresa_id'] ?? 0);

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            (string) ($data['fecha'] ?? date('Y-m-d')),
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        try {
            $estado = (string) ($data['estado'] ?? 'CONFIRMADA');
            if (! in_array($estado, ['PRE CARGA', 'CONFIRMADA'], true)) {
                $estado = 'CONFIRMADA';
            }
            // Validar asiento ANTES de abrir TX / numerar OP / tocar Anita.
            $this->assertAsientoBalanceadoAntesDeGrabar($data, $estado);
            // Preflight numeradores Anita (solo lectura): evita quemar correlativos si falta retención/OPP.
            $tipoComprobante = $this->resolverTipoComprobanteAlta($data);
            $this->assertNumeradoresAnitaAntesDeGrabar($empresaId, $data, $estado, $tipoComprobante);

            $pago = DB::transaction(function () use ($data, $request, $empresaId, $estado, $tipoComprobante) {
                $numero = PagoproveedorAnitaNumeracionSupport::siguienteNumeroConLock($empresaId, $tipoComprobante);
                $sucursal = PagoproveedorAnitaNumeracionSupport::sucursalParaOp($empresaId);

                $cbuElegido = ProveedorCbuPagoSupport::resolverDesdeRequest(
                    (int) $data['proveedor_id'],
                    $data['proveedor_formapago_id'] ?? null,
                    $data['cbu_pago'] ?? null
                );

                $monedaId = (int) ($data['moneda_id'] ?? 1);
                $cotizacion = PagoproveedorAsientoArmadoSupport::cotizacionUnicaDelPago(
                    $monedaId,
                    NumeroDecimalLocalSupport::aFloat($data['cotizacion'] ?? 1, 1.0),
                    (string) ($data['fecha'] ?? date('Y-m-d'))
                );

                $pago = $this->pagoproveedorRepository->create([
                    'empresa_id' => $empresaId,
                    'tipotransaccion_caja_id' => $this->tipotransaccionCajaIdParaComprobante(
                        $tipoComprobante,
                        $data['tipotransaccion_caja_id'] ?? null
                    ),
                    'tipocomprobante' => $tipoComprobante,
                    'letra' => (string) config('pagoproveedor.letra_default', ' '),
                    'sucursal' => $sucursal,
                    'numerotransaccion' => (string) $numero,
                    'fecha' => $data['fecha'],
                    'caja_id' => ($data['caja_id'] ?? null) ?: null,
                    'proveedor_id' => (int) $data['proveedor_id'],
                    'proveedor_formapago_id' => $cbuElegido['proveedor_formapago_id'] ?? null,
                    'cbu_pago' => $cbuElegido['cbu_pago'] ?? null,
                    'detalle' => (string) ($data['detalle'] ?? ('Orden de pago Nro. '.$numero)),
                    'estado' => $estado,
                    'monto' => (float) ($data['monto'] ?? $data['totalfinalpago'] ?? 0),
                    'cotizacion' => $cotizacion,
                    'moneda_id' => $monedaId,
                    'modo_cotizacion' => (string) ($data['modo_cotizacion'] ?? config('pagoproveedor.modo_cotizacion_default', 'factura')),
                    'usuario_id' => Auth::id(),
                ]);

                $this->registrarEstado($pago, $estado, 'Alta de orden de pago');
                $this->persistirDetalle($pago, $data, $request, true);

                return $pago;
            });

            $avisoAnita = null;
            try {
                $this->sincronizarAnitaTesoreria($pago->fresh(), false);
            } catch (\Throwable $eAnita) {
                // La OP ya está commitida en ERP; no fingir que "no grabó" (evita reintentos duplicados).
                Log::error('pagoproveedor.anita.sync_post_commit.fallo', [
                    'pagoproveedor_id' => $pago->id,
                    'numero' => $pago->numerotransaccion,
                    'mensaje' => $eAnita->getMessage(),
                ]);
                $avisoAnita = 'OP grabada en ERP (#'.$pago->numerotransaccion
                    .') pero falló la réplica Anita: '.$eAnita->getMessage()
                    .'. No vuelva a grabar la misma OP; revise sincronización/auditoría.';
            }

            $out = [
                'mensaje' => 'ok',
                'pagoproveedor_id' => $pago->id,
                'numerotransaccion' => (string) $pago->numerotransaccion,
            ];
            if ($avisoAnita !== null) {
                $out['aviso'] = $avisoAnita;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('pagoproveedor.guardar.fallo', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
            ]);

            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * @return array{mensaje?:string,errores?:string}
     */
    public function actualizaPago(Request $request, int $id): array
    {
        $data = $request->all();
        $empresaId = (int) ($data['empresa_id'] ?? 0);

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            (string) ($data['fecha'] ?? date('Y-m-d')),
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        try {
            $pagoCandado = $this->pagoproveedorRepository->findOrFail($id);
            PagoproveedorEdicionCandadoSupport::assertEditable($pagoCandado);

            $estado = (string) ($data['estado'] ?? 'CONFIRMADA');
            if (! in_array($estado, ['PRE CARGA', 'CONFIRMADA'], true)) {
                $estado = (string) ($pagoCandado->estado ?? 'CONFIRMADA');
            }
            $this->assertAsientoBalanceadoAntesDeGrabar($data, $estado);

            DB::transaction(function () use ($data, $request, $id) {
                $pago = $this->pagoproveedorRepository->findOrFail($id);
                PagoproveedorEdicionCandadoSupport::assertEditable($pago);
                if (in_array($pago->estado, Pagoproveedor::estadosFinalesBloqueados(), true)) {
                    throw new Exception('No se puede modificar una OP en estado '.$pago->estado.'.');
                }

                $cbuElegido = ProveedorCbuPagoSupport::resolverDesdeRequest(
                    (int) $data['proveedor_id'],
                    $data['proveedor_formapago_id'] ?? null,
                    $data['cbu_pago'] ?? null
                );

                $monedaId = (int) ($data['moneda_id'] ?? $pago->moneda_id ?? 1);
                $cotizacion = PagoproveedorAsientoArmadoSupport::cotizacionUnicaDelPago(
                    $monedaId,
                    NumeroDecimalLocalSupport::aFloat(
                        $data['cotizacion'] ?? $pago->cotizacion ?? 1,
                        1.0
                    ),
                    (string) ($data['fecha'] ?? $pago->fecha?->format('Y-m-d') ?? date('Y-m-d'))
                );

                $this->pagoproveedorRepository->update([
                    'fecha' => $data['fecha'],
                    'caja_id' => ($data['caja_id'] ?? null) ?: null,
                    'proveedor_id' => (int) $data['proveedor_id'],
                    'proveedor_formapago_id' => $cbuElegido['proveedor_formapago_id'] ?? null,
                    'cbu_pago' => $cbuElegido['cbu_pago'] ?? null,
                    'detalle' => (string) ($data['detalle'] ?? $pago->detalle),
                    'estado' => (string) ($data['estado'] ?? $pago->estado),
                    'monto' => (float) ($data['monto'] ?? $data['totalfinalpago'] ?? $pago->monto),
                    'cotizacion' => $cotizacion,
                    'moneda_id' => $monedaId,
                    'modo_cotizacion' => (string) ($data['modo_cotizacion'] ?? $pago->modo_cotizacion),
                    'tipotransaccion_caja_id' => ($data['tipotransaccion_caja_id'] ?? null)
                        ?: ($pago->tipotransaccion_caja_id ?: IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorConfig() ?: null),
                ], $id);

                $pago = $this->pagoproveedorRepository->findOrFail($id);
                $this->registrarEstado($pago, (string) $pago->estado, 'Actualización de orden de pago');
                $this->persistirDetalle($pago, $data, $request, false);
            });

            $pago = $this->pagoproveedorRepository->findOrFail($id);
            $avisoAnita = null;
            try {
                $this->sincronizarAnitaTesoreria($pago, true);
            } catch (\Throwable $eAnita) {
                Log::error('pagoproveedor.actualizar.anita.sync_post_commit.fallo', [
                    'pagoproveedor_id' => $pago->id,
                    'numero' => $pago->numerotransaccion,
                    'mensaje' => $eAnita->getMessage(),
                ]);
                $avisoAnita = 'OP actualizada en ERP (#'.$pago->numerotransaccion
                    .') pero falló la réplica Anita: '.$eAnita->getMessage()
                    .'. No vuelva a grabar la misma OP; revise sincronización/auditoría.';
            }

            $out = ['mensaje' => 'ok'];
            if ($avisoAnita !== null) {
                $out['aviso'] = $avisoAnita;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('pagoproveedor.actualizar.fallo', [
                'id' => $id,
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
            ]);

            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistirDetalle(Pagoproveedor $pago, array $data, Request $request, bool $esAlta): void
    {
        $aplicaciones = $this->resolverAplicacionesDesdeRequest($data);
        PagoproveedorAplicacionCuentacorrienteSupport::reemplazarAplicaciones($pago, $aplicaciones);

        $anticipo = (float) ($data['anticipo'] ?? $data['totalanticipo'] ?? 0);
        if ($anticipo <= 0 && ! $this->hayAplicacionConMonto($aplicaciones)) {
            $anticipo = abs((float) ($pago->monto ?? $data['monto'] ?? 0));
        }
        if ($anticipo > 0) {
            PagoproveedorAplicacionCuentacorrienteSupport::crearAnticipo(
                $pago,
                $anticipo,
                (int) ($pago->moneda_id ?: ($data['moneda_id'] ?? 1)),
                (float) ($pago->cotizacion ?: 1),
            );
            if (! $this->hayAplicacionConMonto($aplicaciones)) {
                $this->marcarComoOpa($pago);
            }
        }

        $this->persistirRetenciones($pago, $data);

        $cajaMovimientoId = $this->persistirCajaMovimiento($pago, $data, $esAlta);
        $data['pagoproveedor_id'] = $pago->id;
        $data['proveedor_emitido_ids'] = $data['proveedor_emitido_ids']
            ?? array_fill(0, count($data['numerocheque_emitidos'] ?? []), $pago->proveedor_id);

        if ($cajaMovimientoId > 0) {
            $this->chequeRepository->guardarChequeIngresoEgreso(
                $data,
                $esAlta ? 'create' : 'update',
                $cajaMovimientoId
            );
            Cheque::query()
                ->where('caja_movimiento_id', $cajaMovimientoId)
                ->whereNull('pagoproveedor_id')
                ->update(['pagoproveedor_id' => $pago->id]);
        }

        if ($pago->estado !== 'PRE CARGA') {
            // El asiento del form puede quedar viejo (p.ej. se abrió antes de cargar caja).
            // Salvo edición manual, se rearma desde deuda/medios/retenciones.
            if (! $this->asientoFueEditadoManual($data)) {
                $data = array_merge($data, $this->construirArraysAsientoDesdeOperacion($pago, $data));
            }
            if (empty($data['cuentacontable_ids'])) {
                throw new Exception('No se pudo armar el asiento contable de la OP (sin líneas).');
            }
            $this->persistirAsiento($pago, $data);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function asientoFueEditadoManual(array $data): bool
    {
        return AsientoCargaManualSupport::fueEditadoManual(
            $data['carga_cuentacontable_manuales'] ?? []
        );
    }

    /**
     * Solo lectura: OP + certificados de retención que se van a emitir.
     * Debe correr ANTES de cualquier siguienteNumeroConLock (Anita no revierte al rollback MySQL).
     *
     * @param  array<string, mixed>  $data
     */
    private function assertNumeradoresAnitaAntesDeGrabar(
        int $empresaId,
        array $data,
        string $estado,
        ?string $tipoComprobante = null
    ): void {
        if ($estado === 'PRE CARGA' || ! PagoproveedorAnitaNumeracionSupport::estaHabilitada()) {
            return;
        }

        PagoproveedorAnitaNumeracionSupport::assertNumeradorDisponible($empresaId, $tipoComprobante);

        $tipos = $this->tiposRetencionConImporteDesdeRequest($data);
        if ($tipos === []) {
            $tipos = $this->tiposRetencionPrevistosPorCalculo($empresaId, $data);
        }
        foreach ($tipos as $tipo) {
            PagoproveedorAnitaRetencionNumeracionSupport::assertNumeradorDisponible($tipo, $empresaId);
        }
    }

    /**
     * Misma lógica que persistirRetenciones, sin escribir: qué tipos saldrían con importe.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function tiposRetencionPrevistosPorCalculo(int $empresaId, array $data): array
    {
        $proveedorId = (int) ($data['proveedor_id'] ?? 0);
        if ($proveedorId <= 0) {
            return [];
        }
        $proveedor = Proveedor::query()->with(['condicionivas', 'condicionIIBBs'])->find($proveedorId);
        if ($proveedor === null) {
            return [];
        }

        $aplicaciones = $this->aplicacionesDesdeData($data);
        $ctx = $this->retencionesPagoContextoBuilder->armarInput(
            proveedor: $proveedor,
            aplicaciones: $aplicaciones,
            fecha: (string) ($data['fecha'] ?? date('Y-m-d')),
            empresaId: $empresaId ?: null,
            monedaPagoId: (int) ($data['moneda_id'] ?? 1),
            cotizacionPago: NumeroDecimalLocalSupport::aFloat($data['cotizacion'] ?? 0) ?: null,
            excluirPagoproveedorId: null,
            overrides: [
                'retencionganancia_id' => $data['retencionganancia_id'] ?? null,
                'retencioniva_id' => $data['retencioniva_id'] ?? null,
                'retencionsuss_id' => $data['retencionsuss_id'] ?? null,
                'iibb_provincia_id' => $data['iibb_provincia_id'] ?? null,
                'iibb_tasa' => $data['iibb_tasa'] ?? null,
                'calcular_ganancias' => $data['calcular_ganancias'] ?? true,
                'calcular_iva' => $data['calcular_iva'] ?? true,
                'calcular_suss' => $data['calcular_suss'] ?? true,
                'calcular_iibb' => $data['calcular_iibb'] ?? true,
            ],
            importeNetoFallback: (float) ($data['importe_neto_retencion'] ?? $data['monto'] ?? $data['totalfinalpago'] ?? 0),
            importeIvaFallback: (float) ($data['importe_iva_retencion'] ?? 0),
        );
        $resultado = $this->retencionesPagoCalculator->calcular($ctx['input']);

        $tipos = [];
        if ($resultado->ganancias->aplica && $resultado->ganancias->importeRetencion > 0) {
            $tipos[] = Pagoproveedor_Retencion::TIPO_GANANCIAS;
        }
        if ($resultado->iva->aplica && $resultado->iva->importeRetencion > 0) {
            $tipos[] = Pagoproveedor_Retencion::TIPO_IVA;
        }
        if ($resultado->suss->aplica && $resultado->suss->importeRetencion > 0) {
            $tipos[] = Pagoproveedor_Retencion::TIPO_SUSS;
        }
        if ($resultado->iibb->aplica && $resultado->iibb->importeRetencion > 0) {
            $tipos[] = Pagoproveedor_Retencion::TIPO_IIBB;
        }

        return $tipos;
    }

    /**
     * Tipos de retención con importe > 0 según el JSON de pantalla o flags de cálculo.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function tiposRetencionConImporteDesdeRequest(array $data): array
    {
        $tipos = [];
        $raw = $data['pp_retenciones_json'] ?? $data['retenciones_json'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $mapa = [
                    'ganancias' => Pagoproveedor_Retencion::TIPO_GANANCIAS,
                    'iva' => Pagoproveedor_Retencion::TIPO_IVA,
                    'suss' => Pagoproveedor_Retencion::TIPO_SUSS,
                    'iibb' => Pagoproveedor_Retencion::TIPO_IIBB,
                ];
                foreach ($mapa as $clave => $tipo) {
                    $fila = $decoded[$clave] ?? null;
                    if (! is_array($fila)) {
                        continue;
                    }
                    if (! empty($fila['aplica']) && (float) ($fila['importe'] ?? 0) > 0) {
                        $tipos[] = $tipo;
                    }
                }

                return $tipos;
            }
        }

        // Fallback: si la pantalla mandó importes sueltos.
        if (NumeroDecimalLocalSupport::aFloat($data['retencion_ganancias'] ?? 0) > 0) {
            $tipos[] = Pagoproveedor_Retencion::TIPO_GANANCIAS;
        }
        if (NumeroDecimalLocalSupport::aFloat($data['retencion_iva'] ?? 0) > 0) {
            $tipos[] = Pagoproveedor_Retencion::TIPO_IVA;
        }
        if (NumeroDecimalLocalSupport::aFloat($data['retencion_suss'] ?? 0) > 0) {
            $tipos[] = Pagoproveedor_Retencion::TIPO_SUSS;
        }
        if (NumeroDecimalLocalSupport::aFloat($data['retencion_iibb'] ?? 0) > 0) {
            $tipos[] = Pagoproveedor_Retencion::TIPO_IIBB;
        }

        return $tipos;
    }

    /**
     * Rechaza OP CONFIRMADA con asiento desbalanceado antes de abrir TX / numerar / tocar Anita.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertAsientoBalanceadoAntesDeGrabar(array $data, string $estado): void
    {
        if ($estado === 'PRE CARGA') {
            return;
        }

        $cuentas = $data['cuentacontable_ids'] ?? [];
        if (! is_array($cuentas) || $cuentas === []) {
            throw new Exception(
                'Falta el asiento contable. Abra la pestaña Asiento Contable (o revise medios/deuda) antes de grabar.'
            );
        }

        AsientoBalanceSupport::assertBalanceadoDesdePayload([
            'debes' => $data['debeasientos'] ?? $data['debes'] ?? [],
            'haberes' => $data['haberasientos'] ?? $data['haberes'] ?? [],
        ], 'asiento de la orden de pago');
    }

    /**
     * Rearma arrays de asiento (cuentacontable_ids / debe / haber) desde la operación.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function construirArraysAsientoDesdeOperacion(Pagoproveedor $pago, array $data): array
    {
        $datosCaja = [];
        foreach ($data['cuentacaja_ids'] ?? [] as $i => $cid) {
            $cid = (int) $cid;
            $monto = NumeroDecimalLocalSupport::aFloat($data['montos'][$i] ?? 0);
            if ($cid <= 0 || $monto <= 0) {
                continue;
            }
            $datosCaja[] = [
                'cuentacaja_ids' => $cid,
                'moneda_ids' => (int) ($data['moneda_ids'][$i] ?? $pago->moneda_id ?? 1),
                'montos' => $monto,
                'cotizaciones' => NumeroDecimalLocalSupport::aFloat(
                    $data['cotizaciones'][$i] ?? $pago->cotizacion ?? 1,
                    1.0
                ),
                'observaciones' => (string) ($data['observaciones'][$i] ?? ''),
            ];
        }

        $datosChequesEmitidos = [];
        foreach ($data['montocheque_emitidos'] ?? [] as $i => $montoRaw) {
            $monto = NumeroDecimalLocalSupport::aFloat($montoRaw);
            if ($monto <= 0) {
                continue;
            }
            $datosChequesEmitidos[] = [
                'cuentacaja_ids' => (int) ($data['cuentacaja_emitido_ids'][$i] ?? 0),
                'moneda_ids' => (int) ($data['moneda_emitido_ids'][$i] ?? $pago->moneda_id ?? 1),
                'montos' => $monto,
                'cotizaciones' => NumeroDecimalLocalSupport::aFloat(
                    $data['cotizacioncheque_emitidos'][$i] ?? 1,
                    1.0
                ),
                'fechapagos' => (string) ($data['fechapago_emitidos'][$i] ?? $pago->fecha?->format('Y-m-d') ?? ''),
                'numerocheques' => (string) ($data['numerocheque_emitidos'][$i] ?? ''),
            ];
        }

        $datosChequesRecibidos = [];
        foreach ($data['montocheque_recibidos'] ?? [] as $i => $montoRaw) {
            $monto = NumeroDecimalLocalSupport::aFloat($montoRaw);
            if ($monto <= 0) {
                continue;
            }
            $datosChequesRecibidos[] = [
                'moneda_ids' => (int) ($data['monedacheque_recibido_ids'][$i] ?? $pago->moneda_id ?? 1),
                'montos' => $monto,
                'cotizaciones' => NumeroDecimalLocalSupport::aFloat(
                    $data['cotizacioncheque_recibidos'][$i] ?? 1,
                    1.0
                ),
            ];
        }

        $datosComprobantes = [];
        foreach ($data['idcuentacorrientes'] ?? [] as $i => $ccId) {
            $ccId = (int) $ccId;
            $monto = NumeroDecimalLocalSupport::aFloat($data['montoaplicadocomprobantes'][$i] ?? 0);
            if ($ccId <= 0 || $monto <= 0) {
                continue;
            }
            $datosComprobantes[] = [
                'proveedor_cuentacorriente_ids' => $ccId,
                'montos' => $monto,
                'moneda_ids' => (int) ($data['monedacomprobante_ids'][$i] ?? $pago->moneda_id ?? 1),
                'cotizaciones' => NumeroDecimalLocalSupport::aFloat(
                    $data['cotizacioncomprobantes'][$i] ?? $pago->cotizacion ?? 1,
                    1.0
                ),
                'cotizacion_aplicadas' => NumeroDecimalLocalSupport::aFloat(
                    $data['cotizacion_aplicada_dia'][$i] ?? $data['cotizacioncomprobantes'][$i] ?? 1,
                    1.0
                ),
                'diferencias_cambio' => NumeroDecimalLocalSupport::aFloat($data['diferencias_cambio'][$i] ?? 0),
            ];
        }

        $datosRetenciones = [];
        foreach (
            Pagoproveedor_Retencion::query()->where('pagoproveedor_id', $pago->id)->get() as $ret
        ) {
            $importe = abs((float) ($ret->importe ?? 0));
            if ($importe <= 0) {
                continue;
            }
            $datosRetenciones[] = [
                'tiporetencion' => (string) $ret->tiporetencion,
                'montos' => $importe,
                'moneda_ids' => (int) ($ret->moneda_id ?: $pago->moneda_id ?: 1),
                'cotizaciones' => (float) ($ret->cotizacion ?: $pago->cotizacion ?: 1),
                'provincia_id' => $ret->provincia_id,
            ];
        }

        $proveedorNombre = (string) (
            $pago->proveedores->nombre
            ?? Proveedor::query()->find((int) $pago->proveedor_id)?->nombre
            ?? ''
        );

        $lineas = PagoproveedorAsientoArmadoSupport::armar(
            $datosCaja,
            [],
            $datosChequesEmitidos,
            $datosChequesRecibidos,
            $datosComprobantes,
            $datosRetenciones,
            (int) $pago->empresa_id,
            (int) $pago->proveedor_id,
            $pago->fecha?->format('Y-m-d') ?? date('Y-m-d'),
            $this->cuentacajaRepository,
            $this->cuentacontableRepository,
            $proveedorNombre,
            (string) ($pago->numerotransaccion ?? ''),
            (int) ($pago->moneda_id ?: 1),
            (float) ($pago->cotizacion ?: 1),
        );

        $out = [
            'cuentacontable_ids' => [],
            'centrocostoasiento_ids' => [],
            'monedaasiento_ids' => [],
            'debeasientos' => [],
            'haberasientos' => [],
            'cotizacionasientos' => [],
            'observacionasientos' => [],
            'carga_cuentacontable_manuales' => [],
        ];

        foreach ($lineas as $linea) {
            $out['cuentacontable_ids'][] = $linea['cuentacontable_id'];
            $out['centrocostoasiento_ids'][] = $linea['centrocosto_id'] ?? 0;
            $out['monedaasiento_ids'][] = $linea['moneda_id'] ?? 1;
            $out['debeasientos'][] = $linea['debe'] === '' || $linea['debe'] === null ? '' : $linea['debe'];
            $out['haberasientos'][] = $linea['haber'] === '' || $linea['haber'] === null ? '' : $linea['haber'];
            $out['cotizacionasientos'][] = $linea['cotizacion'] ?? 1;
            $out['observacionasientos'][] = $linea['observacion'] ?? '';
            $out['carga_cuentacontable_manuales'][] = $linea['carga_cuentacontable_manual'] ?? 'N';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function resolverAplicacionesDesdeRequest(array $data): array
    {
        $ids = $data['idcuentacorrientes'] ?? $data['proveedor_cuentacorriente_ids'] ?? [];
        $montos = $data['montoaplicadocomprobantes'] ?? $data['montos_aplicados'] ?? [];
        $monedas = $data['monedacomprobante_ids'] ?? $data['moneda_aplicada_ids'] ?? [];
        $cotizaciones = $data['cotizacioncomprobantes'] ?? $data['cotizaciones_aplicadas'] ?? [];
        $cotDia = $data['cotizacion_aplicada_dia'] ?? [];
        $dcs = $data['diferencias_cambio'] ?? [];

        $out = [];
        foreach ($ids as $i => $ccId) {
            $out[] = [
                'proveedor_cuentacorriente_id' => (int) $ccId,
                'montoaplicado' => (float) ($montos[$i] ?? 0),
                'moneda_id' => (int) ($monedas[$i] ?? ($data['moneda_id'] ?? 1)),
                'cotizacion' => (float) ($cotizaciones[$i] ?? ($data['cotizacion'] ?? 1)),
                'cotizacion_aplicada' => isset($cotDia[$i]) ? (float) $cotDia[$i] : null,
                'diferencia_cambio' => isset($dcs[$i]) ? (float) $dcs[$i] : null,
            ];
        }

        // La propuesta ya filtra las retenidas, pero acá también llega el armado manual de la OP
        // con ids de cuenta corriente elegidos a mano: el bloqueo tiene que valer igual.
        app(ComprobanteProveedorBloqueoPagoService::class)->assertNingunaBloqueada(
            array_map(static fn (array $a) => (int) $a['proveedor_cuentacorriente_id'], $out)
        );

        return $out;
    }

    /**
     * pago.c: ADELANTO fija in_tcomp=OPA antes de nro_op(). Hay que numerar con ese
     * comprobante (Ferli t_comp OPA; MultiEmpresa sigue en O{n}).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolverTipoComprobanteAlta(array $data): string
    {
        $tipoCajaId = (int) ($data['tipotransaccion_caja_id'] ?? 0);
        $abrevCaja = $tipoCajaId > 0
            ? IngresoEgresoAnitaNumeracionSupport::abreviaturaTipo($tipoCajaId)
            : '';

        return PagoproveedorAnitaNumeracionSupport::normalizarTipoComprobante(
            $abrevCaja === 'OPA' ? 'OPA' : (string) ($data['tipocomprobante'] ?? ''),
            $this->esAnticipoSinAplicaciones($data)
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function esAnticipoSinAplicaciones(array $data): bool
    {
        $aplicaciones = $this->resolverAplicacionesDesdeRequest($data);
        if ($this->hayAplicacionConMonto($aplicaciones)) {
            return false;
        }

        $anticipo = (float) ($data['anticipo'] ?? $data['totalanticipo'] ?? 0);
        if ($anticipo <= 0) {
            $anticipo = abs((float) ($data['monto'] ?? $data['totalfinalpago'] ?? 0));
        }

        return $anticipo > 0.01;
    }

    private function tipotransaccionCajaIdParaComprobante(string $tipoComprobante, mixed $tipoCajaIdRequest): ?int
    {
        if (PagoproveedorAnitaNumeracionSupport::esTipoOpa($tipoComprobante)) {
            $tipoOpaId = IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorAbreviaturaPublica('OPA');
            if ($tipoOpaId > 0) {
                return $tipoOpaId;
            }
        }

        $tipoCajaId = (int) ($tipoCajaIdRequest ?: 0);
        if ($tipoCajaId > 0) {
            return $tipoCajaId;
        }

        $porConfig = IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorConfig();

        return $porConfig ?: null;
    }

    /**
     * @param  list<array<string, mixed>>  $aplicaciones
     */
    private function hayAplicacionConMonto(array $aplicaciones): bool
    {
        foreach ($aplicaciones as $apl) {
            if ((int) ($apl['proveedor_cuentacorriente_id'] ?? 0) > 0
                && abs((float) ($apl['montoaplicado'] ?? 0)) > 0.01) {
                return true;
            }
        }

        return false;
    }

    private function marcarComoOpa(Pagoproveedor $pago): void
    {
        $tipoOpaId = IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorAbreviaturaPublica('OPA');
        $upd = ['tipocomprobante' => 'OPA'];
        if ($tipoOpaId > 0) {
            $upd['tipotransaccion_caja_id'] = $tipoOpaId;
        }
        $this->pagoproveedorRepository->update($upd, $pago->id);
        $pago->tipocomprobante = 'OPA';
        if ($tipoOpaId > 0) {
            $pago->tipotransaccion_caja_id = $tipoOpaId;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistirRetenciones(Pagoproveedor $pago, array $data): void
    {
        $proveedor = Proveedor::query()->with(['condicionivas', 'condicionIIBBs'])->find((int) $pago->proveedor_id);
        if ($proveedor === null) {
            return;
        }

        $aplicaciones = $this->aplicacionesDesdeData($data);
        $ctx = $this->retencionesPagoContextoBuilder->armarInput(
            proveedor: $proveedor,
            aplicaciones: $aplicaciones,
            fecha: $pago->fecha?->format('Y-m-d'),
            empresaId: (int) $pago->empresa_id ?: null,
            monedaPagoId: (int) ($pago->moneda_id ?: 1),
            cotizacionPago: (float) ($pago->cotizacion ?: 0) ?: null,
            excluirPagoproveedorId: (int) $pago->id ?: null,
            overrides: [
                'retencionganancia_id' => $data['retencionganancia_id'] ?? null,
                'retencioniva_id' => $data['retencioniva_id'] ?? null,
                'retencionsuss_id' => $data['retencionsuss_id'] ?? null,
                'iibb_provincia_id' => $data['iibb_provincia_id'] ?? null,
                'iibb_tasa' => $data['iibb_tasa'] ?? null,
                'calcular_ganancias' => $data['calcular_ganancias'] ?? true,
                'calcular_iva' => $data['calcular_iva'] ?? true,
                'calcular_suss' => $data['calcular_suss'] ?? true,
                'calcular_iibb' => $data['calcular_iibb'] ?? true,
            ],
            importeNetoFallback: (float) ($data['importe_neto_retencion'] ?? $data['monto'] ?? $pago->monto),
            importeIvaFallback: (float) ($data['importe_iva_retencion'] ?? 0),
        );

        $resultado = $this->retencionesPagoCalculator->calcular($ctx['input']);

        PagoproveedorRetencionPersistenciaSupport::reemplazarDesdeResultado(
            $pago,
            $resultado,
            (int) $pago->moneda_id,
            (float) $pago->cotizacion,
            $pago->estado !== 'PRE CARGA',
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function aplicacionesDesdeData(array $data): array
    {
        $ids = $data['idcuentacorrientes'] ?? [];
        $montos = $data['montoaplicadocomprobantes'] ?? [];
        $cots = $data['cotizacion_aplicada_dia'] ?? ($data['cotizacioncomprobantes'] ?? []);
        $monedas = $data['monedacomprobante_ids'] ?? [];
        $out = [];
        foreach ($ids as $i => $ccId) {
            $ccId = (int) $ccId;
            if ($ccId <= 0) {
                continue;
            }
            $out[] = [
                'proveedor_cuentacorriente_id' => $ccId,
                'montoaplicado' => (float) ($montos[$i] ?? 0),
                'cotizacion_aplicada' => (float) ($cots[$i] ?? 0),
                'moneda_id' => isset($monedas[$i]) ? (int) $monedas[$i] : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistirCajaMovimiento(Pagoproveedor $pago, array $data, bool $esAlta): int
    {
        $cuentacajaIds = array_values(array_filter(
            $data['cuentacaja_ids'] ?? [],
            static fn ($id) => (int) $id > 0
        ));
        $data['cuentacaja_ids'] = $cuentacajaIds;

        if (
            $cuentacajaIds === []
            && empty($data['numerocheque_emitidos'])
            && empty($data['numerocheque_recibidos'])
        ) {
            return (int) ($pago->caja_movimiento_id ?? 0);
        }

        $payload = $data;
        $payload['moneda_ids'] = array_values($payload['moneda_ids'] ?? []);
        $payload['montos'] = array_values($payload['montos'] ?? []);
        $payload['observaciones'] = array_values($payload['observaciones'] ?? []);
        $tipoOppId = (int) ($pago->tipotransaccion_caja_id ?: IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorConfig());
        if ($tipoOppId <= 0) {
            throw new Exception('No hay tipo de transacción OPP configurado para el movimiento de caja de la OP.');
        }

        // Una sola TC del pago (pago.c in_cotizacion) en medios de caja.
        $fechaPago = $pago->fecha?->format('Y-m-d') ?? date('Y-m-d');
        $cotPago = PagoproveedorAsientoArmadoSupport::cotizacionUnicaDelPago(
            (int) ($pago->moneda_id ?: 1),
            (float) ($pago->cotizacion ?: 1),
            $fechaPago
        );
        if (abs((float) $pago->cotizacion - $cotPago) > 0.0001) {
            $pago->cotizacion = $cotPago;
            $pago->save();
        }
        $monedasCaja = array_values($payload['moneda_ids'] ?? []);
        $cotsCaja = [];
        foreach (array_values($payload['cuentacaja_ids'] ?? []) as $i => $_cid) {
            $monLin = (int) ($monedasCaja[$i] ?? $pago->moneda_id ?? 1);
            $cotsCaja[] = PagoproveedorAsientoArmadoSupport::cotizacionParaLinea($monLin, $cotPago);
        }
        if ($cotsCaja !== []) {
            $payload['cotizaciones'] = $cotsCaja;
        }

        $payload['empresa_id'] = $pago->empresa_id;
        $payload['fecha'] = $pago->fecha?->format('Y-m-d');
        $payload['caja_id'] = $pago->caja_id;
        $payload['detalle'] = $pago->detalle;
        $payload['pagoproveedor_id'] = $pago->id;
        $payload['proveedor_id'] = $pago->proveedor_id;
        $payload['monto'] = $pago->monto;
        $payload['usuario_id'] = Auth::id();
        $payload['tipotransaccion_caja_id'] = $tipoOppId;
        $payload['numerotransaccion'] = (string) $pago->numerotransaccion;
        $payload['proveedor_formapago_id'] = $pago->proveedor_formapago_id;
        $payload['observaciones'] = $payload['observaciones'] ?? [];

        if (! $esAlta && $pago->caja_movimiento_id) {
            $this->cajaMovimientoRepository->update($payload, $pago->caja_movimiento_id);
            $cajaMovimientoId = (int) $pago->caja_movimiento_id;
            $this->cajaMovimientoCuentacajaRepository->create($payload, $cajaMovimientoId);
        } else {
            $cajaMovimiento = $this->cajaMovimientoRepository->create($payload);
            if (! $cajaMovimiento instanceof Caja_Movimiento) {
                throw new Exception('No se pudo grabar el movimiento de caja de la OP.');
            }
            $cajaMovimientoId = (int) $cajaMovimiento->id;
            $this->cajaMovimientoCuentacajaRepository->create($payload, $cajaMovimientoId);
            $this->pagoproveedorRepository->update([
                'caja_movimiento_id' => $cajaMovimientoId,
                'tipotransaccion_caja_id' => $tipoOppId,
            ], $pago->id);

            if (Schema::hasColumn('caja_movimiento', 'pagoproveedor_id')) {
                Caja_Movimiento::query()->where('id', $cajaMovimientoId)->update(['pagoproveedor_id' => $pago->id]);
            }
        }

        $estadoData = [
            'fechas' => [Carbon::now()],
            'estados' => [Caja_Movimiento_Estado::$enumEstado[0]['valor'] ?? 'ACTIVO'],
            'observacionestados' => ['Movimiento de caja OP '.$pago->numerotransaccion],
            'usuario_ids' => [Auth::id()],
        ];
        $this->cajaMovimientoEstadoRepository->create($estadoData, $cajaMovimientoId);

        return $cajaMovimientoId;
    }

    /**
     * Replica pago/auxpag/tesmov en Anita (mismo camino que Ingreso/Egreso OPP).
     */
    public function sincronizarAnitaTesoreria(Pagoproveedor $pago, bool $reemplazar): void
    {
        if ((string) $pago->estado === 'PRE CARGA') {
            return;
        }

        $cajaId = (int) ($pago->caja_movimiento_id ?? 0);
        if ($cajaId <= 0) {
            Log::warning('pagoproveedor.anita.sin_movimiento_caja', [
                'pagoproveedor_id' => $pago->id,
                'numero' => $pago->numerotransaccion,
            ]);
            // Sin tesorería local igual hay que espejar CC (promov/aplmovp) y retenciones.
            $this->cuentacorrienteAnitaSyncService->syncPorPagoproveedor((int) $pago->id);
            PagoproveedorAnitaRetencionEscrituraSupport::sincronizarDesdePago($pago->fresh(), $reemplazar);

            return;
        }

        $movimiento = Caja_Movimiento::query()->find($cajaId);
        if ($movimiento === null) {
            PagoproveedorAnitaRetencionEscrituraSupport::sincronizarDesdePago($pago->fresh(), $reemplazar);

            return;
        }

        if ($reemplazar) {
            IngresoEgresoAnitaTesmovSupport::eliminarDesdeMovimiento($movimiento);
        }

        IngresoEgresoAnitaTesmovSupport::grabarDesdeMovimiento($movimiento->fresh());
        $this->cuentacorrienteAnitaSyncService->syncPorPagoproveedor((int) $pago->id);
        PagoproveedorAnitaRetencionEscrituraSupport::sincronizarDesdePago($pago->fresh(), $reemplazar);
        $this->endosarChequesTercerosAnita($pago->fresh());
    }

    /**
     * Marca en Anita (ctermae) los CHT entregados con esta OP.
     */
    private function endosarChequesTercerosAnita(Pagoproveedor $pago): void
    {
        $cheques = Cheque::query()
            ->where('pagoproveedor_id', $pago->id)
            ->where('origen', 'R')
            ->whereNotNull('nro_interno_anita')
            ->get();
        if ($cheques->isEmpty()) {
            return;
        }

        $proveedor = $pago->proveedor_id
            ? Proveedor::query()->find($pago->proveedor_id)
            : null;
        $codigoProv = $proveedor ? (string) ($proveedor->codigo ?? '0') : '0';
        $nombreProv = $proveedor ? (string) ($proveedor->nombre ?? '') : '';

        ChequeTerceroEndosoAnitaSupport::marcarEndosoColeccion($cheques, [
            'fecha_acreed' => $pago->fecha ? (string) $pago->fecha : date('Y-m-d'),
            'nro_op' => (string) ($pago->numerotransaccion ?? ''),
            'proveedor_codigo' => $codigoProv,
            'cedio_a' => $codigoProv,
            'entregado_a' => $nombreProv,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistirAsiento(Pagoproveedor $pago, array $data): void
    {
        $tipoasiento = $this->tipoasientoRepository->findPorAbreviatura('TES');
        if (! $tipoasiento) {
            throw new Exception('No existe tipo de asiento TES.');
        }

        $payload = $data;
        $payload['tipoasiento_id'] = $tipoasiento->id;
        $payload['empresa_id'] = $pago->empresa_id;
        $payload['fecha'] = $pago->fecha?->format('Y-m-d');
        $payload['observacion'] = $pago->detalle;
        $payload['pagoproveedor_id'] = $pago->id;
        $payload['moneda_ids'] = $data['monedaasiento_ids'] ?? $data['moneda_ids'] ?? [];
        $payload['centrocosto_ids'] = $data['centrocostoasiento_ids'] ?? $data['centrocosto_ids'] ?? [];
        $payload['debes'] = $data['debeasientos'] ?? $data['debes'] ?? [];
        $payload['haberes'] = $data['haberasientos'] ?? $data['haberes'] ?? [];
        $payload['cotizaciones'] = $data['cotizacionasientos'] ?? $data['cotizaciones'] ?? [];
        $payload['observaciones'] = $data['observacionasientos'] ?? $data['observaciones'] ?? [];
        // Clave comprobante Anita (igual que IE OPP / a-movim MultiEmpresa): sin letra.
        $payload = array_merge($payload, $this->referenciaComprobanteCtamov($pago));

        $asientoId = (int) ($pago->asiento_id ?? 0);
        if ($asientoId <= 0) {
            $porPago = $this->asientoRepository->leeAsientoPorClave($pago->id, 'pagoproveedor_id');
            $ultimo = $porPago->sortByDesc('id')->first();
            if ($ultimo) {
                $asientoId = (int) $ultimo->id;
            }
        }
        if ($asientoId > 0) {
            try {
                $existente = $this->asientoRepository->find($asientoId);
            } catch (\Throwable $e) {
                $existente = null;
            }
            if ($existente) {
                $payload['tipoasiento_id'] = $existente->tipoasiento_id ?: $payload['tipoasiento_id'];
                $payload['numeroasiento'] = $existente->numeroasiento;
                $this->asientoRepository->update($payload, $asientoId);
                $this->asientoMovimientoRepository->update($payload, $asientoId);
                if ((int) ($pago->asiento_id ?? 0) !== $asientoId) {
                    $this->pagoproveedorRepository->update(['asiento_id' => $asientoId], $pago->id);
                }

                return;
            }
        }

        $asiento = $this->asientoRepository->create($payload);
        if ($asiento === 'Error' || ! $asiento) {
            throw new Exception('Error al grabar asiento de la OP.');
        }
        $this->asientoMovimientoRepository->create($payload, $asiento->id);
        $this->pagoproveedorRepository->update(['asiento_id' => $asiento->id], $pago->id);
    }

    /**
     * tipo/letra/sucursal/nro para ctamov (tesmov/pago usan la misma clave).
     *
     * @return array{tipo: string, letra: string, sucursal: int, nro: int}
     */
    private function referenciaComprobanteCtamov(Pagoproveedor $pago): array
    {
        $tipo = strtoupper(substr(trim((string) ($pago->tipocomprobante ?: 'OPP')), 0, 3));
        if ($tipo === '') {
            $tipo = 'OPP';
        }

        $letra = (string) config('caja.ingresoegreso_anita_tesmov_letra', ' ');
        if ($letra === '') {
            $letra = ' ';
        }

        $empresaAnita = PagoproveedorAnitaNumeracionSupport::codigoEmpresaAnita((int) $pago->empresa_id);
        if ($empresaAnita <= 0) {
            $empresaAnita = (int) $pago->empresa_id;
        }

        $sucursalCfg = config('caja.ingresoegreso_anita_tesmov_sucursal');
        $sucursal = $sucursalCfg === null || $sucursalCfg === ''
            ? $empresaAnita
            : (int) $sucursalCfg;

        return [
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'nro' => (int) $pago->numerotransaccion,
        ];
    }

    private function registrarEstado(Pagoproveedor $pago, string $estado, string $observacion): void
    {
        Pagoproveedor_Estado::query()->create([
            'pagoproveedor_id' => $pago->id,
            'fecha' => now(),
            'estado' => $estado,
            'usuario_id' => Auth::id(),
            'observacion' => $observacion,
        ]);
    }

    /**
     * Baja de OP en PRE CARGA.
     *
     * No usa anularFisicamente: PRE CARGA no replica Anita. Pero sí tiene que
     * revertirAplicacionesExistentes: el destroy solo deja ON DELETE SET NULL y el
     * saldo de la factura queda “aplicado” sin OP.
     *
     * @return array{mensaje?:string,errores?:string}
     */
    public function eliminarPreCarga(int $id): array
    {
        try {
            DB::transaction(function () use ($id) {
                $pago = Pagoproveedor::query()->whereKey($id)->lockForUpdate()->first();
                if ($pago === null) {
                    throw new Exception('Orden de pago no encontrada.');
                }
                if ((string) $pago->estado !== 'PRE CARGA') {
                    throw new Exception('Solo se pueden eliminar OP en PRE CARGA. Use anular o revertir.');
                }

                PagoproveedorAplicacionCuentacorrienteSupport::revertirAplicacionesExistentes($pago);

                Pagoproveedor_Retencion::query()->where('pagoproveedor_id', $id)->get()
                    ->each(fn (Pagoproveedor_Retencion $r) => $r->delete());

                Cheque::query()->where('pagoproveedor_id', $id)->get()
                    ->each(fn (Cheque $c) => $c->delete());

                $cajaIds = Caja_Movimiento::query()
                    ->where('pagoproveedor_id', $id)
                    ->pluck('id')
                    ->map(fn ($cid) => (int) $cid)
                    ->all();
                if ((int) ($pago->caja_movimiento_id ?? 0) > 0) {
                    $cajaIds[] = (int) $pago->caja_movimiento_id;
                }
                \App\Support\Caja\CajaMovimientoEloquentDeleteSupport::eliminarPorQuery(
                    Caja_Movimiento::query()->whereIn('id', array_unique(array_filter($cajaIds)))
                );

                // Si salió de una propuesta, liberar la línea para poder reejecutar.
                \App\Models\Compras\PropuestaPagoLinea::query()
                    ->where('pagoproveedor_id', $id)
                    ->update([
                        'pagoproveedor_id' => null,
                        'estado_linea' => 'PENDIENTE',
                    ]);

                $this->pagoproveedorRepository->delete($id);
            });

            return ['mensaje' => 'ok'];
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * PRE CARGA → CONFIRMADA; re-persiste retenciones y arma asiento si falta.
     *
     * @return array{mensaje?:string,errores?:string,aviso?:string}
     */
    public function confirmar(int $id): array
    {
        try {
            DB::transaction(function () use ($id) {
                $pago = Pagoproveedor::query()->whereKey($id)->lockForUpdate()->first();
                if ($pago === null) {
                    throw new Exception('Orden de pago no encontrada.');
                }
                if ((string) $pago->estado !== 'PRE CARGA') {
                    throw new Exception('Solo se puede confirmar una OP en PRE CARGA.');
                }

                PeriodoContableCierreSupport::assertOperacionPermitida(
                    (int) $pago->empresa_id,
                    $pago->fecha?->format('Y-m-d') ?? date('Y-m-d'),
                    PeriodoContableCierreSupport::ALCANCE_CAJA
                );

                $data = $this->armarDataDesdePagoPersistido($pago);
                $tipoComprobante = PagoproveedorAnitaNumeracionSupport::normalizarTipoComprobante(
                    (string) ($pago->tipocomprobante ?: 'OPP'),
                    strtoupper((string) ($pago->tipocomprobante ?? '')) === 'OPA'
                );
                $this->assertNumeradoresAnitaAntesDeGrabar(
                    (int) $pago->empresa_id,
                    $data,
                    'CONFIRMADA',
                    $tipoComprobante
                );

                $this->pagoproveedorRepository->update(['estado' => 'CONFIRMADA'], $id);
                $pago = $this->pagoproveedorRepository->findOrFail($id);
                $this->registrarEstado($pago, 'CONFIRMADA', 'Confirmación de orden de pago');

                // No pasar el monto de la OP como neto IIBB: un anticipo sin factura
                // no tiene destino BA y eso inventaba retención CABA.
                $this->persistirRetenciones($pago, array_merge($data, [
                    'calcular_ganancias' => true,
                    'calcular_iva' => true,
                    'calcular_suss' => true,
                    'calcular_iibb' => true,
                ]));

                $pago = $pago->fresh();
                if (! $pago->asiento_id) {
                    $data = array_merge($data, $this->construirArraysAsientoDesdeOperacion($pago, $data));
                    if (empty($data['cuentacontable_ids'])) {
                        throw new Exception(
                            'No se pudo armar el asiento contable al confirmar la OP #'
                            .$pago->numerotransaccion
                            .' (faltan medios de pago o aplicaciones). Edite la OP y complete caja/cheque.'
                        );
                    }
                    AsientoBalanceSupport::assertBalanceadoDesdePayload([
                        'debes' => $data['debeasientos'] ?? [],
                        'haberes' => $data['haberasientos'] ?? [],
                    ], 'asiento al confirmar OP #'.$pago->numerotransaccion);
                    $this->persistirAsiento($pago, $data);
                }
            });

            $pago = $this->pagoproveedorRepository->findOrFail($id);
            $avisoAnita = null;
            try {
                $this->sincronizarAnitaTesoreria($pago, true);
            } catch (\Throwable $eAnita) {
                Log::error('pagoproveedor.confirmar.anita.sync_post_commit.fallo', [
                    'pagoproveedor_id' => $pago->id,
                    'numero' => $pago->numerotransaccion,
                    'mensaje' => $eAnita->getMessage(),
                ]);
                $avisoAnita = 'OP confirmada en ERP (#'.$pago->numerotransaccion
                    .') pero falló la réplica Anita: '.$eAnita->getMessage()
                    .'. No vuelva a confirmar; revise sincronización/auditoría.';
            }

            $out = ['mensaje' => 'ok'];
            if ($avisoAnita !== null) {
                $out['aviso'] = $avisoAnita;
            }

            return $out;
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * Reconstruye el payload de medios/aplicaciones desde lo ya grabado (para confirmar).
     *
     * @return array<string, mixed>
     */
    private function armarDataDesdePagoPersistido(Pagoproveedor $pago): array
    {
        $pago->loadMissing([
            'pagoproveedor_comprobantes',
            'cheques',
            'caja_movimientos.caja_movimiento_cuentacajas',
        ]);

        $data = [
            'monto' => $pago->monto,
            'moneda_id' => $pago->moneda_id,
            'cotizacion' => $pago->cotizacion,
            'fecha' => $pago->fecha?->format('Y-m-d'),
            'proveedor_id' => $pago->proveedor_id,
            'empresa_id' => $pago->empresa_id,
            'idcuentacorrientes' => [],
            'montoaplicadocomprobantes' => [],
            'monedacomprobante_ids' => [],
            'cotizacioncomprobantes' => [],
            'cotizacion_aplicada_dia' => [],
            'diferencias_cambio' => [],
            'cuentacaja_ids' => [],
            'montos' => [],
            'moneda_ids' => [],
            'cotizaciones' => [],
            'observaciones' => [],
            'numerocheque_emitidos' => [],
            'montocheque_emitidos' => [],
            'chequera_emitido_ids' => [],
            'cuentacaja_emitido_ids' => [],
            'fechapago_emitidos' => [],
            'moneda_emitido_ids' => [],
            'cotizacioncheque_emitidos' => [],
        ];

        foreach ($pago->pagoproveedor_comprobantes as $pc) {
            $data['idcuentacorrientes'][] = (int) $pc->proveedor_cuentacorriente_id;
            $data['montoaplicadocomprobantes'][] = (float) $pc->montoaplicado;
            $data['monedacomprobante_ids'][] = (int) ($pc->moneda_id ?: $pago->moneda_id);
            $data['cotizacioncomprobantes'][] = (float) ($pc->cotizacion ?: $pago->cotizacion ?: 1);
            $data['cotizacion_aplicada_dia'][] = (float) ($pc->cotizacion_aplicada ?: $pc->cotizacion ?: 0);
            $data['diferencias_cambio'][] = (float) ($pc->diferencia_cambio ?? 0);
        }

        foreach ($pago->caja_movimientos as $mov) {
            foreach ($mov->caja_movimiento_cuentacajas ?? [] as $lin) {
                $cid = (int) ($lin->cuentacaja_id ?? 0);
                $monto = abs((float) ($lin->monto ?? 0));
                if ($cid <= 0 || $monto <= 0) {
                    continue;
                }
                $data['cuentacaja_ids'][] = $cid;
                $data['montos'][] = $monto;
                $data['moneda_ids'][] = (int) ($lin->moneda_id ?: $pago->moneda_id ?: 1);
                $data['cotizaciones'][] = (float) ($lin->cotizacion ?: $pago->cotizacion ?: 1);
                $data['observaciones'][] = (string) ($lin->observacion ?? '');
            }
        }

        foreach ($pago->cheques as $ch) {
            $monto = abs((float) ($ch->monto ?? 0));
            if ($monto <= 0) {
                continue;
            }
            $data['numerocheque_emitidos'][] = (string) ($ch->numerocheque ?? '');
            $data['montocheque_emitidos'][] = $monto;
            $data['chequera_emitido_ids'][] = (int) ($ch->chequera_id ?: 0);
            $data['cuentacaja_emitido_ids'][] = (int) ($ch->cuentacaja_id ?: 0);
            $data['fechapago_emitidos'][] = $ch->fechapago
                ? (string) $ch->fechapago
                : ($pago->fecha?->format('Y-m-d') ?? date('Y-m-d'));
            $data['moneda_emitido_ids'][] = (int) ($ch->moneda_id ?: $pago->moneda_id ?: 1);
            $data['cotizacioncheque_emitidos'][] = (float) ($ch->cotizacion ?: $pago->cotizacion ?: 1);
        }

        return $data;
    }

    /**
     * @return array{mensaje?:string,errores?:string}
     */
    public function marcarPagada(int $id): array
    {
        try {
            DB::transaction(function () use ($id) {
                $pago = $this->pagoproveedorConLock($id);
                PagoproveedorEdicionCandadoSupport::assertEditable($pago);
                if ((string) $pago->estado !== 'CONFIRMADA') {
                    throw new Exception('Solo se puede marcar PAGADA una OP CONFIRMADA.');
                }
                $this->pagoproveedorRepository->update(['estado' => 'PAGADA'], $id);
                $this->registrarEstado($pago, 'PAGADA', 'Marcada como pagada (bridge bancario manual / Interbanking pendiente)');
            });

            return ['mensaje' => 'ok'];
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * @return array{mensaje?:string,errores?:string}
     */
    public function marcarConciliada(int $id): array
    {
        try {
            DB::transaction(function () use ($id) {
                $pago = $this->pagoproveedorConLock($id);
                PagoproveedorEdicionCandadoSupport::assertEditable($pago);
                if (! in_array((string) $pago->estado, ['CONFIRMADA', 'PAGADA'], true)) {
                    throw new Exception('Solo se puede marcar CONCILIADA una OP CONFIRMADA o PAGADA.');
                }
                // Enganche futuro: ConciliacionBancaria ↔ caja_movimiento_id de la OP.
                $this->pagoproveedorRepository->update(['estado' => 'CONCILIADA'], $id);
                $this->registrarEstado($pago, 'CONCILIADA', 'Marcada como conciliada (manual; FK caja_movimiento pendiente de Interbanking)');
            });

            return ['mensaje' => 'ok'];
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * OP con la fila tomada. Las transiciones de estado leían y escribían sin lock: dos
     * pedidos simultáneos pasaban los dos controles y aplicaban ambos.
     */
    private function pagoproveedorConLock(int $id): Pagoproveedor
    {
        $pago = Pagoproveedor::query()->whereKey($id)->lockForUpdate()->first();
        if ($pago === null) {
            throw new Exception('No existe la OP #'.$id.'.');
        }

        return $pago;
    }

    /**
     * Vincula OP a transferencia Interbanking y marca PAGADA + CONCILIADA.
     *
     * @return array{mensaje?:string,errores?:string}
     */
    public function vincularTransferenciaInterbanking(int $pagoproveedorId, int $interbankingTransferenciaId): array
    {
        try {
            DB::transaction(function () use ($pagoproveedorId, $interbankingTransferenciaId) {
                $pago = $this->pagoproveedorConLock($pagoproveedorId);
                $estado = (string) $pago->estado;
                if (! in_array($estado, ['CONFIRMADA', 'PAGADA'], true)) {
                    throw new Exception('La OP debe estar CONFIRMADA o PAGADA para conciliar con Interbanking.');
                }

                // Control dentro de la TX; el índice único de la columna es la garantía final.
                $ya = Pagoproveedor::query()
                    ->where('interbanking_transferencia_id', $interbankingTransferenciaId)
                    ->where('id', '!=', $pagoproveedorId)
                    ->exists();
                if ($ya) {
                    throw new Exception('La transferencia IB #'.$interbankingTransferenciaId.' ya está vinculada a otra OP.');
                }

                if ($estado === 'CONFIRMADA') {
                    $this->registrarEstado($pago, 'PAGADA', 'Bridge IB: transferencia #'.$interbankingTransferenciaId);
                }

                $this->pagoproveedorRepository->update([
                    'interbanking_transferencia_id' => $interbankingTransferenciaId,
                    'estado' => 'CONCILIADA',
                ], $pagoproveedorId);

                $this->registrarEstado(
                    $pago,
                    'CONCILIADA',
                    'Bridge IB: conciliada con transferencia #'.$interbankingTransferenciaId
                );
            });

            return ['mensaje' => 'ok'];
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * Vincula OP a movimiento de extracto Interbanking (clearing statement).
     *
     * @return array{mensaje?:string,errores?:string}
     */
    public function vincularMovimientoInterbanking(int $pagoproveedorId, int $interbankingMovimientoId): array
    {
        try {
            if (! Schema::hasColumn('pagoproveedor', 'interbanking_movimiento_id')) {
                throw new Exception('Columna interbanking_movimiento_id no disponible.');
            }

            DB::transaction(function () use ($pagoproveedorId, $interbankingMovimientoId) {
                $pago = $this->pagoproveedorConLock($pagoproveedorId);
                $estado = (string) $pago->estado;
                if (! in_array($estado, ['CONFIRMADA', 'PAGADA'], true)) {
                    throw new Exception('La OP debe estar CONFIRMADA o PAGADA para conciliar con extracto.');
                }

                // Control dentro de la TX; el índice único de la columna es la garantía final.
                $ya = Pagoproveedor::query()
                    ->where('interbanking_movimiento_id', $interbankingMovimientoId)
                    ->where('id', '!=', $pagoproveedorId)
                    ->exists();
                if ($ya) {
                    throw new Exception('El movimiento IB #'.$interbankingMovimientoId.' ya está vinculado a otra OP.');
                }

                if ($estado === 'CONFIRMADA') {
                    $this->registrarEstado($pago, 'PAGADA', 'Clearing IB: movimiento #'.$interbankingMovimientoId);
                }

                $this->pagoproveedorRepository->update([
                    'interbanking_movimiento_id' => $interbankingMovimientoId,
                    'estado' => 'CONCILIADA',
                ], $pagoproveedorId);

                $this->registrarEstado(
                    $pago,
                    'CONCILIADA',
                    'Clearing IB: conciliada con movimiento extracto #'.$interbankingMovimientoId
                );
            });

            return ['mensaje' => 'ok'];
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * Alta de OP desde propuesta de pagos (por proveedor + forma de pago + aplicaciones).
     *
     * Graba con la misma integridad que una OP común: aplicaciones, retenciones, medio de
     * pago y asiento contable balanceado, todo dentro de la misma transacción. Si el asiento
     * no cierra, se cae toda la OP en lugar de quedar una OP confirmada sin contabilizar.
     *
     * @param  list<array{proveedor_cuentacorriente_id:int,montoaplicado:float,moneda_id?:int,cotizacion?:float}>  $aplicaciones
     * @param  bool  $sincronizarAnita  false si el caller tiene TX externa (p.ej. ejecutar propuesta)
     * @return array{mensaje?:string,errores?:string,pagoproveedor_id?:int,aviso?:string}
     */
    public function crearDesdePropuesta(
        int $empresaId,
        int $proveedorId,
        float $monto,
        int $monedaId,
        string $fecha,
        int $propuestaPagoId,
        array $aplicaciones,
        ?string $detalle = null,
        ?bool $confirmada = null,
        ?int $cajaId = null,
        ?int $cuentacajaId = null,
        bool $calcularRetenciones = true,
        ?string $observacionCuentacaja = null,
        ?int $chequeraId = null,
        ?string $nombreProveedorCheque = null,
        ?float $cotizacion = null,
        bool $sincronizarAnita = true,
    ): array {
        $estado = ($confirmada ?? (bool) config('propuesta_pago.ejecutar_confirmada', true))
            ? 'CONFIRMADA'
            : 'PRE CARGA';

        // Sin instrumento (cuenta ni chequera): preferir PRE CARGA
        if ($estado === 'CONFIRMADA' && (! $cuentacajaId || $cuentacajaId <= 0) && (! $chequeraId || $chequeraId <= 0)) {
            $estado = 'PRE CARGA';
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_CAJA
        );

        $esOpa = ! $this->hayAplicacionConMonto($aplicaciones) && abs((float) $monto) > 0;
        $tipoComprobante = PagoproveedorAnitaNumeracionSupport::normalizarTipoComprobante(
            'OPP',
            $esOpa
        );
        $dataPreflight = [
            'proveedor_id' => $proveedorId,
            'fecha' => $fecha,
            'moneda_id' => $monedaId,
            'cotizacion' => $cotizacion ?? 1,
            'monto' => $monto,
            'idcuentacorrientes' => [],
            'montoaplicadocomprobantes' => [],
            'monedacomprobante_ids' => [],
            'cotizacioncomprobantes' => [],
            'cotizacion_aplicada_dia' => [],
        ];
        foreach ($aplicaciones as $apl) {
            $dataPreflight['idcuentacorrientes'][] = (int) ($apl['proveedor_cuentacorriente_id'] ?? 0);
            $dataPreflight['montoaplicadocomprobantes'][] = (float) ($apl['montoaplicado'] ?? 0);
            $dataPreflight['monedacomprobante_ids'][] = (int) ($apl['moneda_id'] ?? 0) ?: $monedaId;
            $cotApl = (float) ($apl['cotizacion'] ?? 0) ?: 1;
            $dataPreflight['cotizacioncomprobantes'][] = $cotApl;
            $dataPreflight['cotizacion_aplicada_dia'][] = (float) ($apl['cotizacion_aplicada'] ?? 0) ?: $cotApl;
        }
        // Antes de abrir TX / quemar correlativos: Anita no revierte al rollback MySQL.
        $this->assertNumeradoresAnitaAntesDeGrabar(
            $empresaId,
            $dataPreflight,
            $estado,
            $tipoComprobante
        );

        try {
            $pago = DB::transaction(function () use (
                $empresaId,
                $proveedorId,
                $monto,
                $monedaId,
                $fecha,
                $propuestaPagoId,
                $aplicaciones,
                $detalle,
                $estado,
                $cajaId,
                $cuentacajaId,
                $calcularRetenciones,
                $observacionCuentacaja,
                $chequeraId,
                $nombreProveedorCheque,
                $cotizacion,
                $tipoComprobante
            ) {
                $numero = PagoproveedorAnitaNumeracionSupport::siguienteNumeroConLock($empresaId, $tipoComprobante);
                $sucursal = PagoproveedorAnitaNumeracionSupport::sucursalParaOp($empresaId);

                // Misma TC que una OP común: fijar 1 dejaba las OP en ME al cambio 1.
                $cotizacionPago = PagoproveedorAsientoArmadoSupport::cotizacionUnicaDelPago(
                    $monedaId,
                    NumeroDecimalLocalSupport::aFloat($cotizacion ?? 1, 1.0),
                    $fecha
                );

                $chequera = null;
                if ($chequeraId && $chequeraId > 0) {
                    $chequera = \App\Models\Caja\Chequera::query()->with('cuentacajas')->find($chequeraId);
                    if ($chequera && (! $cuentacajaId || $cuentacajaId <= 0)) {
                        $cuentacajaId = (int) ($chequera->cuentacaja_id ?: 0) ?: null;
                    }
                }

                $pago = $this->pagoproveedorRepository->create([
                    'empresa_id' => $empresaId,
                    'tipotransaccion_caja_id' => $this->tipotransaccionCajaIdParaComprobante($tipoComprobante, null),
                    'tipocomprobante' => $tipoComprobante,
                    'letra' => (string) config('pagoproveedor.letra_default', ' '),
                    'sucursal' => $sucursal,
                    'numerotransaccion' => (string) $numero,
                    'fecha' => $fecha,
                    'caja_id' => ($cajaId && $cajaId > 0) ? $cajaId : null,
                    'proveedor_id' => $proveedorId,
                    'detalle' => $detalle ?: ('OP desde propuesta #'.$propuestaPagoId.' Nro. '.$numero),
                    'estado' => $estado,
                    'monto' => $monto,
                    'cotizacion' => $cotizacionPago,
                    'moneda_id' => $monedaId,
                    'modo_cotizacion' => (string) config('pagoproveedor.modo_cotizacion_default', 'factura'),
                    'usuario_id' => Auth::id(),
                    'propuesta_pago_id' => $propuestaPagoId,
                ]);

                $this->registrarEstado($pago, $estado, 'Alta desde propuesta de pagos #'.$propuestaPagoId);
                PagoproveedorAplicacionCuentacorrienteSupport::reemplazarAplicaciones($pago, $aplicaciones);
                if (! $this->hayAplicacionConMonto($aplicaciones) && abs((float) $monto) > 0) {
                    PagoproveedorAplicacionCuentacorrienteSupport::crearAnticipo(
                        $pago,
                        abs((float) $monto),
                        (int) $monedaId,
                        $cotizacionPago,
                    );
                    $this->marcarComoOpa($pago);
                }

                // Aplicaciones en el formato del formulario: lo consumen retenciones y asiento.
                $data = [
                    'empresa_id' => $empresaId,
                    'caja_id' => $cajaId,
                    'fecha' => $fecha,
                    'monto' => $monto,
                    'moneda_id' => $monedaId,
                    'cotizacion' => $cotizacionPago,
                    'idcuentacorrientes' => [],
                    'montoaplicadocomprobantes' => [],
                    'monedacomprobante_ids' => [],
                    'cotizacioncomprobantes' => [],
                    'cotizacion_aplicada_dia' => [],
                    'diferencias_cambio' => [],
                ];
                foreach ($aplicaciones as $apl) {
                    $cotApl = (float) ($apl['cotizacion'] ?? 0) ?: $cotizacionPago;
                    $data['idcuentacorrientes'][] = (int) ($apl['proveedor_cuentacorriente_id'] ?? 0);
                    $data['montoaplicadocomprobantes'][] = (float) ($apl['montoaplicado'] ?? 0);
                    $data['monedacomprobante_ids'][] = (int) ($apl['moneda_id'] ?? 0) ?: $monedaId;
                    $data['cotizacioncomprobantes'][] = $cotApl;
                    $data['cotizacion_aplicada_dia'][] = (float) ($apl['cotizacion_aplicada'] ?? 0) ?: $cotApl;
                    $data['diferencias_cambio'][] = 0;
                }

                if ($calcularRetenciones && (bool) config('propuesta_pago.calcular_retenciones_al_ejecutar', true)) {
                    $this->persistirRetenciones($pago, array_merge($data, [
                        'importe_neto_retencion' => $monto,
                        'importe_iva_retencion' => 0,
                        'calcular_ganancias' => true,
                        'calcular_iva' => true,
                        'calcular_suss' => true,
                        'calcular_iibb' => true,
                    ]));
                }

                // Neto real al proveedor. La columna de retenciones es `importe`: pedir `monto`
                // devolvía null y se giraba el bruto (la retención se le pagaba al proveedor).
                $pago = $pago->fresh();
                $neto = $pago->netoAPagar(4);

                // Un solo medio por OP: con cheque no va línea de cuentacaja, si no el asiento
                // acredita dos veces el mismo egreso y no cierra.
                $pagaConCheque = $chequera !== null && $neto > 0;

                if ($pagaConCheque) {
                    $data = array_merge($data, [
                        'numerocheque_emitidos' => [self::siguienteNumeroCheque((int) $chequera->id, $chequera)],
                        'montocheque_emitidos' => [$neto],
                        'chequera_emitido_ids' => [(int) $chequera->id],
                        'cuentacaja_emitido_ids' => [$cuentacajaId],
                        'fechapago_emitidos' => [$fecha],
                        'moneda_emitido_ids' => [$monedaId],
                        'cotizacioncheque_emitidos' => [
                            PagoproveedorAsientoArmadoSupport::cotizacionParaLinea($monedaId, $cotizacionPago),
                        ],
                        'caracter_emitidos' => [ChequePropioInstrumentoSupport::caracterDefault()],
                        'para_dep_emitidos' => [ChequePropioInstrumentoSupport::paraDepDefault()],
                        'negociable_emitidos' => [ChequePropioInstrumentoSupport::negociableDesdeChequera(
                            (string) ($chequera->tipochequera
                                ?: ChequePropioInstrumentoSupport::negociableDefault())
                        )],
                        'anombrede_emitidos' => [$nombreProveedorCheque ?: ('Proveedor #'.$proveedorId)],
                        'proveedor_emitido_ids' => [$proveedorId],
                        'pagoproveedor_id' => $pago->id,
                    ]);
                } elseif ($cuentacajaId && $cuentacajaId > 0 && $neto > 0) {
                    $data = array_merge($data, [
                        'cuentacaja_ids' => [$cuentacajaId],
                        'montos' => [$neto],
                        'moneda_ids' => [$monedaId],
                        'cotizaciones' => [
                            PagoproveedorAsientoArmadoSupport::cotizacionParaLinea($monedaId, $cotizacionPago),
                        ],
                        'observaciones' => [$observacionCuentacaja ?: ('Egreso OP propuesta #'.$propuestaPagoId)],
                    ]);
                }

                $cajaMovId = $this->persistirCajaMovimiento($pago, $data, true);

                if ($pagaConCheque && $cajaMovId > 0) {
                    $this->chequeRepository->guardarChequeIngresoEgreso($data, 'create', $cajaMovId);
                    Cheque::query()
                        ->where('caja_movimiento_id', $cajaMovId)
                        ->whereNull('pagoproveedor_id')
                        ->update(['pagoproveedor_id' => $pago->id]);
                }

                // Asiento por la misma vía que una OP común (se rearma desde deuda/medios/retenciones).
                if ((string) $pago->estado !== 'PRE CARGA') {
                    $pago = $pago->fresh();
                    $data = array_merge($data, $this->construirArraysAsientoDesdeOperacion($pago, $data));
                    if (empty($data['cuentacontable_ids'])) {
                        throw new Exception(
                            'No se pudo armar el asiento contable de la OP de la propuesta #'
                            .$propuestaPagoId.' (sin líneas).'
                        );
                    }
                    AsientoBalanceSupport::assertBalanceadoDesdePayload([
                        'debes' => $data['debeasientos'] ?? [],
                        'haberes' => $data['haberasientos'] ?? [],
                    ], 'asiento de la OP de la propuesta #'.$propuestaPagoId);
                    $this->persistirAsiento($pago, $data);
                }

                return $pago->fresh();
            });

            $out = [
                'mensaje' => 'ok',
                'pagoproveedor_id' => (int) $pago->id,
            ];

            // Si se llama desde PropuestaPagoService::ejecutar (TX externa), Anita va
            // DESPUÉS del commit de esa TX. Acá solo sincroniza cuando no hay TX padre.
            if (! $sincronizarAnita) {
                return $out;
            }

            try {
                $this->sincronizarAnitaTesoreria($pago->fresh(), false);
            } catch (\Throwable $eAnita) {
                // La OP ya está commitida. Devolver error haría que la propuesta no marque
                // sus líneas como ejecutadas y las mismas facturas se vuelvan a pagar.
                Log::error('pagoproveedor.propuesta.anita.sync_post_commit.fallo', [
                    'pagoproveedor_id' => $pago->id,
                    'numero' => $pago->numerotransaccion,
                    'propuesta_pago_id' => $propuestaPagoId,
                    'mensaje' => $eAnita->getMessage(),
                ]);
                $out['aviso'] = 'OP grabada en ERP (#'.$pago->numerotransaccion
                    .') pero falló la réplica Anita: '.$eAnita->getMessage()
                    .'. No reejecute la propuesta; revise sincronización/auditoría.';
            }

            return $out;
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * `numerocheque` es varchar: MAX() compara como texto ('9' gana a '10'), así que hay
     * que castear. El lock de la chequera serializa dos propuestas que se ejecutan a la vez
     * sobre la misma chequera y evita que saquen el mismo número.
     */
    private static function siguienteNumeroCheque(int $chequeraId, \App\Models\Caja\Chequera $chequera): string
    {
        \App\Models\Caja\Chequera::query()->whereKey($chequeraId)->lockForUpdate()->first();

        $desde = (int) ($chequera->desdenumerocheque ?: 1);
        $hasta = (int) ($chequera->hastanumerocheque ?: 99999999);
        $ultimo = (int) (\App\Models\Caja\Cheque::query()
            ->where('chequera_id', $chequeraId)
            ->selectRaw('MAX(CAST(numerocheque AS UNSIGNED)) as ultimo')
            ->value('ultimo') ?: ($desde - 1));
        $sig = max($desde, $ultimo + 1);
        if ($sig > $hasta) {
            throw new Exception('Chequera #'.$chequeraId.' sin números disponibles (rango '.$desde.'-'.$hasta.').');
        }

        return (string) $sig;
    }
}
