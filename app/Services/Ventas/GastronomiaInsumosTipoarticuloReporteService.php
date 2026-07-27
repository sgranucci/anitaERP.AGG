<?php

namespace App\Services\Ventas;

use App\Queries\Ventas\GastronomiaInsumosTipoarticuloReporteQuery;
use App\Support\Ventas\GastronomiaInsumosTipoarticuloReporteFiltros;
use Illuminate\Pagination\LengthAwarePaginator;

class GastronomiaInsumosTipoarticuloReporteService
{
    public function __construct(
        private readonly GastronomiaInsumosTipoarticuloReporteQuery $query,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   columnas_dias: list<array{ymd: string, label: string}>,
     *   filas: list<array<string, mixed>>,
     *   totales_por_dia: array<string, float>,
     *   total_general: float,
     *   cantidad_articulos: int,
     *   unidad_medida_etiqueta: string
     * }
     */
    public function generar(array $filtros): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $tipoarticuloId = (int) ($filtros['tipoarticulo_id'] ?? 0);
        [$desde, $hasta] = GastronomiaInsumosTipoarticuloReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        $columnasDias = GastronomiaInsumosTipoarticuloReporteFiltros::columnasDias($desde, $hasta);
        $ventas = $this->query->cantidadesPorArticuloDia($filtros);

        $mapVentas = [];
        foreach ($ventas as $row) {
            $articuloId = (int) $row->articulo_id;
            $dia = (string) $row->dia;
            $mapVentas[$articuloId][$dia] = round((float) ($row->cantidad ?? 0), 4);
        }

        $catalogo = $this->query->articulosCatalogo($tipoarticuloId);

        if ($catalogo->isEmpty() && $ventas->isNotEmpty()) {
            $catalogo = $ventas->map(fn ($row) => (object) [
                'id' => (int) $row->articulo_id,
                'sku' => (string) $row->sku,
                'descripcion' => (string) $row->descripcion,
            ])->unique('id')->values();
        }

        $catalogoPorId = $catalogo->keyBy('id');

        $filas = [];
        $totalesPorDia = [];
        foreach ($columnasDias as $col) {
            $totalesPorDia[$col['ymd']] = 0.;
        }
        $totalGeneral = 0.;

        $articuloIds = array_keys($mapVentas);
        sort($articuloIds, SORT_NUMERIC);

        foreach ($articuloIds as $articuloId) {
            $articuloId = (int) $articuloId;
            $articulo = $catalogoPorId->get($articuloId);
            if ($articulo === null) {
                $ventaRow = $ventas->firstWhere('articulo_id', $articuloId);
                if ($ventaRow === null) {
                    continue;
                }
                $articulo = (object) [
                    'id' => $articuloId,
                    'sku' => (string) $ventaRow->sku,
                    'descripcion' => (string) $ventaRow->descripcion,
                ];
            }

            $cantidadesPorDia = [];
            $totalFila = 0.;

            foreach ($columnasDias as $col) {
                $cant = round((float) ($mapVentas[$articuloId][$col['ymd']] ?? 0), 4);
                $cantidadesPorDia[$col['ymd']] = $cant;
                $totalesPorDia[$col['ymd']] = round($totalesPorDia[$col['ymd']] + $cant, 4);
                $totalFila = round($totalFila + $cant, 4);
            }

            if ($totalFila == 0.) {
                continue;
            }

            $filas[] = [
                'articulo_id' => $articuloId,
                'sku' => trim((string) $articulo->sku),
                'descripcion' => trim((string) $articulo->descripcion),
                'cantidades_por_dia' => $cantidadesPorDia,
                'total' => $totalFila,
            ];
            $totalGeneral = round($totalGeneral + $totalFila, 4);
        }

        usort($filas, fn ($a, $b) => strcmp((string) $a['sku'], (string) $b['sku']));

        $articuloIdsConVenta = array_map(
            static fn (array $fila): int => (int) ($fila['articulo_id'] ?? 0),
            $filas,
        );

        return [
            'columnas_dias' => $columnasDias,
            'filas' => $filas,
            'totales_por_dia' => $totalesPorDia,
            'total_general' => $totalGeneral,
            'cantidad_articulos' => count($filas),
            'unidad_medida_etiqueta' => $this->query->etiquetaUnidadMedida($articuloIdsConVenta),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);
        $total = count($filas);
        $offset = ($page - 1) * $perPage;
        $items = array_slice($filas, $offset, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }
}
