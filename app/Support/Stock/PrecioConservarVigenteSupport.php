<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;

/**
 * Deja una sola fila por articulo_id + listaprecio_id: la de fechavigencia más reciente (desempate id).
 */
final class PrecioConservarVigenteSupport
{
    /**
     * @param  list<array{articulo_id: int, listaprecio_id: int}>|null  $pares  null = toda la tabla precio
     * @return array{pares_con_duplicado: int, eliminados: int}
     */
    public function conservarSoloVigente(?array $pares = null, int $chunkSize = 1000): array
    {
        $chunkSize = max(1, $chunkSize);
        $resumen = $this->resumenObsoletos($pares);
        $eliminados = 0;

        while (true) {
            $query = DB::table('precio as p1')
                ->joinSub($this->subqueryKeeper($pares), 'k', function ($join) {
                    $join->on('k.articulo_id', '=', 'p1.articulo_id')
                        ->on('k.listaprecio_id', '=', 'p1.listaprecio_id');
                })
                ->whereColumn('p1.id', '<>', 'k.keep_id');

            if ($pares !== null && $pares !== []) {
                $query->where(function ($q) use ($pares) {
                    foreach ($pares as $par) {
                        $aid = (int) ($par['articulo_id'] ?? 0);
                        $lid = (int) ($par['listaprecio_id'] ?? 0);
                        if ($aid > 0 && $lid > 0) {
                            $q->orWhere(function ($q2) use ($aid, $lid) {
                                $q2->where('p1.articulo_id', $aid)->where('p1.listaprecio_id', $lid);
                            });
                        }
                    }
                });
            }

            $ids = $query->limit($chunkSize)->pluck('p1.id')->all();
            if ($ids === []) {
                break;
            }

            $eliminados += DB::table('precio')->whereIn('id', $ids)->delete();
        }

        return [
            'pares_con_duplicado' => $resumen['pares_con_duplicado'],
            'eliminados' => $eliminados,
        ];
    }

    /**
     * @param  list<array{articulo_id: int, listaprecio_id: int}>|null  $pares
     * @return array{pares_con_duplicado: int, filas_a_eliminar: int}
     */
    public function resumenObsoletos(?array $pares = null): array
    {
        $row = DB::query()->fromSub($this->subqueryConteoObsoletos($pares), 'x')->selectRaw('
            COUNT(*) AS pares_con_duplicado,
            COALESCE(SUM(obsoletos), 0) AS filas_a_eliminar
        ')->first();

        return [
            'pares_con_duplicado' => (int) ($row->pares_con_duplicado ?? 0),
            'filas_a_eliminar' => (int) ($row->filas_a_eliminar ?? 0),
        ];
    }

    /**
     * @param  list<array{articulo_id: int, listaprecio_id: int}>|null  $pares
     */
    private function subqueryKeeper(?array $pares)
    {
        $base = DB::table('precio as p2')
            ->selectRaw('p2.articulo_id, p2.listaprecio_id, MAX(p2.id) AS keep_id')
            ->joinSub(
                DB::table('precio')
                    ->selectRaw('articulo_id, listaprecio_id, MAX(fechavigencia) AS max_fechavigencia')
                    ->when($pares !== null && $pares !== [], function ($q) use ($pares) {
                        $q->where(function ($w) use ($pares) {
                            foreach ($pares as $par) {
                                $aid = (int) ($par['articulo_id'] ?? 0);
                                $lid = (int) ($par['listaprecio_id'] ?? 0);
                                if ($aid > 0 && $lid > 0) {
                                    $w->orWhere(function ($q2) use ($aid, $lid) {
                                        $q2->where('articulo_id', $aid)->where('listaprecio_id', $lid);
                                    });
                                }
                            }
                        });
                    })
                    ->groupBy('articulo_id', 'listaprecio_id'),
                'm',
                function ($join) {
                    $join->on('p2.articulo_id', '=', 'm.articulo_id')
                        ->on('p2.listaprecio_id', '=', 'm.listaprecio_id')
                        ->on('p2.fechavigencia', '=', 'm.max_fechavigencia');
                }
            )
            ->groupBy('p2.articulo_id', 'p2.listaprecio_id');

        return $base;
    }

    /**
     * @param  list<array{articulo_id: int, listaprecio_id: int}>|null  $pares
     */
    private function subqueryConteoObsoletos(?array $pares)
    {
        return DB::table('precio as p1')
            ->joinSub($this->subqueryKeeper($pares), 'k', function ($join) {
                $join->on('k.articulo_id', '=', 'p1.articulo_id')
                    ->on('k.listaprecio_id', '=', 'p1.listaprecio_id');
            })
            ->whereColumn('p1.id', '<>', 'k.keep_id')
            ->selectRaw('p1.articulo_id, p1.listaprecio_id, COUNT(*) AS obsoletos')
            ->groupBy('p1.articulo_id', 'p1.listaprecio_id');
    }
}
