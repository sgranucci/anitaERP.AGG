<?php

namespace App\Services\Compras;

use App\Models\Compras\RequisicionArticuloCambio;
use Illuminate\Support\Collection;

class RequisicionArticuloCambioService
{
    public function registrar(
        int $requisicionId,
        int $requisicionArticuloId,
        int $articuloIdAnterior,
        int $articuloIdNuevo,
        int $usuarioId,
        ?int $cumplimientoId = null,
        ?string $motivo = null
    ): RequisicionArticuloCambio {
        return RequisicionArticuloCambio::query()->create([
            'requisicion_id' => $requisicionId,
            'requisicion_articulo_id' => $requisicionArticuloId,
            'articulo_id_anterior' => $articuloIdAnterior,
            'articulo_id_nuevo' => $articuloIdNuevo,
            'usuario_id' => $usuarioId,
            'cumplimiento_requisicion_compra_id' => $cumplimientoId,
            'motivo' => $motivo,
        ]);
    }

    /** @return Collection<int, RequisicionArticuloCambio> */
    public function listarPorRequisicion(int $requisicionId): Collection
    {
        return RequisicionArticuloCambio::query()
            ->with(['articuloAnterior', 'articuloNuevo', 'usuario', 'cumplimiento', 'requisicionArticulo'])
            ->where('requisicion_id', $requisicionId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /** @return Collection<int, RequisicionArticuloCambio> */
    public function listarPorCumplimiento(int $cumplimientoId): Collection
    {
        return RequisicionArticuloCambio::query()
            ->with(['articuloAnterior', 'articuloNuevo', 'usuario', 'cumplimiento'])
            ->where('cumplimiento_requisicion_compra_id', $cumplimientoId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
