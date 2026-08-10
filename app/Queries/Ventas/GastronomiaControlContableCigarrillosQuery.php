<?php

declare(strict_types=1);

namespace App\Queries\Ventas;

use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\GastronomiaInsumosTipoarticuloReporteFiltros;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cantidades y precio unitario histórico de factura (línea menú cigarrillos) por artículo/día.
 */
class GastronomiaControlContableCigarrillosQuery
{
    /**
     * Precio unitario de venta histórico: promedio del precio de la línea menú cigarrillos
     * (precio > 0) de las mismas facturas donde se vendió el insumo cigarrillo (suele ir a $0).
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object{articulo_id:int,dia:string,precio:float}>
     */
    public function preciosMenuHistoricoPorArticuloDia(array $filtros): Collection
    {
        $tipoarticuloId = (int) ($filtros['tipoarticulo_id'] ?? 0);
        if ($tipoarticuloId <= 0) {
            return collect();
        }

        [$desde, $hasta] = GastronomiaInsumosTipoarticuloReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($desde === '' || $hasta === '') {
            return collect();
        }

        $query = DB::table('venta_emision as ve_cig')
            ->join('venta as v', 'v.id', '=', 've_cig.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've_cig.articulo_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('venta_emision as ve_menu', function ($join) {
                $join->on('ve_menu.venta_id', '=', 've_cig.venta_id')
                    ->where('ve_menu.precio', '>', 0.0001);
            })
            ->join('articulo as am', 'am.id', '=', 've_menu.articulo_id')
            ->whereNull('v.deleted_at')
            ->where('a.tipoarticulo_id', $tipoarticuloId)
            ->whereRaw('UPPER(am.descripcion) LIKE ?', ['%CIGARRILLO%'])
            ->whereDate('v.fechajornada', '>=', $desde)
            ->whereDate('v.fechajornada', '<=', $hasta);

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('pv.empresa_id', $empresaId);
        }

        return $query
            ->select(['ve_cig.articulo_id'])
            ->selectRaw(SqlDialectSupport::fecha('v.fechajornada').' as dia')
            ->selectRaw('ROUND(AVG(ve_menu.precio), 2) as precio')
            ->groupBy('ve_cig.articulo_id', DB::raw(SqlDialectSupport::fecha('v.fechajornada')))
            ->get();
    }

    /**
     * Fallback: si el insumo tiene precio propio en la factura (no opcional a $0).
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object{articulo_id:int,dia:string,precio:float}>
     */
    public function preciosLineaPropiaPorArticuloDia(array $filtros): Collection
    {
        $tipoarticuloId = (int) ($filtros['tipoarticulo_id'] ?? 0);
        if ($tipoarticuloId <= 0) {
            return collect();
        }

        [$desde, $hasta] = GastronomiaInsumosTipoarticuloReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($desde === '' || $hasta === '') {
            return collect();
        }

        $cantidadExpr = GastronomiaVentaComprobanteSignoSupport::sqlCantidadLineaVenta();

        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->whereNull('v.deleted_at')
            ->where('a.tipoarticulo_id', $tipoarticuloId)
            ->where('ve.precio', '>', 0.0001)
            ->whereDate('v.fechajornada', '>=', $desde)
            ->whereDate('v.fechajornada', '<=', $hasta);

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('pv.empresa_id', $empresaId);
        }

        return $query
            ->select(['ve.articulo_id'])
            ->selectRaw(SqlDialectSupport::fecha('v.fechajornada').' as dia')
            ->selectRaw(
                'ROUND(SUM(('.$cantidadExpr.') * ve.precio) / NULLIF(SUM(ABS('.$cantidadExpr.')), 0), 2) as precio'
            )
            ->groupBy('ve.articulo_id', DB::raw(SqlDialectSupport::fecha('v.fechajornada')))
            ->get();
    }
}
