<?php

namespace App\Support\Compras;

use App\Models\Contable\Asiento;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * La factura nacida en Ingresos y egresos se contabiliza con el asiento del
 * movimiento de caja. Guardarla desde Comprobantes de proveedor la vuelve a
 * contabilizar y duplica el mayor.
 */
final class ComprobanteProveedorIngresoEgresoEdicionSupport
{
    public static function es(object $comprobante): bool
    {
        return (string) ($comprobante->origen_entrada ?? '') === ComprobanteProveedorOrigenEntrada::INGRESO_EGRESO;
    }

    public static function mensaje(): string
    {
        return 'Esta factura se cargó desde Ingresos y egresos. Solo se puede modificar en ese movimiento de caja.';
    }

    public static function assertPuedeModificarDesdeComprobante(object $comprobante): void
    {
        if (self::es($comprobante)) {
            throw new RuntimeException(self::mensaje());
        }
    }

    public static function urlMovimiento(Model $comprobante): ?string
    {
        $cajaId = (int) ($comprobante->caja_movimiento_id ?? 0);
        if ($cajaId <= 0 || ! can('editar-ingresos-egresos-caja', false)) {
            return null;
        }

        return route('editar_ingresoegreso', [
            'id' => $cajaId,
            'origen' => 'ingresoegreso',
        ]);
    }

    /**
     * Asiento contable del movimiento de caja (no vive en comprobante_proveedor.asiento_id).
     */
    public static function asientoDelMovimiento(object $comprobante): ?Asiento
    {
        if (! self::es($comprobante)) {
            return null;
        }

        $cajaId = (int) ($comprobante->caja_movimiento_id ?? 0);
        if ($cajaId <= 0) {
            return null;
        }

        return Asiento::query()
            ->where('caja_movimiento_id', $cajaId)
            ->orderByDesc('id')
            ->first();
    }
}
