<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo_ParteUnica;
use App\Support\Stock\ArticuloParteUnicaEstados;
use App\Support\Stock\ParteUnicaBajaReporteFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ParteUnicaBajaReporteService
{
    /**
     * @return LengthAwarePaginator<int, Articulo_ParteUnica>|Collection<int, Articulo_ParteUnica>
     */
    public function consultar(array $filtros, bool $paginar = true, int $porPagina = 25)
    {
        $query = $this->queryBase($filtros);

        if ($paginar) {
            return $query->paginate($porPagina);
        }

        return $query->get();
    }

    /** @return array{total_registros: int, total_baja: int, total_activos: int} */
    public function totales(array $filtros): array
    {
        $base = $this->queryBase($filtros, false);
        $total = (clone $base)->count();
        $baja = (clone $base)->where('articulo_parte_unica.estado', ArticuloParteUnicaEstados::BAJA)->count();
        $activos = (clone $base)->where('articulo_parte_unica.estado', ArticuloParteUnicaEstados::ACTIVO)->count();

        return [
            'total_registros' => $total,
            'total_baja' => $baja,
            'total_activos' => $activos,
        ];
    }

    public function subtituloFiltros(array $filtros): string
    {
        $partes = [];
        if (! empty($filtros['numeroparte'])) {
            $partes[] = 'NPU: '.$filtros['numeroparte'];
        }
        if (! empty($filtros['sku'])) {
            $partes[] = 'SKU: '.$filtros['sku'];
        }
        if (! empty($filtros['fecha_desde']) || ! empty($filtros['fecha_hasta'])) {
            $partes[] = 'Fecha baja: '.($filtros['fecha_desde'] ?? '…').' — '.($filtros['fecha_hasta'] ?? '…');
        }
        $partes[] = 'Estado: '.(ParteUnicaBajaReporteFiltros::ESTADOS[$filtros['estado'] ?? 'B'] ?? '');

        return implode(' · ', $partes);
    }

    /**
     * @return Builder<Articulo_ParteUnica>
     */
    private function queryBase(array $filtros, bool $aplicarEstadoDefault = true): Builder
    {
        $query = Articulo_ParteUnica::query()
            ->select('articulo_parte_unica.*')
            ->with(['articulos', 'movimientostock'])
            ->join('articulo as a', 'a.id', '=', 'articulo_parte_unica.articulo_id');

        $estado = (string) ($filtros['estado'] ?? 'B');
        if ($estado === 'T') {
            // sin filtro estado
        } elseif ($estado === ArticuloParteUnicaEstados::ACTIVO) {
            $query->where('articulo_parte_unica.estado', ArticuloParteUnicaEstados::ACTIVO);
        } elseif ($aplicarEstadoDefault) {
            $query->where('articulo_parte_unica.estado', ArticuloParteUnicaEstados::BAJA);
        }

        if (! empty($filtros['numeroparte'])) {
            $query->where('articulo_parte_unica.numeroparte', (int) $filtros['numeroparte']);
        }

        if (! empty($filtros['articulo_id'])) {
            $query->where('articulo_parte_unica.articulo_id', (int) $filtros['articulo_id']);
        }

        $sku = trim((string) ($filtros['sku'] ?? ''));
        if ($sku !== '') {
            $query->where('a.sku', 'like', '%'.addcslashes($sku, '%_\\').'%');
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('articulo_parte_unica.fecha_baja', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('articulo_parte_unica.fecha_baja', '<=', $filtros['fecha_hasta']);
        }

        return $query->orderByDesc('articulo_parte_unica.fecha_baja')
            ->orderByDesc('articulo_parte_unica.numeroparte');
    }
}
