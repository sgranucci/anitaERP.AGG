<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;

/**
 * Bucket de stock de una OT: punto único.
 *
 * Un movimiento puede señalar la OT a la que pertenece su saldo de dos formas:
 *
 * | Caso | `lote` | `ordentrabajo_id` |
 * |---|---|---|
 * | Alta de producción (desde 24/09/2026) | 0 | OT propia |
 * | Alta de producción (hasta 24/09/2026) | código OT | OT propia |
 * | Consumo desde el stock de otra OT | código OT **origen** | OT que consume |
 * | Consumo por movimiento de stock | código OT origen | 0 |
 * | Lote importado de Excel | número de lote | 0 |
 *
 * El consumo desde otra OT no puede dejar de usar `lote`: `ordentrabajo_id` ya lo
 * ocupa la OT que consume. Por eso la unificación va en la lectura y no al grabar.
 *
 * Sin esta unificación el alta queda en `OT:<id>` y su consumo en `L:<código>`, no
 * se netean, y el reporte muestra como disponible stock ya consumido.
 *
 * Un `lote` solo se toma como OT si existe una OT con ese código **y** esa OT es del
 * mismo artículo y combinación del movimiento. Sin esa guarda, un lote importado que
 * coincide por número con una OT ajena se fusionaría con ella.
 */
final class OtBucketStockSupport
{
    /** @var array<string, int> clave `lote|articulo|combinacion` => ordentrabajo_id (0 = lote importado) */
    private static array $cache = [];

    public static function olvidarCache(): void
    {
        self::$cache = [];
    }

    /**
     * OT a la que pertenece el saldo del movimiento. 0 si el bucket no es una OT.
     */
    public static function ordentrabajoIdDelBucket(
        mixed $lote,
        int $ordentrabajoId,
        int $articuloId,
        int $combinacionId
    ): int {
        $otPorLote = self::otIdDeLote($lote, $articuloId, $combinacionId);
        if ($otPorLote > 0) {
            return $otPorLote;
        }

        // Sin lote de OT, el bucket es la OT propia del movimiento (alta nueva).
        return ReporteStockOtSituacionSupport::esLoteImportado($lote) ? 0 : max($ordentrabajoId, 0);
    }

    /**
     * OT cuyo código es ese lote, si además es del mismo artículo y combinación.
     */
    public static function otIdDeLote(mixed $lote, int $articuloId, int $combinacionId): int
    {
        if (! ReporteStockOtSituacionSupport::esLoteImportado($lote)) {
            return 0;
        }
        $loteTxt = trim((string) $lote);
        if (! ctype_digit($loteTxt) || $articuloId <= 0 || $combinacionId <= 0) {
            return 0;
        }

        $clave = $loteTxt.'|'.$articuloId.'|'.$combinacionId;
        if (! array_key_exists($clave, self::$cache)) {
            self::precargar([[
                'lote' => $loteTxt,
                'articulo_id' => $articuloId,
                'combinacion_id' => $combinacionId,
            ]]);
        }

        return self::$cache[$clave] ?? 0;
    }

    /**
     * Resuelve en bloque para no pegarle a la base una vez por fila del reporte.
     *
     * @param  iterable<int, array<string, mixed>|object>  $filas
     */
    public static function precargar(iterable $filas): void
    {
        $pendientes = [];
        $lotes = [];
        $articulos = [];
        $combinaciones = [];

        foreach ($filas as $fila) {
            $lote = trim((string) self::valor($fila, 'lote'));
            $articuloId = (int) self::valor($fila, 'articulo_id');
            $combinacionId = (int) self::valor($fila, 'combinacion_id');
            if (! ReporteStockOtSituacionSupport::esLoteImportado($lote)
                || ! ctype_digit($lote)
                || $articuloId <= 0
                || $combinacionId <= 0) {
                continue;
            }
            $clave = $lote.'|'.$articuloId.'|'.$combinacionId;
            if (array_key_exists($clave, self::$cache) || isset($pendientes[$clave])) {
                continue;
            }
            $pendientes[$clave] = true;
            $lotes[$lote] = $lote;
            $articulos[$articuloId] = $articuloId;
            $combinaciones[$combinacionId] = $combinacionId;
        }

        if ($pendientes === []) {
            return;
        }

        foreach (array_keys($pendientes) as $clave) {
            self::$cache[$clave] = 0;
        }

        $resueltos = self::resolverDesdeMovimientos($lotes, $articulos, $combinaciones)
            + self::resolverDesdeRenglonesOt($lotes, $articulos, $combinaciones);

        foreach ($resueltos as $clave => $otId) {
            if (isset($pendientes[$clave]) && $otId > 0) {
                self::$cache[$clave] = $otId;
            }
        }
    }

