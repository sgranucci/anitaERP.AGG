<?php

namespace App\Queries\Ventas;

use App\Models\Stock\Articulo;
use App\Support\Ventas\GastronomiaInsumosTipoarticuloReporteFiltros;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cantidades vendidas en facturas gastronomía por artículo y día (tipo de artículo configurable).
 */
class GastronomiaInsumosTipoarticuloReporteQuery
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object{articulo_id:int,sku:string,descripcion:string,dia:string,cantidad:float}>
     */
    public function cantidadesPorArticuloDia(array $filtros): Collection
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
            ->whereDate('v.fechajornada', '>=', $desde)
            ->whereDate('v.fechajornada', '<=', $hasta);

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('pv.empresa_id', $empresaId);
        }

        return $query
            ->select([
                've.articulo_id',
                'a.sku',
                'a.descripcion',
            ])
            ->selectRaw('DATE(v.fechajornada) as dia')
            ->selectRaw('SUM('.$cantidadExpr.') as cantidad')
            ->groupBy('ve.articulo_id', 'a.sku', 'a.descripcion', DB::raw('DATE(v.fechajornada)'))
            ->orderBy('a.sku')
            ->orderBy('dia')
            ->get();
    }

    /**
     * @return Collection<int, object{id:int,sku:string,descripcion:string}>
     */
    public function articulosCatalogo(int $tipoarticuloId): Collection
    {
        if ($tipoarticuloId <= 0) {
            return collect();
        }

        return Articulo::query()
            ->where('tipoarticulo_id', $tipoarticuloId)
            ->orderBy('sku')
            ->get(['id', 'sku', 'descripcion']);
    }
}
