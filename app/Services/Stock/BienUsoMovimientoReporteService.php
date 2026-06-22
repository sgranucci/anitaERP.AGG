<?php

namespace App\Services\Stock;

use App\Support\Stock\BienUsoAsignacionSupport;
use App\Support\Stock\BienUsoMovimientoListadoFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BienUsoMovimientoReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consultar(array $filtros, bool $paginar = true, int $porPagina = 25): LengthAwarePaginator|Collection
    {
        $query = BienUsoAsignacionSupport::queryMovimientos($filtros)
            ->orderByDesc('am.fecha')
            ->orderByDesc('am.id');

        if ($paginar) {
            return $query->paginate($porPagina);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{total_registros: int, total_asignaciones: float, total_desasignaciones: float}
     */
    public function totales(array $filtros): array
    {
        $base = BienUsoAsignacionSupport::queryMovimientos($filtros);

        $row = DB::query()
            ->fromSub($base, 'mov')
            ->selectRaw('COUNT(*) as total_registros')
            ->selectRaw('COALESCE(SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END), 0) as total_asignaciones')
            ->selectRaw('COALESCE(SUM(CASE WHEN cantidad < 0 THEN ABS(cantidad) ELSE 0 END), 0) as total_desasignaciones')
            ->first();

        return [
            'total_registros' => (int) ($row->total_registros ?? 0),
            'total_asignaciones' => (float) ($row->total_asignaciones ?? 0),
            'total_desasignaciones' => (float) ($row->total_desasignaciones ?? 0),
        ];
    }

    public function subtituloFiltros(array $filtros): string
    {
        $partes = [];

        if (! empty($filtros['bien_uso_id'])) {
            $bien = \App\Models\Contable\BienUso::query()->find((int) $filtros['bien_uso_id']);
            if ($bien) {
                $partes[] = 'Bien: '.\App\Support\Stock\TransferenciaBienUsoSupport::etiquetaBien($bien);
            }
        }

        if (! empty($filtros['fecha_desde']) || ! empty($filtros['fecha_hasta'])) {
            $partes[] = 'Fechas: '.($filtros['fecha_desde'] ?? '…').' — '.($filtros['fecha_hasta'] ?? '…');
        }

        if (! empty($filtros['efecto'])) {
            $partes[] = 'Efecto: '.(BienUsoMovimientoListadoFiltros::EFECTOS[$filtros['efecto']] ?? $filtros['efecto']);
        }

        return implode(' · ', $partes);
    }
}