    /**
     * OT con movimientos de stock propios del mismo artículo y combinación.
     *
     * @param  array<int|string, string>  $lotes
     * @param  array<int, int>  $articulos
     * @param  array<int, int>  $combinaciones
     * @return array<string, int>
     */
    private static function resolverDesdeMovimientos(array $lotes, array $articulos, array $combinaciones): array
    {
        $rows = DB::table('ordentrabajo as ot')
            ->join('articulo_movimiento as am', 'am.ordentrabajo_id', '=', 'ot.id')
            ->whereIn('ot.codigo', array_values($lotes))
            ->whereIn('am.articulo_id', array_values($articulos))
            ->whereIn('am.combinacion_id', array_values($combinaciones))
            ->groupBy('ot.codigo', 'am.articulo_id', 'am.combinacion_id')
            ->select([
                'ot.codigo',
                'am.articulo_id',
                'am.combinacion_id',
                DB::raw('MIN(ot.id) as ot_id'),
            ])
            ->get();

        return self::indexar($rows);
    }

    /**
     * OT sin movimiento todavía: se mira el renglón de pedido que la originó.
     *
     * @param  array<int|string, string>  $lotes
     * @param  array<int, int>  $articulos
     * @param  array<int, int>  $combinaciones
     * @return array<string, int>
     */
    private static function resolverDesdeRenglonesOt(array $lotes, array $articulos, array $combinaciones): array
    {
        $rows = DB::table('ordentrabajo as ot')
            ->join('ordentrabajo_combinacion_talle as oct', 'oct.ordentrabajo_id', '=', 'ot.id')
            ->join('pedido_combinacion_talle as pct', 'pct.id', '=', 'oct.pedido_combinacion_talle_id')
            ->join('pedido_combinacion as pc', 'pc.id', '=', 'pct.pedido_combinacion_id')
            ->whereIn('ot.codigo', array_values($lotes))
            ->whereIn('pc.articulo_id', array_values($articulos))
            ->whereIn('pc.combinacion_id', array_values($combinaciones))
            ->groupBy('ot.codigo', 'pc.articulo_id', 'pc.combinacion_id')
            ->select([
                'ot.codigo',
                'pc.articulo_id',
                'pc.combinacion_id',
                DB::raw('MIN(ot.id) as ot_id'),
            ])
            ->get();

        return self::indexar($rows);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<string, int>
     */
    private static function indexar($rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $clave = trim((string) $row->codigo).'|'.(int) $row->articulo_id.'|'.(int) $row->combinacion_id;
            $out[$clave] = (int) $row->ot_id;
        }

        return $out;
    }

    /**
     * Corte de filas del reporte / modal ya unificado.
     */
    public static function claveAgrupacion(
        mixed $lote,
        int $ordentrabajoId,
        int $articuloId,
        int $combinacionId,
        int $depositoId = 0
    ): string {
        $otId = self::ordentrabajoIdDelBucket($lote, $ordentrabajoId, $articuloId, $combinacionId);
        $base = $otId > 0
            ? 'OT:'.$otId
            : 'L:'.trim((string) $lote);

        return $base.'|D:'.$depositoId;
    }

    private static function valor(array|object $fila, string $campo): mixed
    {
        if (is_array($fila)) {
            return $fila[$campo] ?? null;
        }

        return $fila->{$campo} ?? null;
    }
}
