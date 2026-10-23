<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Database\SqlDialectSupport;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Recepción, factura y orden de pago de una orden de compra, una fila.
 *
 * La factura entra por ordencompra_id o por la recepción de esa orden.
 * El pago sale de esas facturas. Texto e ids van en el mismo orden.
 */
final class OrdencompraCircuitoRelacion
{
    public const RECURSO = 'compras.ordencompra';

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        return [
            'recepcion_numero' => ['column' => 'oc_recep.recepcion_numero', 'type' => 'texto', 'label' => 'Recepción'],
            'factura_numero' => ['column' => 'oc_fac.factura_numero', 'type' => 'texto', 'label' => 'Factura'],
            'pago_numero' => ['column' => 'oc_pago.pago_numero', 'type' => 'texto', 'label' => 'Orden de pago'],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Compras\Ordencompra>  $query
     */
    public static function aplicarColumnas($query): void
    {
        $query->leftJoinSub(self::recepciones(), 'oc_recep', 'oc_recep.ordencompra_id', '=', 'ordencompra.id');
        $query->leftJoinSub(self::facturas(), 'oc_fac', 'oc_fac.ordencompra_id', '=', 'ordencompra.id');
        $query->leftJoinSub(self::pagos(), 'oc_pago', 'oc_pago.ordencompra_id', '=', 'ordencompra.id');
        $query->addSelect([
            'oc_recep.recepcion_numero as recepcion_numero',
            'oc_recep.recepcion_ids as recepcion_ids',
            'oc_fac.factura_numero as factura_numero',
            'oc_fac.factura_ids as factura_ids',
            'oc_pago.pago_numero as pago_numero',
            'oc_pago.pago_ids as pago_ids',
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
                'factura_numero' => 'factura_numero',
                'pago_numero' => 'pago_numero',
                default => 'recepcion_numero',
            };
            $query->whereIn('ordencompra.id', function (Builder $sub) use ($campoKey, $alias, $valor): void {
                $sub->fromSub(self::agrupado($campoKey), 'filtro_circ')
                    ->select('ordencompra_id')
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
                'factura_numero' => SqlDialectSupport::castTexto('cp.numerocomprobante'),
                'pago_numero' => SqlDialectSupport::castTexto('pp.numerotransaccion'),
                default => SqlDialectSupport::castTexto('rp.numerorecepcion'),
            };
            PagoproveedorListadoFiltros::aplicarExpresionRelacion($sub, 'texto', $columna, $operador, $valor, $valorHasta);
        });
    }

    private static function existe(Builder $sub, string $campoKey): void
    {
        if ($campoKey === 'pago_numero') {
            $sub->selectRaw('1')
                ->from('pagoproveedor_comprobante as ppc')
                ->join('proveedor_cuentacorriente as deuda', 'deuda.id', '=', 'ppc.proveedor_cuentacorriente_id')
                ->join('pagoproveedor as pp', 'pp.id', '=', 'ppc.pagoproveedor_id')
                ->join('comprobante_proveedor as cp', 'cp.id', '=', 'deuda.comprobante_proveedor_id')
                ->where(function (Builder $w): void {
                    $w->whereColumn('cp.ordencompra_id', 'ordencompra.id')
                        ->orWhereExists(function (Builder $via): void {
                            $via->selectRaw('1')
                                ->from('comprobante_proveedor_recepcion as cpr')
                                ->join('recepcion_proveedor as rp', 'rp.id', '=', 'cpr.recepcion_proveedor_id')
                                ->whereColumn('cpr.comprobante_proveedor_id', 'cp.id')
                                ->whereColumn('rp.ordencompra_id', 'ordencompra.id');
                        });
                });

            return;
        }

        if ($campoKey === 'factura_numero') {
            $sub->selectRaw('1')
                ->from('comprobante_proveedor as cp')
                ->where(function (Builder $w): void {
                    $w->whereColumn('cp.ordencompra_id', 'ordencompra.id')
                        ->orWhereExists(function (Builder $via): void {
                            $via->selectRaw('1')
                                ->from('comprobante_proveedor_recepcion as cpr')
                                ->join('recepcion_proveedor as rp', 'rp.id', '=', 'cpr.recepcion_proveedor_id')
                                ->whereColumn('cpr.comprobante_proveedor_id', 'cp.id')
                                ->whereColumn('rp.ordencompra_id', 'ordencompra.id');
                        });
                });

            return;
        }

        $sub->selectRaw('1')
            ->from('recepcion_proveedor as rp')
            ->whereColumn('rp.ordencompra_id', 'ordencompra.id');
    }

    private static function agrupado(string $campoKey): \Illuminate\Database\Query\Builder
    {
        return match ($campoKey) {
            'factura_numero' => self::facturas(),
            'pago_numero' => self::pagos(),
            default => self::recepciones(),
        };
    }

    private static function recepciones(): \Illuminate\Database\Query\Builder
    {
        $base = DB::table('recepcion_proveedor')
            ->where('ordencompra_id', '>', 0)
            ->select('ordencompra_id', 'id as recepcion_id', 'numerorecepcion')
            ->distinct();

        return self::agrupar($base, 'recepcion_id', 'numerorecepcion', 'recepcion_numero', 'recepcion_ids');
    }

    private static function facturas(): \Illuminate\Database\Query\Builder
    {
        $directa = DB::table('comprobante_proveedor')
            ->where('ordencompra_id', '>', 0)
            ->select('ordencompra_id', 'id as comprobante_id', 'numerocomprobante');
        $via = DB::table('comprobante_proveedor_recepcion as cpr')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cpr.comprobante_proveedor_id')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'cpr.recepcion_proveedor_id')
            ->where('rp.ordencompra_id', '>', 0)
            ->select('rp.ordencompra_id', 'cp.id as comprobante_id', 'cp.numerocomprobante');

        return self::agrupar($directa->union($via), 'comprobante_id', 'numerocomprobante', 'factura_numero', 'factura_ids');
    }

    private static function pagos(): \Illuminate\Database\Query\Builder
    {
        $directa = DB::table('comprobante_proveedor as cp')
            ->join('proveedor_cuentacorriente as deuda', 'deuda.comprobante_proveedor_id', '=', 'cp.id')
            ->join('pagoproveedor_comprobante as ppc', 'ppc.proveedor_cuentacorriente_id', '=', 'deuda.id')
            ->join('pagoproveedor as pp', 'pp.id', '=', 'ppc.pagoproveedor_id')
            ->where('cp.ordencompra_id', '>', 0)
            ->select('cp.ordencompra_id', 'pp.id as pagoproveedor_id', 'pp.numerotransaccion');
        $via = DB::table('comprobante_proveedor_recepcion as cpr')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'cpr.recepcion_proveedor_id')
            ->join('proveedor_cuentacorriente as deuda', 'deuda.comprobante_proveedor_id', '=', 'cpr.comprobante_proveedor_id')
            ->join('pagoproveedor_comprobante as ppc', 'ppc.proveedor_cuentacorriente_id', '=', 'deuda.id')
            ->join('pagoproveedor as pp', 'pp.id', '=', 'ppc.pagoproveedor_id')
            ->where('rp.ordencompra_id', '>', 0)
            ->select('rp.ordencompra_id', 'pp.id as pagoproveedor_id', 'pp.numerotransaccion');

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
            ->groupBy('ordencompra_id')
            ->select('ordencompra_id')
            ->selectRaw(SqlDialectSupport::textosEnOrden(SqlDialectSupport::castTexto($texto), $id).' as '.$aliasTexto)
            ->selectRaw(SqlDialectSupport::textosEnOrden(SqlDialectSupport::castTexto($id), $id).' as '.$aliasIds);
    }
}
