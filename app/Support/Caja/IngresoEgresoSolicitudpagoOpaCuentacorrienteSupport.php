<?php

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Contable\Asiento;
use App\Services\Compras\ProveedorCuentacorrienteAplicacionAnitaSyncService;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaNumeracionSupport;
use App\Support\Compras\PagoproveedorAplicacionCuentacorrienteSupport;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaRetencionEscrituraSupport;
use App\Support\Solicitudpago\SolicitudpagoRetencionCalculoSupport;
use App\Support\Solicitudpago\SolicitudpagoRetencionOpaSupport;
use Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * OPA en ingresos y egresos: deja el comprobante en pagoproveedor y el crédito
 * impago en la cuenta corriente del proveedor.
 *
 * Cubre la solicitud ANTICIPADA y también la OPA cargada directo en esa pantalla
 * (tipo de transacción OPA, con proveedor). Una OPA nacida en Pago a proveedores
 * no se toca: ese módulo ya graba su propio crédito.
 */
final class IngresoEgresoSolicitudpagoOpaCuentacorrienteSupport
{
    public const OBSERVACION_OPA_INGRESO_EGRESO = 'OPA desde ingreso/egreso';

    /**
     * OPA tipeada en ingresos y egresos, sin solicitud y sin ser un movimiento de anulación.
     */
    public static function esOpaDirectaEnIngresosEgresos(Caja_Movimiento $movimiento): bool
    {
        if ((int) ($movimiento->solicitudpago_id ?? 0) > 0) {
            return false;
        }
        if ((int) ($movimiento->caja_movimiento_origen_id ?? 0) > 0) {
            return false;
        }

        return self::esTipoOpa($movimiento);
    }

    public static function persistirDesdeMovimiento(Caja_Movimiento $movimiento): void
    {
        $movimiento->loadMissing(['solicitudpagos', 'caja_movimiento_cuentacajas', 'tipotransaccioncajas']);
        $sp = $movimiento->solicitudpagos;
        if (IngresoEgresoSolicitudpagoSupport::esPagoOpa($sp)) {
            self::persistirOpaDeSolicitud($movimiento);
            return;
        }

        self::persistirOpaDirecta($movimiento);
    }

    private static function persistirOpaDeSolicitud(Caja_Movimiento $movimiento): void
    {
        $sp = $movimiento->solicitudpagos;
        if (! IngresoEgresoSolicitudpagoSupport::esPagoOpa($sp)) {
            return;
        }

        $proveedorId = (int) ($movimiento->proveedor_id ?? $sp->proveedor_id ?? 0);
        if ($proveedorId <= 0) {
            throw new InvalidArgumentException(
                'La solicitud anticipada debe tener proveedor para generar la OPA y el crédito en cuenta corriente.'
            );
        }

        $monedaId = 1;
        $cotizacion = 1.0;
        $monto = 0.0;
        foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
            $monto += abs((float) ($linea->monto ?? 0));
            if ((int) ($linea->moneda_id ?? 0) > 0) {
                $monedaId = (int) $linea->moneda_id;
                $cotizacion = (float) ($linea->cotizacion ?: 1);
            }
        }
        if ($monto < 0.01) {
            $monto = IngresoEgresoSolicitudpagoSupport::montoPendiente($sp);
        }
        $monto = round($monto, 2);
        if ($monto < 0.01) {
            throw new InvalidArgumentException('No hay importe para registrar la OPA de la solicitud anticipada.');
        }

        $totalRetenciones = SolicitudpagoRetencionCalculoSupport::totalImportes($sp);
        $montoAnticipo = $totalRetenciones >= 0.01
            ? round($monto + $totalRetenciones, 2)
            : $monto;

        $asientoId = (int) Asiento::query()
            ->where('caja_movimiento_id', (int) $movimiento->id)
            ->value('id');

        $pago = self::pagoVinculado($movimiento);
        $payload = [
            'empresa_id' => (int) $movimiento->empresa_id,
            'tipotransaccion_caja_id' => (int) $movimiento->tipotransaccion_caja_id,
            'tipocomprobante' => IngresoEgresoSolicitudpagoSupport::abreviaturaTipoPago($sp),
            'letra' => (string) config('pagoproveedor.letra_default', 'A'),
            'sucursal' => PagoproveedorAnitaNumeracionSupport::sucursalParaOp((int) $movimiento->empresa_id),
            'numerotransaccion' => (string) ($movimiento->numerotransaccion ?? ''),
            'fecha' => $movimiento->fecha,
            'proveedor_id' => $proveedorId,
            'detalle' => (string) (
                trim((string) ($movimiento->detalle ?? '')) !== ''
                && ! IngresoEgresoSolicitudpagoSupport::esDescripcionGenericaPagoSp((string) $movimiento->detalle)
                    ? $movimiento->detalle
                    : IngresoEgresoSolicitudpagoSupport::descripcionMovimientoDesdeSp($sp)
            ),
            'estado' => 'CONFIRMADA',
            'monto' => $monto,
            'cotizacion' => $cotizacion > 0 ? $cotizacion : 1,
            'moneda_id' => $monedaId,
            'modo_cotizacion' => 'dia',
            'usuario_id' => Auth::id(),
            'caja_movimiento_id' => (int) $movimiento->id,
            'asiento_id' => $asientoId > 0 ? $asientoId : null,
        ];

