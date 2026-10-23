<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Orden de compra, recepción, factura y pago de una requisición, una fila.
 *
 * La orden sale de ordencompra.requisicion_id. Recepción, factura y pago
 * siguen el mismo camino que en la orden.
 */
final class RequisicionCircuitoRelacion
{
    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        return [
            'ordencompra_numero' => ['column' => 'rq_oc.ordencompra_numero', 'type' => 'texto', 'label' => 'Orden de compra'],
            'recepcion_numero' => ['column' => 'rq_recep.recepcion_numero', 'type' => 'texto', 'label' => 'Recepción'],
            'factura_numero' => ['column' => 'rq_fac.factura_numero', 'type' => 'texto', 'label' => 'Factura'],
            'pago_numero' => ['column' => 'rq_pago.pago_numero', 'type' => 'texto', 'label' => 'Orden de pago'],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Compras\Requisicion>  $query
     */
    public static function aplicarColumnas($query): void
    {
        $query->leftJoinSub(self::ordenes(), 'rq_oc', 'rq_oc.requisicion_id', '=', 'requisicion.id');
        $query->leftJoinSub(self::recepciones(), 'rq_recep', 'rq_recep.requisicion_id', '=', 'requisicion.id');
        $query->leftJoinSub(self::facturas(), 'rq_fac', 'rq_fac.requisicion_id', '=', 'requisicion.id');
        $query->leftJoinSub(self::pagos(), 'rq_pago', 'rq_pago.requisicion_id', '=', 'requisicion.id');
        $query->addSelect([
            'rq_oc.ordencompra_numero as ordencompra_numero',
            'rq_oc.ordencompra_ids as ordencompra_ids',
            'rq_recep.recepcion_numero as recepcion_numero',
            'rq_recep.recepcion_ids as recepcion_ids',
            'rq_fac.factura_numero as factura_numero',
            'rq_fac.factura_ids as factura_ids',
            'rq_pago.pago_numero as pago_numero',
            'rq_pago.pago_ids as pago_ids',
        ]);
    }

    public static function aplicarFiltro(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta
    ): void {
        if ($operador === 'igual' && str_contains($valor, ' | ')) {
            $alias = match ($campoKey) {
                'recepcion_numero' => 'recepcion_numero',
                'factura_numero' => 'factura_numero',
                'pago_numero' => 'pago_numero',
                default => 'ordencompra_numero',
            };
            $query->whereIn('requisicion.id', function (Builder $sub) use ($campoKey, $alias, $valor): void {
                $sub->fromSub(self::agrupado($campoKey), 'filtro_circ')
                    ->select('requisicion_id')
                    ->where($alias, $valor);
            });

            return;
        }

        if ($operador === 'vacio') {
            $query->whereNotExists(function (Builder $sub) use ($campoKey): void {
                self::existe($sub, $campoKey);
            });

            return;
        }

        $query->whereExists(function (Builder $sub) use ($campoKey, $operador, $valor, $valorHasta): void {
            self::existe($sub, $campoKey);
            $columna = match ($campoKey) {
                'recepcion_numero' => SqlDialectSupport::castTexto('rp.numerorecepcion'),
                'factura_numero' => SqlDialectSupport::castTexto('cp.numerocomprobante'),
                'pago_numero' => SqlDialectSupport::castTexto('pp.numerotransaccion'),
                default => SqlDialectSupport::castTexto('oc.numeroordencompra'),
            };
            PagoproveedorListadoFiltros::aplicarExpresionRelacion($sub, 'texto', $columna, $operador, $valor, $valorHasta);
        });
    }

    private static function existe(Builder $sub, string $campoKey): void
    {
        if ($campoKey === 'ordencompra_numero') {
            $sub->selectRaw('1')
                ->from('ordencompra as oc')
                ->whereColumn('oc.requisicion_id', 'requisicion.id');

            return;
        }

        if ($campoKey === 'recepcion_numero') {
            $sub->selectRaw('1')
                ->from('recepcion_proveedor as rp')
                ->join('ordencompra as oc', 'oc.id', '=', 'rp.ordencompra_id')
                ->whereColumn('oc.requisicion_id', 'requisicion.id');

            return;
        }

        if ($campoKey === 'pago_numero') {
            $sub->selectRaw('1')
                ->from('pagoproveedor_comprobante as ppc')
                ->join('proveedor_cuentacorriente as deuda', 'deuda.id', '=', 'ppc.proveedor_cuentacorriente_id')
                ->join('pagoproveedor as pp', 'pp.id', '=', 'ppc.pagoproveedor_id')
                ->join('comprobante_proveedor as cp', 'cp.id', '=', 'deuda.comprobante_proveedor_id')
                ->where(function (Builder $w): void {
                    $w->whereExists(function (Builder $directa): void {
                        $directa->selectRaw('1')
                            ->from('ordencompra as oc')
                            ->whereColumn('oc.id', 'cp.ordencompra_id')
                            ->whereColumn('oc.requisicion_id', 'requisicion.id');
                    })->orWhereExists(function (Builder $via): void {
                        $via->selectRaw('1')
                            ->from('comprobante_proveedor_recepcion as cpr')
                            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'cpr.recepcion_proveedor_id')
                            ->join('ordencompra as oc', 'oc.id', '=', 'rp.ordencompra_id')
                            ->whereColumn('cpr.comprobante_proveedor_id', 'cp.id')
                            ->whereColumn('oc.requisicion_id', 'requisicion.id');
                    });
                });

            return;
        }

        $sub->selectRaw('1')
            ->from('comprobante_proveedor as cp')
            ->where(function (Builder $w): void {
                $w->whereExists(function (Builder $directa): void {
                    $directa->selectRaw('1')
                        ->from('ordencompra as oc')
                        ->whereColumn('oc.id', 'cp.ordencompra_id')
                        ->whereColumn('oc.requisicion_id', 'requisicion.id');
                })->orWhereExists(function (Builder $via): void {
                    $via->selectRaw('1')
                        ->from('comprobante_proveedor_recepcion as cpr')
                        ->join('recepcion_proveedor as rp', 'rp.id', '=', 'cpr.recepcion_proveedor_id')
                        ->join('ordencompra as oc', 'oc.id', '=', 'rp.ordencompra_id')
                        ->whereColumn('cpr.comprobante_proveedor_id', 'cp.id')
                        ->whereColumn('oc.requisicion_id', 'requisicion.id');
                });
            });
    }

