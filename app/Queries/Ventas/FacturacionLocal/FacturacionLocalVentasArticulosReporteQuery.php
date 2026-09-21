<?php

namespace App\Queries\Ventas\FacturacionLocal;

use App\Support\Ventas\FacturacionLocal\FacturacionLocalVentasArticulosReporteFiltros;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas netas (FAC − NC) de Facturación Local por artículo / combinación-color / talle.
 */
final class FacturacionLocalVentasArticulosReporteQuery
{
    private static function cantidadExpr(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlCantidadLineaVenta();
    }

    private static function importeExpr(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlImporteLineaVenta();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<object{
     *   articulo_id:int,
     *   sku:string,
     *   descripcion:string,
     *   combinacion_id:int|null,
     *   combinacion_codigo:string,
     *   combinacion_nombre:string,
     *   color_id:int|null,
     *   color_codigo:string,
     *   color_nombre:string,
     *   talle_id:int|null,
     *   talle_codigo:string,
     *   talle_nombre:string,
     *   cantidad:float,
     *   importe:float
     * }>
     */
    public function filasAgregadas(array $filtros): array
    {
        $cantidad = self::cantidadExpr();
        $importe = self::importeExpr();
        $abiertoTalle = FacturacionLocalVentasArticulosReporteFiltros::esAbiertoPorTalle($filtros);
        $tieneColor = Schema::hasColumn('venta_emision', 'color_id');

        $select = [
            've.articulo_id',
            'a.sku',
            'a.descripcion',
            've.combinacion_id',
            DB::raw('c.codigo as combinacion_codigo'),
            DB::raw('c.nombre as combinacion_nombre'),
        ];
        $groupBy = [
            've.articulo_id',
            'a.sku',
            'a.descripcion',
            've.combinacion_id',
            'c.codigo',
            'c.nombre',
        ];

        if ($tieneColor) {
            $select[] = 've.color_id';
            $select[] = DB::raw('col.codigo as color_codigo');
            $select[] = DB::raw('col.nombre as color_nombre');
            $groupBy[] = 've.color_id';
            $groupBy[] = 'col.codigo';
            $groupBy[] = 'col.nombre';
        }

        if ($abiertoTalle) {
            $select[] = 've.talle_id';
            $select[] = DB::raw('t.codigo as talle_codigo');
            $select[] = DB::raw('t.nombre as talle_nombre');
            $groupBy[] = 've.talle_id';
            $groupBy[] = 't.codigo';
            $groupBy[] = 't.nombre';
        }

        $query = $this->queryBaseLineas($filtros, $tieneColor)
            ->select($select)
            ->selectRaw("SUM({$cantidad}) as cantidad")
            ->selectRaw("SUM({$importe}) as importe")
            ->groupBy($groupBy)
            ->havingRaw("ABS(SUM({$cantidad})) > 0.0001")
            ->orderBy('a.sku');

        if ($tieneColor) {
            $query->orderByRaw("COALESCE(c.codigo, col.codigo, '')");
        } else {
            $query->orderByRaw("COALESCE(c.codigo, '')");
        }
        if ($abiertoTalle) {
            $query->orderByRaw("COALESCE(t.codigo, t.nombre, '')");
        }

        $out = [];
        foreach ($query->get() as $row) {
            $out[] = (object) [
                'articulo_id' => (int) $row->articulo_id,
                'sku' => trim((string) $row->sku),
                'descripcion' => trim((string) $row->descripcion),
                'combinacion_id' => isset($row->combinacion_id) && $row->combinacion_id !== null
                    ? (int) $row->combinacion_id
                    : null,
                'combinacion_codigo' => trim((string) ($row->combinacion_codigo ?? '')),
                'combinacion_nombre' => trim((string) ($row->combinacion_nombre ?? '')),
                'color_id' => $tieneColor && isset($row->color_id) && $row->color_id !== null
                    ? (int) $row->color_id
                    : null,
                'color_codigo' => $tieneColor ? trim((string) ($row->color_codigo ?? '')) : '',
                'color_nombre' => $tieneColor ? trim((string) ($row->color_nombre ?? '')) : '',
                'talle_id' => $abiertoTalle && isset($row->talle_id) && $row->talle_id !== null
                    ? (int) $row->talle_id
                    : null,
                'talle_codigo' => $abiertoTalle ? trim((string) ($row->talle_codigo ?? '')) : '',
                'talle_nombre' => $abiertoTalle ? trim((string) ($row->talle_nombre ?? '')) : '',
                'cantidad' => round((float) ($row->cantidad ?? 0), 4),
                'importe' => round((float) ($row->importe ?? 0), 2),
            ];
        }

        return $out;
    }

    private function queryBaseLineas(array $filtros, bool $tieneColor): Builder
    {
        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->leftJoin('combinacion as c', 'c.id', '=', 've.combinacion_id')
            ->leftJoin('talle as t', 't.id', '=', 've.talle_id')
            ->whereNotNull('ve.articulo_id')
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('facturacion_local_emision as fle')
                    ->where(function ($w) {
                        $w->whereColumn('fle.venta_id', 'v.id')
                            ->orWhereColumn('fle.venta_nc_id', 'v.id');
                    });
            });

        $puntoventaId = (int) ($filtros['puntoventa_id'] ?? 0);
        if ($puntoventaId > 0) {
            $query->where('v.puntoventa_id', $puntoventaId);
        }

        if ($tieneColor) {
            $query->leftJoin('color as col', 'col.id', '=', 've.color_id');
        }

        [$desde, $hasta] = FacturacionLocalVentasArticulosReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($desde !== '') {
            $query->whereDate('v.fecha', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('v.fecha', '<=', $hasta);
        }

        return $query;
    }
}
