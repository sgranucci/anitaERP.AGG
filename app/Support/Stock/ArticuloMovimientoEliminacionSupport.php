<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_Movimiento;
use Illuminate\Database\Eloquent\Builder;

/**
 * Elimina líneas de articulo_movimiento vía Eloquent antes de borrar el registro padre,
 * para que Articulo_MovimientoObserver actualice articulo_saldo_deposito.
 */
class ArticuloMovimientoEliminacionSupport
{
    public static function eliminarPorQuery(Builder $query): int
    {
        $eliminados = 0;
        foreach ($query->get() as $movimiento) {
            $movimiento->delete();
            $eliminados++;
        }

        return $eliminados;
    }

    public static function eliminarPorVentaId(int $ventaId): int
    {
        if ($ventaId <= 0) {
            return 0;
        }

        return self::eliminarPorQuery(Articulo_Movimiento::query()->where('venta_id', $ventaId));
    }

    public static function eliminarPorVentaEmisionId(int $ventaEmisionId): int
    {
        if ($ventaEmisionId <= 0) {
            return 0;
        }

        return self::eliminarPorQuery(Articulo_Movimiento::query()->where('venta_emision_id', $ventaEmisionId));
    }

    public static function eliminarPorMovimientoStockId(int $movimientoStockId): int
    {
        if ($movimientoStockId <= 0) {
            return 0;
        }

        return self::eliminarPorQuery(Articulo_Movimiento::query()->where('movimientostock_id', $movimientoStockId));
    }

    public static function eliminarPorPedidoCombinacionId(int $pedidoCombinacionId): int
    {
        if ($pedidoCombinacionId <= 0) {
            return 0;
        }

        return self::eliminarPorQuery(Articulo_Movimiento::query()->where('pedido_combinacion_id', $pedidoCombinacionId));
    }

    public static function eliminarPorOrdentrabajoId(int $ordentrabajoId): int
    {
        if ($ordentrabajoId <= 0) {
            return 0;
        }

        return self::eliminarPorQuery(Articulo_Movimiento::query()->where('ordentrabajo_id', $ordentrabajoId));
    }
}
