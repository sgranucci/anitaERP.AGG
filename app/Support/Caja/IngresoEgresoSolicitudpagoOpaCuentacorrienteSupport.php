<?php

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Contable\Asiento;
use App\Support\Compras\PagoproveedorAplicacionCuentacorrienteSupport;
use Auth;
use InvalidArgumentException;

/**
 * SP ANTICIPADA pagada por IE: deja OPA en pagoproveedor + crédito impago en CC.
 */
final class IngresoEgresoSolicitudpagoOpaCuentacorrienteSupport
{
    public static function persistirDesdeMovimiento(Caja_Movimiento $movimiento): void
    {
        $movimiento->loadMissing(['solicitudpagos', 'caja_movimiento_cuentacajas']);
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

        $asientoId = (int) Asiento::query()
            ->where('caja_movimiento_id', (int) $movimiento->id)
            ->value('id');

        $pago = self::pagoVinculado($movimiento);
        $payload = [
            'empresa_id' => (int) $movimiento->empresa_id,
            'tipotransaccion_caja_id' => (int) $movimiento->tipotransaccion_caja_id,
            'tipocomprobante' => IngresoEgresoSolicitudpagoSupport::abreviaturaTipoPago($sp),
            'letra' => (string) config('pagoproveedor.letra_default', 'A'),
            'sucursal' => (int) config('pagoproveedor.sucursal_default', 1),
            'numerotransaccion' => (string) ($movimiento->numerotransaccion ?? ''),
            'fecha' => $movimiento->fecha,
            'proveedor_id' => $proveedorId,
            'detalle' => (string) ($movimiento->detalle ?? ('Pago SP '.$sp->codigo)),
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
            $monto,
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
    }

    public static function eliminarDesdeMovimiento(Caja_Movimiento $movimiento): void
    {
        $pago = self::pagoVinculado($movimiento);
        if ($pago === null) {
            return;
        }

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
