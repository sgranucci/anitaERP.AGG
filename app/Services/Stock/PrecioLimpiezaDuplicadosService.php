<?php

namespace App\Services\Stock;

use Illuminate\Support\Facades\DB;

class PrecioLimpiezaDuplicadosService
{
    /**
     * @return array{grupos_duplicados: int, registros_a_eliminar: int}
     */
    public function resumenDuplicados(): array
    {
        $row = DB::selectOne('
            SELECT
                COUNT(*) AS grupos_duplicados,
                COALESCE(SUM(cnt - 1), 0) AS registros_a_eliminar
            FROM (
                SELECT COUNT(*) AS cnt
                FROM precio
                GROUP BY articulo_id, listaprecio_id, fechavigencia
                HAVING COUNT(*) > 1
            ) AS duplicados
        ');

        return [
            'grupos_duplicados' => (int) ($row->grupos_duplicados ?? 0),
            'registros_a_eliminar' => (int) ($row->registros_a_eliminar ?? 0),
        ];
    }

    /**
     * Elimina filas duplicadas (mismo articulo_id + listaprecio_id + fechavigencia).
     * Conserva la de mayor id (última grabada).
     *
     * @return array{grupos_duplicados: int, eliminados: int}
     */
    public function eliminarDuplicados(int $chunkSize = 1000): array
    {
        $chunkSize = max(1, $chunkSize);
        $resumen = $this->resumenDuplicados();
        $eliminados = 0;

        while (true) {
            $ids = DB::table('precio as p1')
                ->joinSub(
                    DB::table('precio')
                        ->selectRaw('articulo_id, listaprecio_id, fechavigencia, MAX(id) AS keep_id')
                        ->groupBy('articulo_id', 'listaprecio_id', 'fechavigencia')
                        ->havingRaw('COUNT(*) > 1'),
                    'g',
                    function ($join) {
                        $join->on('g.articulo_id', '=', 'p1.articulo_id')
                            ->on('g.listaprecio_id', '=', 'p1.listaprecio_id')
                            ->on('g.fechavigencia', '=', 'p1.fechavigencia');
                    }
                )
                ->whereColumn('p1.id', '<', 'g.keep_id')
                ->limit($chunkSize)
                ->pluck('p1.id')
                ->all();

            if ($ids === []) {
                break;
            }

            $eliminados += DB::table('precio')->whereIn('id', $ids)->delete();
        }

        return [
            'grupos_duplicados' => $resumen['grupos_duplicados'],
            'eliminados' => $eliminados,
        ];
    }
}
