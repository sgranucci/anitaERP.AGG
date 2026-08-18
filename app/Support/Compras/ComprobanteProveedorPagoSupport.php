<?php

namespace App\Support\Compras;

use Illuminate\Support\Facades\DB;

/**
 * Detecta pagos (OP) aplicados sobre la cuenta corriente de un comprobante de proveedor.
 *
 * La fila de deuda NO lleva pagoproveedor_id: el pago crea otra fila CC y/o
 * registros en pagoproveedor_comprobante / proveedor_cuentacorriente_aplicacion.
 */
final class ComprobanteProveedorPagoSupport
{
    /** @return list<int> */
    public static function idsCuentacorrienteDeuda(int $comprobanteId): array
    {
        if ($comprobanteId <= 0) {
            return [];
        }

        $desdeCuotas = DB::table('comprobante_proveedor_cuota')
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->whereNotNull('proveedor_cuentacorriente_id')
            ->pluck('proveedor_cuentacorriente_id');

        $desdeCc = DB::table('proveedor_cuentacorriente')
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->where(function ($q) {
                $q->whereNull('pagoproveedor_id')
                    ->orWhere('pagoproveedor_id', 0);
            })
            ->pluck('id');

        return $desdeCuotas
            ->merge($desdeCc)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function tienePagosAplicados(int $comprobanteId): bool
    {
        if ($comprobanteId <= 0) {
            return false;
        }

        // Filas de pago vinculadas directo al comprobante.
        $pagoDirecto = DB::table('proveedor_cuentacorriente')
            ->where('comprobante_proveedor_id', $comprobanteId)
            ->whereNotNull('pagoproveedor_id')
            ->where('pagoproveedor_id', '>', 0)
            ->exists();
        if ($pagoDirecto) {
            return true;
        }

        $ccIds = self::idsCuentacorrienteDeuda($comprobanteId);
        if ($ccIds === []) {
            return false;
        }

        if (DB::table('pagoproveedor_comprobante')
            ->whereIn('proveedor_cuentacorriente_id', $ccIds)
            ->exists()) {
            return true;
        }

        return DB::table('proveedor_cuentacorriente_aplicacion')
            ->whereIn('proveedor_cuentacorriente_id', $ccIds)
            ->exists();
    }

    public static function assertSinPagosAplicados(int $comprobanteId, string $accion = 'modificar'): void
    {
        if (self::tienePagosAplicados($comprobanteId)) {
            throw new \RuntimeException(
                'No se puede '.$accion.': el comprobante ya tiene pagos aplicados (parcial o total).'
            );
        }
    }
}
