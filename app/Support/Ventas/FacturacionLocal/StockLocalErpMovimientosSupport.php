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
     *   am_id:int,
     *   articulo_id:int|string,
     *   cantidad:float|string,
     *   fecha:mixed,
     *   combinacion_codigo:?string,
     *   combinacion_nombre:?string,
     *   color_codigo_m:?string,
     *   color_nombre:?string,
     *   medida:?string,
     *   medida_nombre:?string,
     *   tipo_abreviatura:?string,
     *   tipo_nombre:?string,
     *   tipo_venta_abreviatura:?string,
     *   tipo_venta_nombre:?string,
     *   venta_id:?int,
     *   venta_codigo:?string,
     *   movimientostock_id:?int,
     *   movimiento_codigo:?string,
     *   concepto:?string
     * }>
     */
    public static function filasPorDepositoYArticulos(
        int $depositoId,
        array $articuloIds,
        ?string $fechaHasta = null,
        ?string $fechaDesde = null
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
                ->leftJoin('tipotransaccion_stock as ts', 'ts.id', '=', 'am.tipotransaccion_stock_id')
                ->leftJoin('tipotransaccion as tt', 'tt.id', '=', 'am.tipotransaccion_id')
                ->leftJoin('venta as v', 'v.id', '=', 'am.venta_id')
                ->leftJoin('movimientostock as ms', 'ms.id', '=', 'am.movimientostock_id')
                ->where('am.deposito_id', $depositoId)
                ->whereIn('am.articulo_id', $chunk)
                ->whereNotNull('am.articulo_id');

            if ($fechaDesde !== null && $fechaDesde !== '') {
                $query->whereDate('am.fecha', '>=', $fechaDesde);
            }
            if ($fechaHasta !== null && $fechaHasta !== '') {
                $query->whereDate('am.fecha', '<=', $fechaHasta);
            }

            $rows = $query->select([
                'am.id as am_id',
                'am.articulo_id',
                'am.cantidad as am_cantidad',
                'am.fecha',
                'am.concepto',
                'am.venta_id',
                'am.movimientostock_id',
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
                'v.codigo as venta_codigo',
                'tt.abreviatura as tipo_venta_abreviatura',
                'tt.nombre as tipo_venta_nombre',
                'ms.codigo as movimiento_codigo',
                DB::raw('COALESCE(ts.nombre, tt.nombre) AS tipo_nombre'),
                DB::raw('COALESCE(ts.abreviatura, tt.abreviatura) AS tipo_abreviatura'),
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
                    'am_id' => (int) $row->am_id,
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
                    'tipo_abreviatura' => $row->tipo_abreviatura,
                    'tipo_nombre' => $row->tipo_nombre,
                    'tipo_venta_abreviatura' => $row->tipo_venta_abreviatura,
                    'tipo_venta_nombre' => $row->tipo_venta_nombre,
                    'venta_id' => $row->venta_id !== null ? (int) $row->venta_id : null,
                    'venta_codigo' => $row->venta_codigo,
                    'movimientostock_id' => $row->movimientostock_id !== null ? (int) $row->movimientostock_id : null,
                    'movimiento_codigo' => $row->movimiento_codigo,
                    'concepto' => $row->concepto,
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

    /**
     * Tipo de comprobante (misma lógica que kardex / RecuentoMovimientosArticuloSupport).
     */
    public static function tipoComprobanteDesdeFila(object $row): string
    {
        $tipoVenta = trim((string) ($row->tipo_venta_abreviatura ?? $row->tipo_venta_nombre ?? ''));
        if (! empty($row->venta_id) && $tipoVenta !== '') {
            return $tipoVenta;
        }

        return trim((string) ($row->tipo_abreviatura ?: $row->tipo_nombre ?: '—')) ?: '—';
    }

    /**
     * Número / código de comprobante (venta, mov. stock o concepto).
     */
    public static function numeroComprobanteDesdeFila(object $row): string
    {
        $ventaCodigo = trim((string) ($row->venta_codigo ?? ''));
        if ($ventaCodigo !== '') {
            return $ventaCodigo;
        }
        $movCodigo = trim((string) ($row->movimiento_codigo ?? ''));
        if ($movCodigo !== '') {
            return $movCodigo;
        }
        $concepto = trim((string) ($row->concepto ?? ''));

        return $concepto !== '' ? $concepto : '—';
    }
}
