<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Ordentrabajo_Combinacion_Talle;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\DB;

/**
 * Quita filas duplicadas en ordentrabajo_combinacion_talle (mismo OT + mismo PCT).
 * Conserva el id más bajo (primera grabación) y borra el resto.
 * Origen típico: import tareas L8 que insertaba OCT con id de L8 distinto al de L12.
 */
final class OrdentrabajoCombinacionTalleDuplicadosSupport
{
    /**
     * @return array{
     *   grupos: int,
     *   filas_a_borrar: int,
     *   detalle: list<array{ordentrabajo_id: int, pedido_combinacion_talle_id: int, conservar_id: int, borrar_ids: list<int>}>
     * }
     */
    public static function analizar(?int $ordentrabajoId = null): array
    {
        $query = DB::table('ordentrabajo_combinacion_talle')
            ->select(
                'ordentrabajo_id',
                'pedido_combinacion_talle_id',
                DB::raw('COUNT(*) as n'),
                DB::raw('MIN(id) as min_id')
            )
            ->groupBy('ordentrabajo_id', 'pedido_combinacion_talle_id')
            ->havingRaw('COUNT(*) > 1');

        if ($ordentrabajoId !== null && $ordentrabajoId > 0) {
            $query->where('ordentrabajo_id', $ordentrabajoId);
        }

        $grupos = $query->get();
        $detalle = [];
        $filasABorrar = 0;

        foreach ($grupos as $grupo) {
            $ids = DB::table('ordentrabajo_combinacion_talle')
                ->where('ordentrabajo_id', $grupo->ordentrabajo_id)
                ->where('pedido_combinacion_talle_id', $grupo->pedido_combinacion_talle_id)
                ->orderBy('id')
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            $conservarId = (int) array_shift($ids);
            $borrarIds = $ids;
            $filasABorrar += count($borrarIds);

            $detalle[] = [
                'ordentrabajo_id' => (int) $grupo->ordentrabajo_id,
                'pedido_combinacion_talle_id' => (int) $grupo->pedido_combinacion_talle_id,
                'conservar_id' => $conservarId,
                'borrar_ids' => $borrarIds,
            ];
        }

        return [
            'grupos' => count($detalle),
            'filas_a_borrar' => $filasABorrar,
            'detalle' => $detalle,
        ];
    }

    /**
     * @return array{grupos: int, filas_borradas: int}
     */
    public static function ejecutar(?int $ordentrabajoId = null): array
    {
        $analisis = self::analizar($ordentrabajoId);
        $filasBorradas = 0;

        DB::transaction(static function () use ($analisis, &$filasBorradas): void {
            foreach ($analisis['detalle'] as $grupo) {
                $borrarIds = $grupo['borrar_ids'];
                if ($borrarIds === []) {
                    continue;
                }

                $filasBorradas += EloquentAuditDeleteSupport::each(
                    Ordentrabajo_Combinacion_Talle::query()->whereIn('id', $borrarIds)
                );
            }
        });

        return [
            'grupos' => $analisis['grupos'],
            'filas_borradas' => $filasBorradas,
        ];
    }
}
