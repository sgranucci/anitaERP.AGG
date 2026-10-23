<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Support\Compras\PagoproveedorListadoFiltros;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Factura y orden de pago de una recepción, una fila.
 */
final class RecepcionCircuitoRelacion
{
    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        return [
            'factura_numero' => ['column' => 'rp_fac.factura_numero', 'type' => 'texto', 'label' => 'Factura'],
            'pago_numero' => ['column' => 'rp_pago.pago_numero', 'type' => 'texto', 'label' => 'Orden de pago'],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Stock\Recepcion_Proveedor>  $query
     */
    public static function aplicarColumnas($query): void
    {
        $query->leftJoinSub(self::facturas(), 'rp_fac', 'rp_fac.recepcion_proveedor_id', '=', 'recepcion_proveedor.id');
        $query->leftJoinSub(self::pagos(), 'rp_pago', 'rp_pago.recepcion_proveedor_id', '=', 'recepcion_proveedor.id');
        $query->addSelect([
            'rp_fac.factura_numero as factura_numero',
            'rp_fac.factura_ids as factura_ids',
            'rp_pago.pago_numero as pago_numero',
            'rp_pago.pago_ids as pago_ids',
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
            $alias = $campoKey === 'pago_numero' ? 'pago_numero' : 'factura_numero';
            $base = $campoKey === 'pago_numero' ? self::pagos() : self::facturas();
            $query->whereIn('recepcion_proveedor.id', function (Builder $sub) use ($base, $alias, $valor): void {
                $sub->fromSub($base, 'filtro_circ')
                    ->select('recepcion_proveedor_id')
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
            $columna = $campoKey === 'pago_numero'
                ? SqlDialectSupport::castTexto('pp.numerotransaccion')
                : SqlDialectSupport::castTexto('cp.numerocomprobante');
            PagoproveedorListadoFiltros::aplicarExpresionRelacion($sub, 'texto', $columna, $operador, $valor, $valorHasta);
        });
    }

    private static function existe(Builder $sub, string $campoKey): void
    {
        if ($campoKey === 'pago_numero') {
            $sub->selectRaw('1')
                ->from('comprobante_proveedor_recepcion as cpr')
                ->join('proveedor_cuentacorriente as deuda', 'deuda.comprobante_proveedor_id', '=', 'cpr.comprobante_proveedor_id')
                ->join('pagoproveedor_comprobante as ppc', 'ppc.proveedor_cuentacorriente_id', '=', 'deuda.id')
                ->join('pagoproveedor as pp', 'pp.id', '=', 'ppc.pagoproveedor_id')
                ->whereColumn('cpr.recepcion_proveedor_id', 'recepcion_proveedor.id');

            return;
        }

        $sub->selectRaw('1')
            ->from('comprobante_proveedor_recepcion as cpr')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cpr.comprobante_proveedor_id')
            ->whereColumn('cpr.recepcion_proveedor_id', 'recepcion_proveedor.id');
    }

    private static function facturas(): \Illuminate\Database\Query\Builder
    {
        $base = DB::table('comprobante_proveedor_recepcion as cpr')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cpr.comprobante_proveedor_id')
            ->select('cpr.recepcion_proveedor_id', 'cp.id as comprobante_id', 'cp.numerocomprobante')
            ->distinct();

        return self::agrupar($base, 'comprobante_id', 'numerocomprobante', 'factura_numero', 'factura_ids');
    }

    private static function pagos(): \Illuminate\Database\Query\Builder
    {
        $base = DB::table('comprobante_proveedor_recepcion as cpr')
            ->join('proveedor_cuentacorriente as deuda', 'deuda.comprobante_proveedor_id', '=', 'cpr.comprobante_proveedor_id')
            ->join('pagoproveedor_comprobante as ppc', 'ppc.proveedor_cuentacorriente_id', '=', 'deuda.id')
            ->join('pagoproveedor as pp', 'pp.id', '=', 'ppc.pagoproveedor_id')
            ->select('cpr.recepcion_proveedor_id', 'pp.id as pagoproveedor_id', 'pp.numerotransaccion')
            ->distinct();

        return self::agrupar($base, 'pagoproveedor_id', 'numerotransaccion', 'pago_numero', 'pago_ids');
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
            ->groupBy('recepcion_proveedor_id')
            ->select('recepcion_proveedor_id')
            ->selectRaw(SqlDialectSupport::textosEnOrden(SqlDialectSupport::castTexto($texto), $id).' as '.$aliasTexto)
            ->selectRaw(SqlDialectSupport::textosEnOrden(SqlDialectSupport::castTexto($id), $id).' as '.$aliasIds);
    }
}
