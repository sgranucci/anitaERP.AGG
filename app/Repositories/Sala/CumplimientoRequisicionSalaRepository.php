<?php

namespace App\Repositories\Sala;

use App\Models\Sala\CumplimientoRequisicionSala;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CumplimientoRequisicionSalaRepository implements CumplimientoRequisicionSalaRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function leeCumplimientos($filtros, bool $paginar = true): LengthAwarePaginator|\Illuminate\Support\Collection
    {
        $query = $this->queryBase();

        if (is_array($filtros)) {
            \App\Support\Sala\CumplimientoRequisicionSalaListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('cumplimiento_requisicion_sala.id');

        if ($paginar) {
            return $query->paginate(10);
        }

        return $query->get();
    }

    public function findConDetalle(int $id): ?CumplimientoRequisicionSala
    {
        return CumplimientoRequisicionSala::query()
            ->with([
                'usuario',
                'empresa',
                'revertidoPor',
                'articulos.articulo',
                'articulos.depositoOrigen',
                'articulos.tecnicoLaboratorio',
                'articulos.requisicionSala.depositos',
                'articulos.requisicionSala.centrocostos',
                'articulos.requisicionSala.empresas',
                'transferencias.transferenciaMercaderia.depositoOrigen',
                'transferencias.transferenciaMercaderia.depositoDestino',
            ])
            ->find($id);
    }

    /** @return list<CumplimientoRequisicionSala> */
    public function listarPorRequisicion(int $requisicionSalaId): array
    {
        return CumplimientoRequisicionSala::query()
            ->with(['usuario'])
            ->whereHas('articulos', fn ($q) => $q->where('requisicion_sala_id', $requisicionSalaId))
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function siguienteNumero(): int
    {
        return (int) (CumplimientoRequisicionSala::query()->max('numero') ?? 0) + 1;
    }

    private function queryBase(): Builder
    {
        return CumplimientoRequisicionSala::query()
            ->select('cumplimiento_requisicion_sala.*')
            ->with(['usuario', 'empresa'])
            ->withCount('articulos');
    }
}
