<?php

namespace App\Services\Compras;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Caja_Movimiento_Estado;
use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Proveedor;
use App\Repositories\Caja\Caja_Movimiento_CuentacajaRepositoryInterface;
use App\Repositories\Caja\Caja_Movimiento_EstadoRepositoryInterface;
use App\Repositories\Caja\Caja_MovimientoRepositoryInterface;
use App\Repositories\Caja\ChequeRepositoryInterface;
use App\Repositories\Compras\PagoproveedorRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaNumeracionSupport;
use App\Support\Compras\PagoproveedorAplicacionCuentacorrienteSupport;
use App\Support\Compras\PagoproveedorAsientoArmadoSupport;
use App\Support\Compras\ProveedorCbuPagoSupport;
use App\Support\Compras\Retencion\PagoproveedorRetencionPersistenciaSupport;
use App\Support\Compras\Retencion\RetencionesPagoInput;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;

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
        private CuentacajaRepositoryInterface $cuentacajaRepository,
        private CuentacontableRepositoryInterface $cuentacontableRepository,
        private ProveedorCuentacorrienteAplicacionAnitaSyncService $cuentacorrienteAnitaSyncService,
    ) {
    }

    /**
     * Preview AJAX del asiento TES (no graba).
     *
     * @param  array<string, mixed>  $data
     * @return array{mensaje: string, asiento: list<array<string, mixed>>}
     */
    public function generaAsientoContable(array $data): array
    {
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
        );

        return ['mensaje' => 'ok', 'asiento' => $asiento];
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
            $pago = DB::transaction(function () use ($data, $request, $empresaId) {
                $numero = PagoproveedorAnitaNumeracionSupport::siguienteNumeroConLock($empresaId);
                $sucursal = PagoproveedorAnitaNumeracionSupport::sucursalParaOp($empresaId);

                $estado = (string) ($data['estado'] ?? 'CONFIRMADA');
                if (! in_array($estado, ['PRE CARGA', 'CONFIRMADA'], true)) {
                    $estado = 'CONFIRMADA';
                }

                $cbuElegido = ProveedorCbuPagoSupport::resolverDesdeRequest(
                    (int) $data['proveedor_id'],
                    $data['proveedor_formapago_id'] ?? null,
                    $data['cbu_pago'] ?? null
                );

                $pago = $this->pagoproveedorRepository->create([
                    'empresa_id' => $empresaId,
                    'tipotransaccion_caja_id' => ($data['tipotransaccion_caja_id'] ?? null)
                        ?: (IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorConfig() ?: null),
                    'tipocomprobante' => (string) ($data['tipocomprobante'] ?? config('pagoproveedor.tipocomprobante_default', 'OPP')),
                    'letra' => (string) ($data['letra'] ?? config('pagoproveedor.letra_default', 'A')),
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
                    'cotizacion' => (float) ($data['cotizacion'] ?? 1),
                    'moneda_id' => (int) ($data['moneda_id'] ?? 1),
                    'modo_cotizacion' => (string) ($data['modo_cotizacion'] ?? config('pagoproveedor.modo_cotizacion_default', 'factura')),
                    'usuario_id' => Auth::id(),
                ]);

                $this->registrarEstado($pago, $estado, 'Alta de orden de pago');
                $this->persistirDetalle($pago, $data, $request, true);

                return $pago;
            });

            $this->sincronizarAnitaTesoreria($pago->fresh(), false);

            return [
                'mensaje' => 'ok',
                'pagoproveedor_id' => $pago->id,
            ];
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
            DB::transaction(function () use ($data, $request, $id) {
                $pago = $this->pagoproveedorRepository->findOrFail($id);
                if (in_array($pago->estado, Pagoproveedor::estadosFinalesBloqueados(), true)) {
                    throw new Exception('No se puede modificar una OP en estado '.$pago->estado.'.');
                }

                $cbuElegido = ProveedorCbuPagoSupport::resolverDesdeRequest(
                    (int) $data['proveedor_id'],
                    $data['proveedor_formapago_id'] ?? null,
                    $data['cbu_pago'] ?? null
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
                    'cotizacion' => (float) ($data['cotizacion'] ?? $pago->cotizacion),
                    'moneda_id' => (int) ($data['moneda_id'] ?? $pago->moneda_id),
                    'modo_cotizacion' => (string) ($data['modo_cotizacion'] ?? $pago->modo_cotizacion),
                    'tipotransaccion_caja_id' => ($data['tipotransaccion_caja_id'] ?? null)
                        ?: ($pago->tipotransaccion_caja_id ?: IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorConfig() ?: null),
                ], $id);

                $pago = $this->pagoproveedorRepository->findOrFail($id);
                $this->registrarEstado($pago, (string) $pago->estado, 'Actualización de orden de pago');
                $this->persistirDetalle($pago, $data, $request, false);
            });

            $this->sincronizarAnitaTesoreria($this->pagoproveedorRepository->findOrFail($id), true);

            return ['mensaje' => 'ok'];
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
        if ($anticipo > 0) {
            PagoproveedorAplicacionCuentacorrienteSupport::crearAnticipo(
                $pago,
                $anticipo,
                (int) ($data['moneda_id'] ?? 1),
                (float) ($data['cotizacion'] ?? 1),
            );
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

        if (! empty($data['cuentacontable_ids']) && $pago->estado !== 'PRE CARGA') {
            $this->persistirAsiento($pago, $data);
        }
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

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistirRetenciones(Pagoproveedor $pago, array $data): void
    {
        $proveedor = Proveedor::query()->find((int) $pago->proveedor_id);
        if ($proveedor === null) {
            return;
        }

        $neto = (float) ($data['importe_neto_retencion'] ?? $data['monto'] ?? $pago->monto);
        $iva = (float) ($data['importe_iva_retencion'] ?? 0);

        $resultado = $this->retencionesPagoCalculator->calcular(new RetencionesPagoInput(
            proveedor: $proveedor,
            importeNetoPago: $neto,
            importeIvaPago: $iva,
            fecha: $pago->fecha?->format('Y-m-d'),
            retenciongananciaIdPago: isset($data['retencionganancia_id']) ? (int) $data['retencionganancia_id'] : null,
            retencionivaIdPago: isset($data['retencioniva_id']) ? (int) $data['retencioniva_id'] : null,
            retencionsussIdPago: isset($data['retencionsuss_id']) ? (int) $data['retencionsuss_id'] : null,
            iibbProvinciaId: isset($data['iibb_provincia_id']) ? (int) $data['iibb_provincia_id'] : null,
            iibbTasaOverride: isset($data['iibb_tasa']) ? (float) $data['iibb_tasa'] : null,
            calcularGanancias: ! isset($data['calcular_ganancias']) || (bool) $data['calcular_ganancias'],
            calcularIva: ! isset($data['calcular_iva']) || (bool) $data['calcular_iva'],
            calcularSuss: ! isset($data['calcular_suss']) || (bool) $data['calcular_suss'],
            calcularIibb: ! isset($data['calcular_iibb']) || (bool) $data['calcular_iibb'],
            empresaId: (int) $pago->empresa_id ?: null,
        ));

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
        $tipoOppId = (int) ($pago->tipotransaccion_caja_id ?: IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorConfig());
        if ($tipoOppId <= 0) {
            throw new Exception('No hay tipo de transacción OPP configurado para el movimiento de caja de la OP.');
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

            return;
        }

        $movimiento = Caja_Movimiento::query()->find($cajaId);
        if ($movimiento === null) {
            return;
        }

        if ($reemplazar) {
            IngresoEgresoAnitaTesmovSupport::eliminarDesdeMovimiento($movimiento);
        }

        IngresoEgresoAnitaTesmovSupport::grabarDesdeMovimiento($movimiento->fresh());
        $this->cuentacorrienteAnitaSyncService->syncPorPagoproveedor((int) $pago->id);
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

        $asiento = $this->asientoRepository->create($payload);
        if ($asiento === 'Error' || ! $asiento) {
            throw new Exception('Error al grabar asiento de la OP.');
        }
        $this->asientoMovimientoRepository->create($payload, $asiento->id);
        $this->pagoproveedorRepository->update(['asiento_id' => $asiento->id], $pago->id);
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
     * PRE CARGA → CONFIRMADA; re-persiste asiento/certificados si faltan.
     *
     * @return array{mensaje?:string,errores?:string}
     */
    public function confirmar(int $id): array
    {
        try {
            DB::transaction(function () use ($id) {
                $pago = $this->pagoproveedorRepository->findOrFail($id);
                if ((string) $pago->estado !== 'PRE CARGA') {
                    throw new Exception('Solo se puede confirmar una OP en PRE CARGA.');
                }

                PeriodoContableCierreSupport::assertOperacionPermitida(
                    (int) $pago->empresa_id,
                    $pago->fecha?->format('Y-m-d') ?? date('Y-m-d'),
                    PeriodoContableCierreSupport::ALCANCE_CAJA
                );

                $this->pagoproveedorRepository->update(['estado' => 'CONFIRMADA'], $id);
                $pago = $this->pagoproveedorRepository->findOrFail($id);
                $this->registrarEstado($pago, 'CONFIRMADA', 'Confirmación de orden de pago');

                // Re-persistir retenciones (certificados) y asiento si hay datos de cuentas.
                $data = [
                    'monto' => $pago->monto,
                    'importe_neto_retencion' => $pago->monto,
                    'calcular_ganancias' => true,
                    'calcular_iva' => true,
                    'calcular_suss' => true,
                    'calcular_iibb' => true,
                ];
                $this->persistirRetenciones($pago, $data);

                if (! $pago->asiento_id && ! $pago->asientos) {
                    // Sin líneas contables en request: no fuerza asiento vacío.
                }
            });

            return ['mensaje' => 'ok'];
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * @return array{mensaje?:string,errores?:string}
     */
    public function marcarPagada(int $id): array
    {
        try {
            $pago = $this->pagoproveedorRepository->findOrFail($id);
            if ((string) $pago->estado !== 'CONFIRMADA') {
                throw new Exception('Solo se puede marcar PAGADA una OP CONFIRMADA.');
            }
            $this->pagoproveedorRepository->update(['estado' => 'PAGADA'], $id);
            $this->registrarEstado($pago, 'PAGADA', 'Marcada como pagada (bridge bancario manual / Interbanking pendiente)');

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
            $pago = $this->pagoproveedorRepository->findOrFail($id);
            if (! in_array((string) $pago->estado, ['CONFIRMADA', 'PAGADA'], true)) {
                throw new Exception('Solo se puede marcar CONCILIADA una OP CONFIRMADA o PAGADA.');
            }
            // Enganche futuro: ConciliacionBancaria ↔ caja_movimiento_id de la OP.
            $this->pagoproveedorRepository->update(['estado' => 'CONCILIADA'], $id);
            $this->registrarEstado($pago, 'CONCILIADA', 'Marcada como conciliada (manual; FK caja_movimiento pendiente de Interbanking)');

            return ['mensaje' => 'ok'];
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * Vincula OP a transferencia Interbanking y marca PAGADA + CONCILIADA.
     *
     * @return array{mensaje?:string,errores?:string}
     */
    public function vincularTransferenciaInterbanking(int $pagoproveedorId, int $interbankingTransferenciaId): array
    {
        try {
            $pago = $this->pagoproveedorRepository->findOrFail($pagoproveedorId);
            $estado = (string) $pago->estado;
            if (! in_array($estado, ['CONFIRMADA', 'PAGADA'], true)) {
                throw new Exception('La OP debe estar CONFIRMADA o PAGADA para conciliar con Interbanking.');
            }

            $ya = Pagoproveedor::query()
                ->where('interbanking_transferencia_id', $interbankingTransferenciaId)
                ->where('id', '!=', $pagoproveedorId)
                ->exists();
            if ($ya) {
                throw new Exception('La transferencia IB #'.$interbankingTransferenciaId.' ya está vinculada a otra OP.');
            }

            $upd = ['interbanking_transferencia_id' => $interbankingTransferenciaId];
            if ($estado === 'CONFIRMADA') {
                $upd['estado'] = 'PAGADA';
                $this->pagoproveedorRepository->update($upd, $pagoproveedorId);
                $this->registrarEstado($pago, 'PAGADA', 'Bridge IB: transferencia #'.$interbankingTransferenciaId);
                $pago = $this->pagoproveedorRepository->findOrFail($pagoproveedorId);
            } else {
                $this->pagoproveedorRepository->update($upd, $pagoproveedorId);
            }

            $this->pagoproveedorRepository->update(['estado' => 'CONCILIADA'], $pagoproveedorId);
            $this->registrarEstado(
                $pago,
                'CONCILIADA',
                'Bridge IB: conciliada con transferencia #'.$interbankingTransferenciaId
            );

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
            $pago = $this->pagoproveedorRepository->findOrFail($pagoproveedorId);
            $estado = (string) $pago->estado;
            if (! in_array($estado, ['CONFIRMADA', 'PAGADA'], true)) {
                throw new Exception('La OP debe estar CONFIRMADA o PAGADA para conciliar con extracto.');
            }
            if (! \Illuminate\Support\Facades\Schema::hasColumn('pagoproveedor', 'interbanking_movimiento_id')) {
                throw new Exception('Columna interbanking_movimiento_id no disponible.');
            }

            $ya = Pagoproveedor::query()
                ->where('interbanking_movimiento_id', $interbankingMovimientoId)
                ->where('id', '!=', $pagoproveedorId)
                ->exists();
            if ($ya) {
                throw new Exception('El movimiento IB #'.$interbankingMovimientoId.' ya está vinculado a otra OP.');
            }

            $upd = ['interbanking_movimiento_id' => $interbankingMovimientoId];
            if ($estado === 'CONFIRMADA') {
                $upd['estado'] = 'PAGADA';
                $this->pagoproveedorRepository->update($upd, $pagoproveedorId);
                $this->registrarEstado($pago, 'PAGADA', 'Clearing IB: movimiento #'.$interbankingMovimientoId);
                $pago = $this->pagoproveedorRepository->findOrFail($pagoproveedorId);
            } else {
                $this->pagoproveedorRepository->update($upd, $pagoproveedorId);
            }

            $this->pagoproveedorRepository->update(['estado' => 'CONCILIADA'], $pagoproveedorId);
            $this->registrarEstado(
                $pago,
                'CONCILIADA',
                'Clearing IB: conciliada con movimiento extracto #'.$interbankingMovimientoId
            );

            return ['mensaje' => 'ok'];
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    /**
     * Alta de OP desde propuesta de pagos (por proveedor + forma de pago + aplicaciones).
     *
     * @param  list<array{proveedor_cuentacorriente_id:int,montoaplicado:float,moneda_id?:int,cotizacion?:float}>  $aplicaciones
     * @return array{mensaje?:string,errores?:string,pagoproveedor_id?:int}
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
                $nombreProveedorCheque
            ) {
                $numero = PagoproveedorAnitaNumeracionSupport::siguienteNumeroConLock($empresaId);
                $sucursal = PagoproveedorAnitaNumeracionSupport::sucursalParaOp($empresaId);

                $chequera = null;
                if ($chequeraId && $chequeraId > 0) {
                    $chequera = \App\Models\Caja\Chequera::query()->with('cuentacajas')->find($chequeraId);
                    if ($chequera && (! $cuentacajaId || $cuentacajaId <= 0)) {
                        $cuentacajaId = (int) ($chequera->cuentacaja_id ?: 0) ?: null;
                    }
                }

                $pago = $this->pagoproveedorRepository->create([
                    'empresa_id' => $empresaId,
                    'tipocomprobante' => (string) config('pagoproveedor.tipocomprobante_default', 'OPP'),
                    'letra' => (string) config('pagoproveedor.letra_default', 'A'),
                    'sucursal' => $sucursal,
                    'numerotransaccion' => (string) $numero,
                    'fecha' => $fecha,
                    'caja_id' => ($cajaId && $cajaId > 0) ? $cajaId : null,
                    'proveedor_id' => $proveedorId,
                    'detalle' => $detalle ?: ('OP desde propuesta #'.$propuestaPagoId.' Nro. '.$numero),
                    'estado' => $estado,
                    'monto' => $monto,
                    'cotizacion' => 1,
                    'moneda_id' => $monedaId,
                    'modo_cotizacion' => (string) config('pagoproveedor.modo_cotizacion_default', 'factura'),
                    'usuario_id' => Auth::id(),
                    'propuesta_pago_id' => $propuestaPagoId,
                ]);

                $this->registrarEstado($pago, $estado, 'Alta desde propuesta de pagos #'.$propuestaPagoId);
                PagoproveedorAplicacionCuentacorrienteSupport::reemplazarAplicaciones($pago, $aplicaciones);

                if ($calcularRetenciones && (bool) config('propuesta_pago.calcular_retenciones_al_ejecutar', true)) {
                    $this->persistirRetenciones($pago, [
                        'monto' => $monto,
                        'importe_neto_retencion' => $monto,
                        'importe_iva_retencion' => 0,
                        'calcular_ganancias' => true,
                        'calcular_iva' => true,
                        'calcular_suss' => true,
                        'calcular_iibb' => true,
                    ]);
                }

                $pago->load('pagoproveedor_retenciones');
                $totalRet = (float) $pago->pagoproveedor_retenciones->sum('monto');
                $neto = round(max(0, $monto - $totalRet), 4);

                if ($cuentacajaId && $cuentacajaId > 0) {
                    $obs = $observacionCuentacaja ?: ('Egreso OP propuesta #'.$propuestaPagoId);
                    $cajaMovId = $this->persistirCajaMovimiento($pago, [
                        'cuentacaja_ids' => [$cuentacajaId],
                        'montos' => [$neto],
                        'moneda_ids' => [$monedaId],
                        'cotizaciones' => [1],
                        'observaciones' => [$obs],
                    ], true);

                    if ($chequera && $cajaMovId > 0 && $neto > 0) {
                        $nroCheque = self::siguienteNumeroCheque((int) $chequera->id, $chequera);
                        $this->chequeRepository->guardarChequeIngresoEgreso([
                            'numerocheque_emitidos' => [$nroCheque],
                            'montocheque_emitidos' => [$neto],
                            'chequera_emitido_ids' => [(int) $chequera->id],
                            'cuentacaja_emitido_ids' => [$cuentacajaId],
                            'fechapago_emitidos' => [$fecha],
                            'moneda_emitido_ids' => [$monedaId],
                            'cotizacioncheque_emitidos' => [1],
                            'caracter_emitidos' => ['O'],
                            'anombrede_emitidos' => [$nombreProveedorCheque ?: ('Proveedor #'.$proveedorId)],
                            'proveedor_emitido_ids' => [$proveedorId],
                            'empresa_id' => $empresaId,
                            'caja_id' => $cajaId,
                            'pagoproveedor_id' => $pago->id,
                        ], 'create', $cajaMovId);
                    }
                }

                return $pago->fresh();
            });

            $this->sincronizarAnitaTesoreria($pago->fresh(), false);

            return [
                'mensaje' => 'ok',
                'pagoproveedor_id' => (int) $pago->id,
            ];
        } catch (\Throwable $e) {
            return ['errores' => $e->getMessage()];
        }
    }

    private static function siguienteNumeroCheque(int $chequeraId, \App\Models\Caja\Chequera $chequera): string
    {
        $desde = (int) ($chequera->desdenumerocheque ?: 1);
        $hasta = (int) ($chequera->hastanumerocheque ?: 99999999);
        $ultimo = (int) (\App\Models\Caja\Cheque::query()
            ->where('chequera_id', $chequeraId)
            ->max('numerocheque') ?: ($desde - 1));
        $sig = max($desde, $ultimo + 1);
        if ($sig > $hasta) {
            throw new Exception('Chequera #'.$chequeraId.' sin números disponibles (rango '.$desde.'-'.$hasta.').');
        }

        return (string) $sig;
    }
}
