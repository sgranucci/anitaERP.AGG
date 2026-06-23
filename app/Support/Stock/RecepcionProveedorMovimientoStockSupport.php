<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;

final class RecepcionProveedorMovimientoStockSupport
{
    /**
     * Corrige articulo_movimiento.cantidad = 0 generados por recepciones confirmadas
     * cuando el signo del tipo se leía vía accessor (S/R) en lugar del valor numérico en BD.
     */
    public static function repararCantidadesArticuloMovimientoCero(): int
    {
        $filas = DB::table('articulo_movimiento as am')
            ->join('recepcion_proveedor_articulo as rpa', 'rpa.articulo_movimiento_id', '=', 'am.id')
            ->join('tipotransaccion_stock as ts', 'ts.id', '=', 'am.tipotransaccion_stock_id')
            ->where('am.cantidad', 0)
            ->whereRaw('ABS(COALESCE(NULLIF(rpa.cantidad_stock, 0), rpa.cantidad)) > 0.000001')
            ->select([
                'am.id',
                'rpa.cantidad',
                'rpa.cantidad_stock',
                'ts.signo',
            ])
            ->get();

        $actualizados = 0;
        foreach ($filas as $fila) {
            $cantidadBase = (float) ($fila->cantidad_stock ?: $fila->cantidad);
            $cantidadFirmada = ArticuloMovimientoCantidadSignoSupport::cantidadFirmadaSignoStock(
                $cantidadBase,
                (int) $fila->signo
            );

            DB::table('articulo_movimiento')
                ->where('id', (int) $fila->id)
                ->update(['cantidad' => $cantidadFirmada]);

            $actualizados++;
        }

        return $actualizados;
    }
}
