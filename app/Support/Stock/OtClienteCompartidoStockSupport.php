<?php

namespace App\Support\Stock;

use App\Models\Ventas\Pedido_Combinacion;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Varios clientes fabrican en la misma OT. Cada uno tiene sus pares.
 * Facturar y enviar a un cliente no saca del stock los pares que otro
 * cliente de esa OT anuló y pasó a STOCK.
 *
 * El alta a STOCK queda en el bucket de la OT (lote 0). La factura del
 * compañero graba «Consumo de OT» sobre ese mismo bucket: no se netea
 * y no se vuelve a grabar.
 */
final class OtClienteCompartidoStockSupport
{
    public const CONCEPTO_CONSUMO_OT = 'Consumo de OT';

    /**
     * La línea es de un cliente que fabrica en esta OT, no es STOCK
     * y no se armó consumiendo otra OT de stock.
     */
    public static function esFacturaDeCompanero(Pedido_Combinacion $linea, int $otId): bool
    {
        if ($otId <= 0 || (int) ($linea->ot_id ?? 0) !== $otId) {
            return false;
        }

        $talles = DB::table('ordentrabajo_combinacion_talle as oct')
            ->join('pedido_combinacion_talle as pct', 'pct.id', '=', 'oct.pedido_combinacion_talle_id')
            ->where('pct.pedido_combinacion_id', (int) $linea->id)
            ->where('oct.ordentrabajo_id', $otId)
            ->get(['oct.cliente_id', 'oct.ordentrabajo_stock_id']);

        if ($talles->isEmpty()) {
            return false;
        }

        foreach ($talles as $talle) {
            if (ReporteStockOtSituacionSupport::esClienteStock($talle->cliente_id ?? 0)) {
                return false;
            }
            if ((int) ($talle->ordentrabajo_stock_id ?? 0) > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Saca del saldo disponible el consumo de la factura de un compañero.
     * El picking y el alta a STOCK de la misma OT siguen contando.
     */
    public static function excluirConsumoFacturaCompanero(EloquentBuilder|QueryBuilder $query, string $alias = 'articulo_movimiento'): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            throw new \InvalidArgumentException('Alias de articulo_movimiento inválido.');
        }

        $stockId = ReporteStockOtSituacionSupport::clienteStockId();

        $query->whereNotExists(function ($sub) use ($alias, $stockId) {
            $sub->select(DB::raw('1'))
                ->from('pedido_combinacion as pc_comp')
                ->whereColumn('pc_comp.id', $alias.'.pedido_combinacion_id')
                ->whereColumn('pc_comp.ot_id', $alias.'.ordentrabajo_id')
                ->where('pc_comp.ot_id', '>', 0)
                ->where($alias.'.concepto', self::CONCEPTO_CONSUMO_OT)
                ->where(function ($lote) use ($alias) {
                    $lote->whereNull($alias.'.lote')
                        ->orWhere($alias.'.lote', 0);
                })
                ->whereExists(function ($oct) use ($alias) {
                    $oct->select(DB::raw('1'))
                        ->from('ordentrabajo_combinacion_talle as oct_comp')
                        ->join('pedido_combinacion_talle as pct_comp', 'pct_comp.id', '=', 'oct_comp.pedido_combinacion_talle_id')
                        ->whereColumn('pct_comp.pedido_combinacion_id', 'pc_comp.id')
                        ->whereColumn('oct_comp.ordentrabajo_id', $alias.'.ordentrabajo_id');
                })
                ->whereNotExists(function ($propio) use ($alias, $stockId) {
                    $propio->select(DB::raw('1'))
                        ->from('ordentrabajo_combinacion_talle as oct_prop')
                        ->join('pedido_combinacion_talle as pct_prop', 'pct_prop.id', '=', 'oct_prop.pedido_combinacion_talle_id')
                        ->whereColumn('pct_prop.pedido_combinacion_id', 'pc_comp.id')
                        ->whereColumn('oct_prop.ordentrabajo_id', $alias.'.ordentrabajo_id')
                        ->where(function ($motivo) use ($stockId) {
                            $motivo->where('oct_prop.cliente_id', $stockId)
                                ->orWhere('oct_prop.ordentrabajo_stock_id', '>', 0);
                        });
                });
        });
    }
}