        if ($pago === null) {
            $pago = Pagoproveedor::query()->create($payload);
            Pagoproveedor_Estado::query()->create([
                'pagoproveedor_id' => $pago->id,
                'fecha' => now(),
                'estado' => 'CONFIRMADA',
                'usuario_id' => Auth::id(),
                'observacion' => 'OPA desde solicitud de pago '.$sp->codigo,
            ]);
        } else {
            PagoproveedorAplicacionCuentacorrienteSupport::revertirAplicacionesExistentes($pago);
            $pago->fill($payload);
            $pago->save();
        }

        PagoproveedorAplicacionCuentacorrienteSupport::crearAnticipo(
            $pago->fresh(),
            $montoAnticipo,
            $monedaId,
            $cotizacion > 0 ? $cotizacion : 1
        );

        SolicitudpagoRetencionOpaSupport::volcar(
            $pago->fresh(),
            $sp,
            $monedaId,
            $cotizacion > 0 ? $cotizacion : 1
        );

        if ((int) ($movimiento->pagoproveedor_id ?? 0) !== (int) $pago->id) {
            $movimiento->pagoproveedor_id = (int) $pago->id;
            $movimiento->save();
        }

        if ($asientoId > 0) {
            Asiento::query()->whereKey($asientoId)->update(['pagoproveedor_id' => $pago->id]);
        }