    private static function agrupado(string $campoKey): \Illuminate\Database\Query\Builder
    {
        return match ($campoKey) {
            'recepcion_numero' => self::recepciones(),
            'factura_numero' => self::facturas(),
            'pago_numero' => self::pagos(),
            default => self::ordenes(),
        };
    }

    private static function ordenes(): \Illuminate\Database\Query\Builder
    {
        $base = DB::table('ordencompra')
            ->where('requisicion_id', '>', 0)
            ->select('requisicion_id', 'id as ordencompra_id', 'numeroordencompra')
            ->distinct();

        return self::agrupar($base, 'ordencompra_id', 'numeroordencompra', 'ordencompra_numero', 'ordencompra_ids');
    }

    private static function recepciones(): \Illuminate\Database\Query\Builder
    {
        $base = DB::table('recepcion_proveedor as rp')
            ->join('ordencompra as oc', 'oc.id', '=', 'rp.ordencompra_id')
            ->where('oc.requisicion_id', '>', 0)
            ->select('oc.requisicion_id', 'rp.id as recepcion_id', 'rp.numerorecepcion')
            ->distinct();

        return self::agrupar($base, 'recepcion_id', 'numerorecepcion', 'recepcion_numero', 'recepcion_ids');
    }

    private static function facturas(): \Illuminate\Database\Query\Builder
    {
        $directa = DB::table('comprobante_proveedor as cp')
            ->join('ordencompra as oc', 'oc.id', '=', 'cp.ordencompra_id')
            ->where('oc.requisicion_id', '>', 0)
            ->select('oc.requisicion_id', 'cp.id as comprobante_id', 'cp.numerocomprobante');
        $via = DB::table('comprobante_proveedor_recepcion as cpr')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cpr.comprobante_proveedor_id')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'cpr.recepcion_proveedor_id')
            ->join('ordencompra as oc', 'oc.id', '=', 'rp.ordencompra_id')
            ->where('oc.requisicion_id', '>', 0)
            ->select('oc.requisicion_id', 'cp.id as comprobante_id', 'cp.numerocomprobante');

        return self::agrupar($directa->union($via), 'comprobante_id', 'numerocomprobante', 'factura_numero', 'factura_ids');
    }

    private static function pagos(): \Illuminate\Database\Query\Builder
    {
        $directa = DB::table('comprobante_proveedor as cp')
            ->join('ordencompra as oc', 'oc.id', '=', 'cp.ordencompra_id')
            ->join('proveedor_cuentacorriente as deuda', 'deuda.comprobante_proveedor_id', '=', 'cp.id')
            ->join('pagoproveedor_comprobante as ppc', 'ppc.proveedor_cuentacorriente_id', '=', 'deuda.id')
            ->join('pagoproveedor as pp', 'pp.id', '=', 'ppc.pagoproveedor_id')
            ->where('oc.requisicion_id', '>', 0)
            ->select('oc.requisicion_id', 'pp.id as pagoproveedor_id', 'pp.numerotransaccion');
        $via = DB::table('comprobante_proveedor_recepcion as cpr')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'cpr.recepcion_proveedor_id')
            ->join('ordencompra as oc', 'oc.id', '=', 'rp.ordencompra_id')
            ->join('proveedor_cuentacorriente as deuda', 'deuda.comprobante_proveedor_id', '=', 'cpr.comprobante_proveedor_id')
            ->join('pagoproveedor_comprobante as ppc', 'ppc.proveedor_cuentacorriente_id', '=', 'deuda.id')
            ->join('pagoproveedor as pp', 'pp.id', '=', 'ppc.pagoproveedor_id')
            ->where('oc.requisicion_id', '>', 0)
            ->select('oc.requisicion_id', 'pp.id as pagoproveedor_id', 'pp.numerotransaccion');

        return self::agrupar($directa->union($via), 'pagoproveedor_id', 'numerotransaccion', 'pago_numero', 'pago_ids');
    }

    private static function agrupar(
        \Illuminate\Database\Query\Builder $base,
        string $id,
        string $texto,
        string $aliasTexto,
        string $aliasIds
    ): \Illuminate\Database\Query\Builder {
        return DB::query()
            ->fromSub($base, 'circ')
            ->groupBy('requisicion_id')
            ->select('requisicion_id')
            ->selectRaw(SqlDialectSupport::textosEnOrden(SqlDialectSupport::castTexto($texto), $id).' as '.$aliasTexto)
            ->selectRaw(SqlDialectSupport::textosEnOrden(SqlDialectSupport::castTexto($id), $id).' as '.$aliasIds);
    }
}
