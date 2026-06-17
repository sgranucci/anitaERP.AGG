<?php

namespace App\Services\Ventas;

use App\Queries\Ventas\PedidoQueryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class KiloCategoriaReporteService
{
    public function __construct(
        private readonly PedidoQueryInterface $pedidoQuery,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function generarDatos(array $filtros): Collection
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->pedidoQuery->findPorKiloCategoriaFiltros($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function aplanarFilas(Collection $datos, array $filtros): array
    {
        $filas = [];
        $categoriaActual = null;
        $nombreCategoriaActual = '';
        $categoriaIdActual = 0;
        $subtotal = self::totalesVacios();
        $totalFinal = self::totalesVacios();

        foreach ($datos as $row) {
            $codigoCategoria = (string) ($row->codigocategoria ?? '');

            if ($codigoCategoria !== $categoriaActual && $categoriaActual !== null) {
                $filas[] = self::filaSubtotalCategoria(
                    (string) $categoriaActual,
                    $nombreCategoriaActual,
                    $categoriaIdActual,
                    $subtotal,
                );
                $subtotal = self::totalesVacios();
            }

            if ($codigoCategoria !== $categoriaActual) {
                $categoriaActual = $codigoCategoria;
                $nombreCategoriaActual = (string) ($row->nombrecategoria ?? '');
                $categoriaIdActual = (int) ($row->categoria_id ?? 0);
            }

            $filas[] = self::filaDetalle($row);
            self::acumularTotales($subtotal, $row);
            self::acumularTotales($totalFinal, $row);
        }

        if ($categoriaActual !== null) {
            $filas[] = self::filaSubtotalCategoria(
                (string) $categoriaActual,
                $nombreCategoriaActual,
                $categoriaIdActual,
                $subtotal,
            );
        }

        if ($datos->isNotEmpty()) {
            $filas[] = self::filaTotalFinal($totalFinal);
        }

        return $filas;
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

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{total_pieza: float, total_kilo: float, total_caja: float, cantidad_detalle: int}
     */
    public function totalesGenerales(array $filas): array
    {
        $totales = self::totalesVacios();
        $cantidadDetalle = 0;

        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? '') !== 'detalle') {
                continue;
            }
            $cantidadDetalle++;
            $totales['total_pieza'] += (float) ($fila['total_pieza'] ?? 0);
            $totales['total_kilo'] += (float) ($fila['total_kilo'] ?? 0);
            $totales['total_caja'] += (float) ($fila['total_caja'] ?? 0);
        }

        $totales['cantidad_detalle'] = $cantidadDetalle;

        return $totales;
    }

    /**
     * @return array{total_pieza: float, total_kilo: float, total_caja: float}
     */
    private static function totalesVacios(): array
    {
        return [
            'total_pieza' => 0.0,
            'total_kilo' => 0.0,
            'total_caja' => 0.0,
        ];
    }

    private static function acumularTotales(array &$totales, object $row): void
    {
        $totales['total_pieza'] += (float) ($row->total_pieza ?? 0);
        $totales['total_kilo'] += (float) ($row->total_kilo ?? 0);
        $totales['total_caja'] += (float) ($row->total_caja ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function filaDetalle(object $row): array
    {
        return [
            'tipo_fila' => 'detalle',
            'categoria_id' => (int) ($row->categoria_id ?? 0),
            'codigocategoria' => (string) ($row->codigocategoria ?? ''),
            'nombrecategoria' => (string) ($row->nombrecategoria ?? ''),
            'articulo_id' => (int) ($row->articulo_id ?? 0),
            'sku' => (string) ($row->sku ?? ''),
            'descripcion' => (string) ($row->descripcion ?? ''),
            'total_pieza' => (float) ($row->total_pieza ?? 0),
            'total_kilo' => (float) ($row->total_kilo ?? 0),
            'total_caja' => (float) ($row->total_caja ?? 0),
        ];
    }

    /**
     * @param  array{total_pieza: float, total_kilo: float, total_caja: float}  $subtotal
     * @return array<string, mixed>
     */
    private static function filaSubtotalCategoria(
        string $codigoCategoria,
        string $nombreCategoria,
        int $categoriaId,
        array $subtotal,
    ): array {
        return array_merge($subtotal, [
            'tipo_fila' => 'subtotal_categoria',
            'codigocategoria' => $codigoCategoria,
            'nombrecategoria' => $nombreCategoria,
            'categoria_id' => $categoriaId,
        ]);
    }

    /**
     * @param  array{total_pieza: float, total_kilo: float, total_caja: float}  $totalFinal
     * @return array<string, mixed>
     */
    private static function filaTotalFinal(array $totalFinal): array
    {
        return array_merge($totalFinal, [
            'tipo_fila' => 'total_final',
        ]);
    }
}