        self::espejarCuentacorrienteAnita($pago->fresh());
        self::espejarRetencionesAnita($pago->fresh());
        // grabarDesdeMovimiento corre antes de crear la OPA: en el alta pagoproveedor_id
        // todavía es 0 y no se escriben auxpag/tesmov RGP/RIP/RSP/RTP. Se completan acá,
        // con las retenciones ya volcadas.
        self::completarAuxpagRetencionesAnita($movimiento);
    }

    private static function persistirOpaDirecta(Caja_Movimiento $movimiento): void
    {
        if (! self::esOpaDirectaEnIngresosEgresos($movimiento)) {
            if ((int) ($movimiento->solicitudpago_id ?? 0) === 0
                && (int) ($movimiento->caja_movimiento_origen_id ?? 0) === 0) {
                self::retirarOpaDirectaSiDejoDeSerlo($movimiento);
            }

            return;
        }

        $pago = self::pagoVinculado($movimiento);
        if ($pago !== null && ! self::esOpaNacidaEnIngresoEgreso($pago)) {
            return;
        }

        $proveedorId = (int) ($movimiento->proveedor_id ?? 0);
        if ($proveedorId <= 0) {
            throw new InvalidArgumentException(
                'La orden de pago adelantada debe tener proveedor para generar el crédito en cuenta corriente.'
            );
        }

        [$monto, $monedaId, $cotizacion] = self::importeDesdeLineas($movimiento);
        if ($monto < 0.01) {
            throw new InvalidArgumentException('No hay importe para registrar la OPA en la cuenta corriente.');
        }

        $asientoId = (int) Asiento::query()
            ->where('caja_movimiento_id', (int) $movimiento->id)
            ->value('id');
        $usuarioId = (int) (Auth::id() ?: 0);
        if ($usuarioId <= 0) {
            $usuarioId = (int) ($movimiento->usuario_id ?? 0);
        }
        if ($usuarioId <= 0 && $pago !== null) {
            $usuarioId = (int) ($pago->usuario_id ?? 0);
        }

        $payload = [
            'empresa_id' => (int) $movimiento->empresa_id,
            'tipotransaccion_caja_id' => (int) $movimiento->tipotransaccion_caja_id,
            'tipocomprobante' => 'OPA',
            'letra' => (string) config('pagoproveedor.letra_default', 'A'),
            'sucursal' => PagoproveedorAnitaNumeracionSupport::sucursalParaOp((int) $movimiento->empresa_id),
            'numerotransaccion' => (string) ($movimiento->numerotransaccion ?? ''),
            'fecha' => $movimiento->fecha,
            'caja_id' => $movimiento->caja_id,
            'proveedor_id' => $proveedorId,
            'detalle' => (string) ($movimiento->detalle ?? ''),
            'estado' => 'CONFIRMADA',
            'monto' => $monto,
            'cotizacion' => $cotizacion,
            'moneda_id' => $monedaId,
            'modo_cotizacion' => 'dia',
            'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            'caja_movimiento_id' => (int) $movimiento->id,
            'asiento_id' => $asientoId > 0 ? $asientoId : null,
        ];

        if ($pago !== null && self::creditoYaAplicado($pago)) {
            $cambio = abs(round((float) $pago->monto, 2) - $monto) > 0.009
                || (int) $pago->proveedor_id !== $proveedorId
                || (int) $pago->moneda_id !== $monedaId
                || self::fechaYmd($pago->fecha) !== self::fechaYmd($movimiento->fecha);
            if ($cambio) {
                throw new InvalidArgumentException(
                    'La OPA '.$pago->etiquetaComprobante().' ya está aplicada en la cuenta corriente. Desaplicá el anticipo antes de cambiar importe, proveedor, moneda o fecha.'
                );
            }
            $pago->fill([
                'detalle' => $payload['detalle'],
                'cotizacion' => $cotizacion,
                'asiento_id' => $payload['asiento_id'],
                'caja_id' => $payload['caja_id'],
            ]);
            $pago->save();
            self::vincularMovimientoYAsiento($movimiento, $pago, $asientoId);

            return;
        }

        if ($pago === null) {
            $pago = Pagoproveedor::query()->create($payload);
            Pagoproveedor_Estado::query()->create([
                'pagoproveedor_id' => $pago->id,
                'fecha' => now(),
                'estado' => 'CONFIRMADA',
                'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                'observacion' => self::OBSERVACION_OPA_INGRESO_EGRESO,
            ]);
        } else {
            PagoproveedorAplicacionCuentacorrienteSupport::revertirAplicacionesExistentes($pago);
            $pago->fill($payload);
            $pago->save();
        }

        PagoproveedorAplicacionCuentacorrienteSupport::crearAnticipo(
            $pago->fresh(),
            $monto,
            $monedaId,
            $cotizacion
        );
        self::vincularMovimientoYAsiento($movimiento, $pago, $asientoId);
        self::espejarCuentacorrienteAnita($pago->fresh());
    }

    private static function retirarOpaDirectaSiDejoDeSerlo(Caja_Movimiento $movimiento): void
    {
        $pago = self::pagoVinculado($movimiento);
        if ($pago === null || ! self::esOpaNacidaEnIngresoEgreso($pago)) {
            return;
        }
        if (self::creditoYaAplicado($pago)) {
            throw new InvalidArgumentException(
                'La OPA '.$pago->etiquetaComprobante().' ya está aplicada en la cuenta corriente. Desaplicá el anticipo antes de cambiar el tipo de comprobante.'
            );
        }

        self::eliminarDesdeMovimiento($movimiento);
    }

    /**
     * @return array{0: float, 1: int, 2: float}
     */
    private static function importeDesdeLineas(Caja_Movimiento $movimiento): array
    {
        $monedaId = 1;
        $cotizacion = 1.0;
        $monto = 0.0;
        foreach ($movimiento->caja_movimiento_cuentacajas as $linea) {
            $monto += abs((float) ($linea->monto ?? 0));
            if ((int) ($linea->moneda_id ?? 0) > 0) {
                $monedaId = (int) $linea->moneda_id;
                $cotizacion = (float) ($linea->cotizacion ?: 1);
            }
        }

        return [round($monto, 2), $monedaId, $cotizacion > 0 ? $cotizacion : 1.0];
    }

    private static function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        return substr(trim((string) $fecha), 0, 10);
    }

    private static function esTipoOpa(Caja_Movimiento $movimiento): bool
    {
        $movimiento->loadMissing('tipotransaccioncajas');
        $abrev = strtoupper(trim((string) ($movimiento->tipotransaccioncajas->abreviatura ?? '')));

        return $abrev === 'OPA';
    }

    private static function esOpaNacidaEnIngresoEgreso(Pagoproveedor $pago): bool
    {
        return Pagoproveedor_Estado::query()
            ->where('pagoproveedor_id', (int) $pago->id)
            ->where('observacion', self::OBSERVACION_OPA_INGRESO_EGRESO)
            ->exists();
    }

    private static function creditoYaAplicado(Pagoproveedor $pago): bool
    {
        if (Proveedor_Cuentacorriente_Aplicacion::query()->where('pagoproveedor_id', (int) $pago->id)->exists()) {
            return true;
        }

        $ccIds = Proveedor_Cuentacorriente::query()
            ->where('pagoproveedor_id', (int) $pago->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($ccIds === []) {
            return false;
        }

        return Proveedor_Cuentacorriente_Aplicacion::query()
            ->where(function ($q) use ($ccIds) {
                $q->whereIn('proveedor_cuentacorriente_id', $ccIds)
                    ->orWhereIn('proveedor_cuentacorriente_aplicado_id', $ccIds);
            })
            ->exists();
    }

    private static function vincularMovimientoYAsiento(Caja_Movimiento $movimiento, Pagoproveedor $pago, int $asientoId): void
    {
        if ((int) ($movimiento->pagoproveedor_id ?? 0) !== (int) $pago->id) {
            $movimiento->pagoproveedor_id = (int) $pago->id;
            $movimiento->save();
        }

        if ($asientoId > 0) {
            Asiento::query()->whereKey($asientoId)->update(['pagoproveedor_id' => $pago->id]);
        }
    }

    public static function eliminarDesdeMovimiento(Caja_Movimiento $movimiento): void
    {
        $pago = self::pagoVinculado($movimiento);
        if ($pago === null) {
            return;
        }

        self::borrarCuentacorrienteAnita($pago);
        self::borrarRetencionesAnita($pago);

        PagoproveedorAplicacionCuentacorrienteSupport::revertirAplicacionesExistentes($pago);

        Asiento::query()
            ->where('pagoproveedor_id', (int) $pago->id)
            ->update(['pagoproveedor_id' => null]);

        $pago->caja_movimiento_id = null;
        $pago->asiento_id = null;
        $pago->save();

        if ((int) ($movimiento->pagoproveedor_id ?? 0) === (int) $pago->id) {
            $movimiento->pagoproveedor_id = null;
            $movimiento->save();
        }

        Pagoproveedor_Estado::query()->where('pagoproveedor_id', (int) $pago->id)->delete();
        $pago->delete();
    }

    private static function completarAuxpagRetencionesAnita(Caja_Movimiento $movimiento): void
    {
        try {
            IngresoEgresoAnitaTesmovSupport::completarRetencionesTesoreriaDesdeMovimiento($movimiento->fresh());
        } catch (Throwable $e) {
            Log::warning('anita_bridge.fallo', [
                'contexto' => 'auxpag retenciones OPA desde IE '.$movimiento->id,
                'mensaje' => $e->getMessage(),
            ]);
        }
    }

    /**
     * promov de la OPA en la cuenta corriente Anita del proveedor, igual que el
     * circuito de pagos. Anita es otra base: un corte del bridge no debe hacer
     * rollback del IE (la auditoría diaria lo informa como «Falta promov de la OP»).
     */
    private static function espejarRetencionesAnita(Pagoproveedor $pago): void
    {
        try {
            PagoproveedorAnitaRetencionEscrituraSupport::sincronizarDesdePago($pago->fresh(), true);
        } catch (Throwable $e) {
            Log::warning('anita_bridge.fallo', [
                'contexto' => 'retenciones OPA desde IE '.$pago->etiquetaComprobante(),
                'mensaje' => $e->getMessage(),
            ]);
        }
    }

    private static function borrarRetencionesAnita(Pagoproveedor $pago): void
    {
        try {
            PagoproveedorAnitaRetencionEscrituraSupport::eliminarDesdePago($pago);
        } catch (Throwable $e) {
            Log::warning('anita_bridge.fallo', [
                'contexto' => 'retenciones delete OPA desde IE '.$pago->etiquetaComprobante(),
                'mensaje' => $e->getMessage(),
            ]);
        }
    }

    private static function espejarCuentacorrienteAnita(Pagoproveedor $pago): void
    {
        try {
            app(ProveedorCuentacorrienteAplicacionAnitaSyncService::class)
                ->syncPorPagoproveedor((int) $pago->id);
        } catch (Throwable $e) {
            Log::warning('anita_bridge.fallo', [
                'contexto' => 'promov OPA desde IE '.$pago->etiquetaComprobante(),
                'mensaje' => $e->getMessage(),
            ]);
        }
    }

    private static function borrarCuentacorrienteAnita(Pagoproveedor $pago): void
    {
        try {
            app(ProveedorCuentacorrienteAplicacionAnitaSyncService::class)
                ->eliminarPromovCabeceraPorPago($pago);
        } catch (Throwable $e) {
            Log::warning('anita_bridge.fallo', [
                'contexto' => 'promov delete OPA desde IE '.$pago->etiquetaComprobante(),
                'mensaje' => $e->getMessage(),
            ]);
        }
    }

    private static function pagoVinculado(Caja_Movimiento $movimiento): ?Pagoproveedor
    {
        $pagoId = (int) ($movimiento->pagoproveedor_id ?? 0);
        if ($pagoId > 0) {
            $pago = Pagoproveedor::query()->find($pagoId);
            if ($pago) {
                return $pago;
            }
        }

        return Pagoproveedor::query()
            ->where('caja_movimiento_id', (int) $movimiento->id)
            ->first();
    }
}
