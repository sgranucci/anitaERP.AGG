<?php

namespace App\Support\Ventas\FacturacionLocal;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lectura de articulo_movimiento para stock de locales (Ferli).
 * Los talles calzado viven en articulo_movimiento_talle (am.talle_id suele ser null).
 */
final class StockLocalErpMovimientosSupport
{
    /**
     * Filas firmadas por artículo × color/combinación × medida.
     * Si el movimiento tiene talles hijos con cantidad != 0, usa esos;
     * si no, usa am.cantidad con medida de am.talle_id (o 0).
     *
     * @param  list<int>  $articuloIds
     * @return Collection<int, object{
     *   articulo_id:int|string,
     *   cantidad:float|string,
     *   fecha:mixed,
     *   combinacion_codigo:?string,
     *   combinacion_nombre:?string,
     *   color_codigo_m:?string,
     *   color_nombre:?string,
     *   medida:?string,
     *   medida_nombre:?string
     * }>
     */
    public static function filasPorDepositoYArticulos(
        int $depositoId,
        array $articuloIds,
        ?string $fechaHasta = null
    ): Collection {
        if ($depositoId <= 0 || $articuloIds === []) {
            return collect();
        }

        $out = collect();
        foreach (array_chunk($articuloIds, 500) as $chunk) {
            $query = DB::table('articulo_movimiento as am')
                ->leftJoin('combinacion as c', 'c.id', '=', 'am.combinacion_id')
                ->leftJoin('color as col', 'col.id', '=', 'am.color_id')
                ->leftJoin('articulo_movimiento_talle as amt', function ($join) {
                    $join->on('amt.articulo_movimiento_id', '=', 'am.id')
                        ->whereRaw('ABS(amt.cantidad) > 0.000001');
                })
                ->leftJoin('talle as t_amt', 't_amt.id', '=', 'amt.talle_id')
                ->leftJoin('talle as t_am', 't_am.id', '=', 'am.talle_id')
                ->where('am.deposito_id', $depositoId)
                ->whereIn('am.articulo_id', $chunk)
                ->whereNotNull('am.articulo_id');

            if ($fechaHasta !== null && $fechaHasta !== '') {
                $query->whereDate('am.fecha', '<=', $fechaHasta);
            }

            $rows = $query->select([
                'am.id as am_id',
                'am.articulo_id',
                'am.cantidad as am_cantidad',
                'am.fecha',
                'amt.id as amt_id',
                'amt.cantidad as amt_cantidad',
                'c.codigo as combinacion_codigo',
                'c.nombre as combinacion_nombre',
                'col.codigo as color_codigo_m',
                'col.nombre as color_nombre',
                't_amt.codigo as medida_amt',
                't_amt.nombre as medida_nombre_amt',
                't_am.codigo as medida_am',
                't_am.nombre as medida_nombre_am',
            ])->get();

            foreach ($rows as $row) {
                $amCant = (float) ($row->am_cantidad ?? 0);
                if ($row->amt_id !== null) {
                    $cant = (float) ($row->amt_cantidad ?? 0);
                    $medida = self::normalizarMedida($row->medida_amt ?? null, $row->medida_nombre_amt ?? null);
                } else {
                    $cant = $amCant;
                    $medida = self::normalizarMedida($row->medida_am ?? null, $row->medida_nombre_am ?? null);
                }
                if (abs($cant) < 0.000001) {
                    continue;
                }

                $out->push((object) [
                    'articulo_id' => (int) $row->articulo_id,
                    'cantidad' => $cant,
                    'fecha' => $row->fecha,
                    'combinacion_codigo' => $row->combinacion_codigo,
                    'combinacion_nombre' => $row->combinacion_nombre,
                    'color_codigo_m' => $row->color_codigo_m,
                    'color_nombre' => $row->color_nombre,
                    'medida' => is_int($medida) || is_string($medida) ? (string) $medida : '0',
                    'medida_nombre' => $row->amt_id !== null
                        ? (string) ($row->medida_nombre_amt ?? '')
                        : (string) ($row->medida_nombre_am ?? ''),
                ]);
            }
        }

        return $out;
    }

    /**
     * @return int|string
     */
    public static function normalizarMedida(mixed $codigo, mixed $nombre): int|string
    {
        $medida = trim((string) ($codigo ?? ''));
        if ($medida !== '' && ctype_digit($medida)) {
            return (int) $medida;
        }
        if ($medida !== '') {
            return $medida;
        }
        $nom = trim((string) ($nombre ?? ''));
        if ($nom !== '' && ctype_digit($nom)) {
            return (int) $nom;
        }
        if ($nom !== '') {
            return $nom;
        }

        return 0;
    }

    /**
     * @param  object{combinacion_codigo?:mixed,combinacion_nombre?:mixed,color_codigo_m?:mixed,color_nombre?:mixed}  $row
     * @return array{0:string,1:string}
     */
    public static function colorDesdeFila(object $row): array
    {
        $colorCodigo = trim((string) ($row->combinacion_codigo ?? ''));
        $colorDesc = trim((string) ($row->combinacion_nombre ?? ''));
        if ($colorCodigo === '') {
            $colorCodigo = trim((string) ($row->color_codigo_m ?? ''));
            $colorDesc = trim((string) ($row->color_nombre ?? ''));
        }
        if ($colorCodigo === '') {
            $colorCodigo = '0';
        }

        return [$colorCodigo, $colorDesc];
    }
}
