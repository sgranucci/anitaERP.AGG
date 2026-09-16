<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Stock\Articulo_Movimiento_Talle;
use App\Models\Ventas\Ordentrabajo_Combinacion_Talle;
use App\Models\Ventas\Pedido_Combinacion_Talle;
use Illuminate\Support\Facades\DB;

/**
 * Quita filas duplicadas en pedido_combinacion_talle (mismo ítem + mismo talle).
 * Conserva el id más alto (última grabación) y reasigna OCT/AMT si apuntaban al viejo.
 */
final class PedidoCombinacionTalleDuplicadosSupport
{
    /**
     * @return array{
     *   grupos: int,
     *   filas_a_borrar: int,
     *   detalle: list<array{pedido_combinacion_id: int, talle_id: int, conservar_id: int, borrar_ids: list<int>}>
     * }
     */
    public static function analizar(?int $pedidoCombinacionId = null): array
    {
        $query = DB::table('pedido_combinacion_talle')
            ->select('pedido_combinacion_id', 'talle_id', DB::raw('COUNT(*) as n'), DB::raw('MAX(id) as max_id'))
            ->groupBy('pedido_combinacion_id', 'talle_id')
            ->havingRaw('COUNT(*) > 1');

        if ($pedidoCombinacionId !== null && $pedidoCombinacionId > 0) {
            $query->where('pedido_combinacion_id', $pedidoCombinacionId);
        }

        $grupos = $query->get();
        $detalle = [];
        $filasABorrar = 0;

        foreach ($grupos as $grupo) {
            $ids = DB::table('pedido_combinacion_talle')
                ->where('pedido_combinacion_id', $grupo->pedido_combinacion_id)
                ->where('talle_id', $grupo->talle_id)
                ->orderByDesc('id')
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            $conservarId = (int) array_shift($ids);
            $borrarIds = $ids;
            $filasABorrar += count($borrarIds);

            $detalle[] = [
                'pedido_combinacion_id' => (int) $grupo->pedido_combinacion_id,
                'talle_id' => (int) $grupo->talle_id,
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
     * @return array{grupos: int, filas_borradas: int, oct_reasignados: int, amt_reasignados: int}
     */
    public static function ejecutar(?int $pedidoCombinacionId = null): array
    {
        $analisis = self::analizar($pedidoCombinacionId);
        $octReasignados = 0;
        $amtReasignados = 0;
        $filasBorradas = 0;

        DB::transaction(static function () use ($analisis, &$octReasignados, &$amtReasignados, &$filasBorradas): void {
            foreach ($analisis['detalle'] as $grupo) {
                $conservarId = $grupo['conservar_id'];
                $borrarIds = $grupo['borrar_ids'];
                if ($borrarIds === []) {
                    continue;
                }

                $octReasignados += Ordentrabajo_Combinacion_Talle::query()
                    ->whereIn('pedido_combinacion_talle_id', $borrarIds)
                    ->update(['pedido_combinacion_talle_id' => $conservarId]);

                $amtReasignados += Articulo_Movimiento_Talle::query()
                    ->whereIn('pedido_combinacion_talle_id', $borrarIds)
                    ->update(['pedido_combinacion_talle_id' => $conservarId]);

                $filasBorradas += Pedido_Combinacion_Talle::query()
                    ->whereIn('id', $borrarIds)
                    ->delete();
            }
        });

        return [
            'grupos' => $analisis['grupos'],
            'filas_borradas' => $filasBorradas,
            'oct_reasignados' => $octReasignados,
            'amt_reasignados' => $amtReasignados,
        ];
    }
}
