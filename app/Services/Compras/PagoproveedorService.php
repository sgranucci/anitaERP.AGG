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
use App\Support\Compras\Retencion\PagoproveedorRetencionPersistenciaSupport;
use App\Support\Compras\Retencion\RetencionesPagoInput;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        private CuentacajaRepositoryInterface $cuentacajaRepository,
        private CuentacontableRepositoryInterface $cuentacontableRepository,
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

                $pago = $this->pagoproveedorRepository->create([
                    'empresa_id' => $empresaId,
                    'tipotransaccion_caja_id' => ($data['tipotransaccion_caja_id'] ?? null) ?: null,
                    'tipocomprobante' => (string) ($data['tipocomprobante'] ?? config('pagoproveedor.tipocomprobante_default', 'OPP')),
                    'letra' => (string) ($data['letra'] ?? config('pagoproveedor.letra_default', 'A')),
                    'sucursal' => $sucursal,
                    'numerotransaccion' => (string) $numero,
                    'fecha' => $data['fecha'],
                    'caja_id' => ($data['caja_id'] ?? null) ?: null,
                    'proveedor_id' => (int) $data['proveedor_id'],
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

            return [
                'mensaje' => 'ok',
                'pagoproveedor_id' => $pago->id,
            ];
        } catch (\Throwable $e) {
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
                if (in_array($pago->estado, ['REVERTIDA', 'BAJA'], true)) {
                    throw new Exception('No se puede modificar una OP revertida o dada de baja.');
                }

                $this->pagoproveedorRepository->update([
                    'fecha' => $data['fecha'],
                    'caja_id' => ($data['caja_id'] ?? null) ?: null,
                    'proveedor_id' => (int) $data['proveedor_id'],
                    'detalle' => (string) ($data['detalle'] ?? $pago->detalle),
                    'estado' => (string) ($data['estado'] ?? $pago->estado),
                    'monto' => (float) ($data['monto'] ?? $data['totalfinalpago'] ?? $pago->monto),
                    'cotizacion' => (float) ($data['cotizacion'] ?? $pago->cotizacion),
                    'moneda_id' => (int) ($data['moneda_id'] ?? $pago->moneda_id),
                    'modo_cotizacion' => (string) ($data['modo_cotizacion'] ?? $pago->modo_cotizacion),
                    'tipotransaccion_caja_id' => ($data['tipotransaccion_caja_id'] ?? null) ?: null,
                ], $id);

                $pago = $this->pagoproveedorRepository->findOrFail($id);
                $this->registrarEstado($pago, (string) $pago->estado, 'Actualización de orden de pago');
                $this->persistirDetalle($pago, $data, $request, false);
            });

            return ['mensaje' => 'ok'];
        } catch (\Throwable $e) {
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
        if (
            empty($data['cuentacaja_ids'])
            && empty($data['numerocheque_emitidos'])
            && empty($data['numerocheque_recibidos'])
        ) {
            return (int) ($pago->caja_movimiento_id ?? 0);
        }

        $payload = $data;
        $payload['empresa_id'] = $pago->empresa_id;
        $payload['fecha'] = $pago->fecha?->format('Y-m-d');
        $payload['caja_id'] = $pago->caja_id;
        $payload['detalle'] = $pago->detalle;
        $payload['pagoproveedor_id'] = $pago->id;
        $payload['monto'] = $pago->monto;
        $payload['usuario_id'] = Auth::id();

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
            $this->pagoproveedorRepository->update(['caja_movimiento_id' => $cajaMovimientoId], $pago->id);

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
}
