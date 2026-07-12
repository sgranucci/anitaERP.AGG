<?php

namespace App\Repositories\Compras;

use App\Models\Compras\CumplimientoRequisicionCompra;
use App\Support\Compras\CumplimientoRequisicionCompraListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CumplimientoRequisicionCompraRepository implements CumplimientoRequisicionCompraRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator|Collection
     */
    public function leeCumplimientos($filtros, bool $paginar = true)
    {
        $query = $this->queryBase();

        if (is_array($filtros)) {
            CumplimientoRequisicionCompraListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('cumplimiento_requisicion_compra.id');

        if ($paginar) {
            return $query->paginate(10);
        }

        return $query->get();
    }

    public function findConDetalle(int $id): ?CumplimientoRequisicionCompra
    {
        return CumplimientoRequisicionCompra::query()
            ->with([
                'usuario',
                'empresa',
                'revertidoPor',
                'articulos.articulo',
                'articulos.articuloOriginal',
                'articulos.depositoOrigen',
                'articulos.depositoDestino',
                'articulos.requisicion.empresas',
                'articulos.requisicion.centrocostos',
                'articulos.requisicion.usuarios',
                'transferencias.transferenciaMercaderia.depositoOrigen',
                'transferencias.transferenciaMercaderia.depositoDestino',
            ])
            ->find($id);
    }

    /** @return list<CumplimientoRequisicionCompra> */
    public function listarPorRequisicion(int $requisicionId): array
    {
        return CumplimientoRequisicionCompra::query()
            ->with(['usuario', 'transferencias.transferenciaMercaderia'])
            ->whereHas('articulos', fn ($q) => $q->where('requisicion_id', $requisicionId))
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    public function siguienteNumero(): int
    {
        return (int) (CumplimientoRequisicionCompra::query()->max('numero') ?? 0) + 1;
    }

    private function queryBase(): Builder
    {
        return CumplimientoRequisicionCompra::query()
            ->select('cumplimiento_requisicion_compra.*')
            ->with([
                'usuario',
                'empresa',
                'articulos:id,cumplimiento_requisicion_compra_id,requisicion_id',
                'articulos.requisicion:id,numerorequisicion',
            ])
            ->withCount('articulos');
    }
}
