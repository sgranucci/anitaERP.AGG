<?php

namespace App\Queries\Ventas;

use App\Support\Stock\ArticuloUsoDescartableSupport;
use App\Support\Stock\ArticuloUsoInsumoSupport;
use App\Support\Ventas\GastronomiaVentasArticulosReporteFiltros;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class GastronomiaVentasArticulosReporteQuery
{
    private static function cantidadExpr(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlCantidadLineaVenta();
    }

    private static function importeExpr(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlImporteLineaVenta();
    }

    private static function tipoVentaExpr(): string
    {
        return "CASE
            WHEN cg.descuento_gastronomia_id IS NULL THEN 'externa'
            WHEN dg.tipo_consumo = 'staff' THEN 'staff'
            ELSE 'invitacion'
        END";
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<object{
     *   articulo_id:int,
     *   sku:string,
     *   descripcion:string,
     *   cant_total:float,
     *   cant_externa:float,
     *   importe_externa:float,
     *   cant_invitacion:float,
     *   cant_staff:float
     * }>
     */
    public function filasAgregadas(array $filtros): array
    {
        $tipoVenta = self::tipoVentaExpr();
        $cantidad = self::cantidadExpr();
        $importe = self::importeExpr();

        $query = $this->queryBaseLineas($filtros);

        $rows = $query
            ->select([
                've.articulo_id',
                'a.sku',
                'a.descripcion',
            ])
            ->selectRaw("SUM(ABS({$cantidad})) as cant_total")
            ->selectRaw("SUM(CASE WHEN ({$tipoVenta}) = 'externa' THEN ABS({$cantidad}) ELSE 0 END) as cant_externa")
            ->selectRaw("SUM(CASE WHEN ({$tipoVenta}) = 'externa' THEN ABS({$importe}) ELSE 0 END) as importe_externa")
            ->selectRaw("SUM(CASE WHEN ({$tipoVenta}) = 'invitacion' THEN ABS({$cantidad}) ELSE 0 END) as cant_invitacion")
            ->selectRaw("SUM(CASE WHEN ({$tipoVenta}) = 'staff' THEN ABS({$cantidad}) ELSE 0 END) as cant_staff")
            ->groupBy('ve.articulo_id', 'a.sku', 'a.descripcion')
            ->havingRaw("SUM(ABS({$cantidad})) > 0.0001")
            ->orderBy('a.sku')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = (object) [
                'articulo_id' => (int) $row->articulo_id,
                'sku' => trim((string) $row->sku),
                'descripcion' => trim((string) $row->descripcion),
                'cant_total' => round((float) ($row->cant_total ?? 0), 4),
                'cant_externa' => round((float) ($row->cant_externa ?? 0), 4),
                'importe_externa' => round((float) ($row->importe_externa ?? 0), 2),
                'cant_invitacion' => round((float) ($row->cant_invitacion ?? 0), 4),
                'cant_staff' => round((float) ($row->cant_staff ?? 0), 4),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryBaseLineas(array $filtros): Builder
    {
        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->leftJoin('cuenta_gastronomia as cg', 'cg.id', '=', 'vge.cuenta_gastronomia_id')
            ->leftJoin('descuento_gastronomia as dg', 'dg.id', '=', 'cg.descuento_gastronomia_id')
            ->whereNull('vge.venta_factura_origen_id');

        $this->aplicarExclusionInsumosYDescartables($query);

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('pv.empresa_id', $empresaId);
        }

        [$desde, $hasta] = GastronomiaVentasArticulosReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($desde !== '') {
            $query->whereDate('v.fechajornada', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('v.fechajornada', '<=', $hasta);
        }

        return $query;
    }

    private function aplicarExclusionInsumosYDescartables(Builder $query): void
    {
        $query->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('usoarticulo as ua_excl')
                ->whereColumn('ua_excl.id', 'a.usoarticulo_id')
                ->whereRaw(
                    'UPPER(TRIM(ua_excl.nombre)) IN (?, ?)',
                    [
                        ArticuloUsoInsumoSupport::NOMBRE_USO_INSUMO,
                        ArticuloUsoDescartableSupport::NOMBRE_USO_DESCARTABLES,
                    ],
                );
        });
    }
}
