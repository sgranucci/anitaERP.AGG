<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Support\Contable\CcVsMayorAnita\CcVsMayorAnitaListadoFiltros;
use App\Support\Contable\CcVsMayorAnita\CcVsMayorAnitaProcesador;
use Illuminate\Pagination\LengthAwarePaginator;

final class CcVsMayorAnitaReporteService
{
    public function __construct(
        private readonly CcVsMayorAnitaProcesador $procesador = new CcVsMayorAnitaProcesador(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarDesdeFiltros(array $filtros): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        return $this->procesador->generar($filtros);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);
        $total = count($filas);
        $items = array_slice($filas, ($page - 1) * $perPage, $perPage);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function titulo(array $filtros): string
    {
        $fecha = (string) ($filtros['fecha'] ?? '');
        $cuenta = (int) ($filtros['cuenta_codigo'] ?? 0);
        $fechaFmt = $fecha !== '' ? date('d/m/Y', strtotime($fecha)) : '';

        return 'Control CC vs mayor Anita · cuenta '.$cuenta.($fechaFmt !== '' ? ' · '.$fechaFmt : '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firmaCache(array $filtros): string
    {
        return CcVsMayorAnitaListadoFiltros::firma($filtros);
    }
}
