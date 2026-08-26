<?php

namespace App\Services\Sala;

use App\Models\Sala\RequisicionSalaArticuloCambio;
use Illuminate\Support\Collection;

class RequisicionSalaArticuloCambioService
{
    public function registrar(
        int $requisicionSalaId,
        int $requisicionSalaArticuloId,
        int $articuloIdAnterior,
        int $articuloIdNuevo,
        int $usuarioId,
        ?int $cumplimientoId = null,
        ?string $motivo = null
    ): RequisicionSalaArticuloCambio {
        return RequisicionSalaArticuloCambio::query()->create([
            'requisicion_sala_id' => $requisicionSalaId,
            'requisicion_sala_articulo_id' => $requisicionSalaArticuloId,
            'articulo_id_anterior' => $articuloIdAnterior,
            'articulo_id_nuevo' => $articuloIdNuevo,
            'usuario_id' => $usuarioId,
            'cumplimiento_requisicion_sala_id' => $cumplimientoId,
            'motivo' => $motivo,
        ]);
    }

    /** @return Collection<int, RequisicionSalaArticuloCambio> */
    public function listarPorRequisicion(int $requisicionSalaId): Collection
    {
        return RequisicionSalaArticuloCambio::query()
            ->with(['articuloAnterior', 'articuloNuevo', 'usuario', 'cumplimiento', 'requisicionSalaArticulo'])
            ->where('requisicion_sala_id', $requisicionSalaId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
