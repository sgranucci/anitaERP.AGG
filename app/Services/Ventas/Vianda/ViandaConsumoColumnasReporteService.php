<?php

namespace App\Services\Ventas\Vianda;

use App\Models\Ventas\ViandaConsumoLinea;
use App\Support\Ventas\GastronomiaDescuentoReporteTipoArticuloSupport;
use App\Support\Ventas\Vianda\ViandaEmpresaSupport;
use App\Support\Ventas\Vianda\ViandaPrecioSupport;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

/**
 * Armado de la vista consolidada del reporte de viandas: artículos en filas
 * y un grupo de columnas (unidades / costo / venta) por cada centro de costo.
 *
 * Costo unitario/total: catálogo lista 5000+mes vigente a fecha_hasta del filtro
 * (mismo criterio que el reporte de descuentos gastronomía). No promedia el costo
 * histórico grabado al marchar, que puede variar durante el mes.
 */
class ViandaConsumoColumnasReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   vista_columnas:array{columnas:list<array<string,mixed>>,filas:list<array<string,mixed>>,grupos:list<array<string,mixed>>,totales_por_columna:list<array<string,mixed>>}|null,
     *   gran_total_unidades:float,
     *   gran_total_costo:float,
     *   gran_total_venta:float
     * }
     */
    public function generar(array $filtros): array
    {
        $agregados = $this->consultarAgregados($filtros);
        if ($agregados === []) {
            return [
                'vista_columnas' => null,
                'gran_total_unidades' => 0.0,
                'gran_total_costo' => 0.0,
                'gran_total_venta' => 0.0,
            ];
        }

        $fechaCosto = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($fechaCosto === '') {
            $fechaCosto = date('Y-m-d');
        }

        /** @var array<int, float> $cacheCosto */
        $cacheCosto = [];

        $columnasIndex = [];
        $articulos = [];
        $totalesPorClave = [];
        $granUnidades = 0.0;
        $granCosto = 0.0;
        $granVenta = 0.0;

        foreach ($agregados as $row) {
            $ccId = (int) ($row->centrocosto_id ?? 0);
            $clave = $ccId > 0 ? 'cc_'.$ccId : 'cc_sin';
            $codigoCc = trim((string) ($row->centrocosto_codigo ?? ''));
            $nombreCc = trim((string) ($row->centrocosto_nombre ?? ''));
            if ($nombreCc === '') {
                $nombreCc = $ccId > 0 ? 'C.C. '.$ccId : 'Sin centro de costo';
            }
            $titulo = $codigoCc !== ''
                ? $codigoCc.' — '.$nombreCc
                : $nombreCc;

            if (! isset($columnasIndex[$clave])) {
                $columnasIndex[$clave] = [
                    'clave' => $clave,
                    'codigo' => $codigoCc !== '' ? $codigoCc : (string) ($ccId > 0 ? $ccId : ''),
                    'nombre' => $nombreCc,
                    'titulo' => $titulo,
                    'orden' => $nombreCc,
                ];
                $totalesPorClave[$clave] = [
                    'unidades' => 0.0,
                    'costo_total' => 0.0,
                    'total_venta' => 0.0,
                ];
            }

            $articuloId = (int) ($row->articulo_id ?? 0);
            $sku = trim((string) ($row->sku ?? ''));
            $claveArt = $articuloId > 0 ? 'a_'.$articuloId : 's_'.mb_strtolower($sku);
            if ($claveArt === 's_') {
                $claveArt = 'x_'.md5((string) ($row->descripcion ?? '').'|'.$sku);
            }

            $unidades = round((float) ($row->unidades ?? 0), 4);
            $totalVenta = round((float) ($row->total_venta ?? 0), 2);
            $precioVenta = $unidades > 0.0001
                ? round($totalVenta / $unidades, 2)
                : round((float) ($row->precio_venta ?? 0), 2);

            $costoUnit = $this->costoCatalogoUnitario($articuloId, $fechaCosto, $cacheCosto);
            if ($costoUnit <= 0 && $unidades > 0.0001) {
                // Fallback si aún no hay precio en lista 5000+mes: promedio de lo grabado al marchar.
                $costoUnit = round((float) ($row->costo_total ?? 0) / $unidades, 2);
            }
            $costoTotal = round($unidades * $costoUnit, 2);

            if (! isset($articulos[$claveArt])) {
                $tipoId = (int) ($row->tipoarticulo_id ?? 0);
                $articulos[$claveArt] = [
                    'articulo_id' => $articuloId,
                    'sku' => $sku !== '' ? $sku : '—',
                    'descripcion' => trim((string) ($row->descripcion ?? '')) ?: '—',
                    'tipoarticulo_id' => $tipoId > 0 ? $tipoId : null,
                    'tipoarticulo_nombre' => trim((string) ($row->tipoarticulo_nombre ?? '')),
                    'costo_unitario' => $costoUnit,
                    'precio_venta' => $precioVenta,
                    'celdas' => [],
                ];
            } else {
                if ($costoUnit > 0) {
                    $articulos[$claveArt]['costo_unitario'] = $costoUnit;
                }
                if ((float) ($articulos[$claveArt]['precio_venta'] ?? 0) <= 0 && $precioVenta > 0) {
                    $articulos[$claveArt]['precio_venta'] = $precioVenta;
                }
            }

            $articulos[$claveArt]['celdas'][$clave] = [
                'unidades' => $unidades,
                'costo_total' => $costoTotal,
                'total_venta' => $totalVenta,
                'costo_unitario' => $costoUnit,
                'precio_venta' => $precioVenta,
            ];

            $totalesPorClave[$clave]['unidades'] = round($totalesPorClave[$clave]['unidades'] + $unidades, 4);
            $totalesPorClave[$clave]['costo_total'] = round($totalesPorClave[$clave]['costo_total'] + $costoTotal, 2);
            $totalesPorClave[$clave]['total_venta'] = round($totalesPorClave[$clave]['total_venta'] + $totalVenta, 2);

            $granUnidades += $unidades;
            $granCosto += $costoTotal;
            $granVenta += $totalVenta;
        }

        $columnas = array_values($columnasIndex);
        usort($columnas, static function (array $a, array $b): int {
            $sinA = ($a['clave'] ?? '') === 'cc_sin' ? 1 : 0;
            $sinB = ($b['clave'] ?? '') === 'cc_sin' ? 1 : 0;
            if ($sinA !== $sinB) {
                return $sinA <=> $sinB;
            }

            return strcmp(mb_strtolower((string) ($a['orden'] ?? '')), mb_strtolower((string) ($b['orden'] ?? '')));
        });

        $totalesPorColumna = [];
        foreach ($columnas as $col) {
            $clave = (string) ($col['clave'] ?? '');
            $totalesPorColumna[] = [
                'clave' => $clave,
                'totales' => $totalesPorClave[$clave] ?? [
                    'unidades' => 0.0,
                    'costo_total' => 0.0,
                    'total_venta' => 0.0,
                ],
            ];
        }

        $agrupado = GastronomiaDescuentoReporteTipoArticuloSupport::agruparFilas(array_values($articulos));

        return [
            'vista_columnas' => [
                'columnas' => $columnas,
                'filas' => $agrupado['filas'],
                'grupos' => $agrupado['grupos'],
                'totales_por_columna' => $totalesPorColumna,
            ],
            'gran_total_unidades' => round($granUnidades, 4),
            'gran_total_costo' => round($granCosto, 2),
            'gran_total_venta' => round($granVenta, 2),
        ];
    }

    /**
     * @param  array<int, float>  $cache
     */
    private function costoCatalogoUnitario(int $articuloId, string $fechaCosto, array &$cache): float
    {
        if ($articuloId <= 0) {
            return 0.0;
        }
        if (array_key_exists($articuloId, $cache)) {
            return $cache[$articuloId];
        }

        $cache[$articuloId] = round(
            ViandaPrecioSupport::precioCostoUnitario($articuloId, $fechaCosto),
            2,
        );

        return $cache[$articuloId];
    }

    /**
     * @param  list<mixed>  $items
     */
    public function paginarItems(array $items, int $perPage, int $page = 1, string $pageName = 'page'): PaginatorImpl
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);

        return new PaginatorImpl(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<object>
     */
    private function consultarAgregados(array $filtros): array
    {
        $query = ViandaConsumoLinea::query()
            ->from('vianda_consumo_linea as l')
            ->join('vianda_consumo as vc', 'vc.id', '=', 'l.vianda_consumo_id')
            ->leftJoin('centrocosto as cc', 'cc.id', '=', 'vc.centrocosto_id')
            ->leftJoin('articulo as art', 'art.id', '=', 'l.articulo_id');

        $this->aplicarFiltrosConsumo($query, $filtros);

        $rows = $query
            ->select([
                'l.articulo_id',
                DB::raw('MAX(l.sku) as sku'),
                DB::raw('MAX(l.descripcion) as descripcion'),
                DB::raw('MAX(COALESCE(l.tipoarticulo_nombre, "")) as tipoarticulo_nombre'),
                DB::raw('MAX(art.tipoarticulo_id) as tipoarticulo_id'),
                'vc.centrocosto_id',
                DB::raw('MAX(cc.codigo) as centrocosto_codigo'),
                DB::raw('MAX(cc.nombre) as centrocosto_nombre'),
                DB::raw('SUM(l.cantidad) as unidades'),
                DB::raw('SUM(l.cantidad * l.precio_costo_unitario) as costo_total'),
                DB::raw('SUM(l.cantidad * l.precio_venta_unitario) as total_venta'),
                DB::raw('MAX(l.precio_costo_unitario) as costo_unitario'),
                DB::raw('MAX(l.precio_venta_unitario) as precio_venta'),
            ])
            ->groupBy('l.articulo_id', 'vc.centrocosto_id')
            ->orderBy('centrocosto_nombre')
            ->orderBy('sku')
            ->get()
            ->all();

        return $rows;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Ventas\ViandaConsumoLinea>|\Illuminate\Database\Query\Builder  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosConsumo($query, array $filtros): void
    {
        ViandaEmpresaSupport::aplicarFiltroAsignadas($query, 'vc.empresa_id');

        $query->whereDate('vc.fecha', '>=', $filtros['fecha_desde'])
            ->whereDate('vc.fecha', '<=', $filtros['fecha_hasta']);

        if (! empty($filtros['empresa_id'])) {
            $query->where('vc.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['centrocosto_id'])) {
            $query->where('vc.centrocosto_id', (int) $filtros['centrocosto_id']);
        }
        if (($filtros['estado'] ?? 'A') !== 'TODOS') {
            $query->where('vc.estado', $filtros['estado']);
        }
        $texto = trim((string) ($filtros['texto'] ?? ''));
        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('vc.login_usuario', 'like', '%'.$texto.'%')
                    ->orWhere('vc.nombre_usuario', 'like', '%'.$texto.'%')
                    ->orWhere('vc.codigo_retiro', 'like', '%'.$texto.'%');
            });
        }
    }
}
