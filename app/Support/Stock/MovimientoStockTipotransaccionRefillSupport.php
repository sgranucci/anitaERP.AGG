<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: refill de tipotransaccion_stock en cabeceras/líneas de movimientos.
 *
 * En Ferli los tipos de stock convivían en `tipotransaccion` (muchos con operacion V).
 * La migración escenario A solo copió E/S/T, así que el mapa quedó vacío y las
 * cabeceras quedaron con tipotransaccion_stock_id = 0. Las líneas conservan
 * tipotransaccion_id (ALTAP/CONOT). Este support:
 * 1) arma tipotransaccion_stock_map por abreviatura
 * 2) rellena movimientostock + articulo_movimiento
 */
final class MovimientoStockTipotransaccionRefillSupport
{
    /**
     * @return array{
     *     mapa_propuesto: list<array{abreviatura: string, tipotransaccion_id: int, tipotransaccion_stock_id: int, nombre_stock: string}>,
     *     mapa_ya_existente: int,
     *     mapa_a_insertar: int,
     *     cabeceras_a_actualizar: list<array{movimientostock_id: int, tipo_venta_id: int, tipo_stock_id: int, abreviatura: string, lineas: int}>,
     *     cabeceras_sin_lineas: list<int>,
     *     cabeceras_sin_mapa: list<array{movimientostock_id: int, tipo_venta_id: int, lineas: int}>,
     *     lineas_a_actualizar: int,
     *     resumen_por_abreviatura: array<string, array{cabeceras: int, lineas: int, tipo_stock_id: int}>
     * }
     */
    public static function planificar(): array
    {
        $mapaPropuesto = self::proponerMapaPorAbreviatura();
        $mapaExistente = Schema::hasTable('tipotransaccion_stock_map')
            ? (int) DB::table('tipotransaccion_stock_map')->count()
            : 0;

        $idsVentaYaMapeados = Schema::hasTable('tipotransaccion_stock_map')
            ? DB::table('tipotransaccion_stock_map')->pluck('tipotransaccion_id')->map(fn ($id) => (int) $id)->all()
            : [];
        $aInsertar = 0;
        foreach ($mapaPropuesto as $fila) {
            if (! in_array($fila['tipotransaccion_id'], $idsVentaYaMapeados, true)) {
                $aInsertar++;
            }
        }

        $mapaEfectivo = [];
        foreach ($mapaPropuesto as $fila) {
            $mapaEfectivo[$fila['tipotransaccion_id']] = $fila;
        }
        if (Schema::hasTable('tipotransaccion_stock_map')) {
            foreach (DB::table('tipotransaccion_stock_map')->get() as $row) {
                $ventaId = (int) $row->tipotransaccion_id;
                $stockId = (int) $row->tipotransaccion_stock_id;
                if (! isset($mapaEfectivo[$ventaId])) {
                    $abrev = (string) (DB::table('tipotransaccion_stock')->whereKey($stockId)->value('abreviatura') ?? '');
                    $nombre = (string) (DB::table('tipotransaccion_stock')->whereKey($stockId)->value('nombre') ?? '');
                    $mapaEfectivo[$ventaId] = [
                        'abreviatura' => $abrev,
                        'tipotransaccion_id' => $ventaId,
                        'tipotransaccion_stock_id' => $stockId,
                        'nombre_stock' => $nombre,
                    ];
                }
            }
        }

        $cabeceras = [];
        $sinLineas = [];
        $sinMapa = [];
        $resumen = [];
        $lineasTotal = 0;

        $msIds = DB::table('movimientostock')
            ->where(function ($q) {
                $q->whereNull('tipotransaccion_stock_id')
                    ->orWhere('tipotransaccion_stock_id', 0);
            })
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach (array_chunk($msIds, 500) as $chunk) {
            $agg = DB::table('articulo_movimiento as am')
                ->select('am.movimientostock_id', 'am.tipotransaccion_id')
                ->selectRaw('COUNT(*) as lineas')
                ->whereIn('am.movimientostock_id', $chunk)
                ->whereNotNull('am.tipotransaccion_id')
                ->where('am.tipotransaccion_id', '>', 0)
                ->groupBy('am.movimientostock_id', 'am.tipotransaccion_id')
                ->get();

            /** @var array<int, list<object>> $porMs */
            $porMs = [];
            foreach ($agg as $row) {
                $porMs[(int) $row->movimientostock_id][] = $row;
            }

            foreach ($chunk as $msId) {
                $grupos = $porMs[$msId] ?? [];
                if ($grupos === []) {
                    $sinLineas[] = $msId;

                    continue;
                }

                usort($grupos, static fn ($a, $b) => ((int) $b->lineas) <=> ((int) $a->lineas));
                $elegido = $grupos[0];
                $tipoVentaId = (int) $elegido->tipotransaccion_id;
                $lineas = (int) $elegido->lineas;
                $map = $mapaEfectivo[$tipoVentaId] ?? null;
                if ($map === null) {
                    $sinMapa[] = [
                        'movimientostock_id' => $msId,
                        'tipo_venta_id' => $tipoVentaId,
                        'lineas' => $lineas,
                    ];

                    continue;
                }

                $abrev = $map['abreviatura'];
                $stockId = (int) $map['tipotransaccion_stock_id'];
                $cabeceras[] = [
                    'movimientostock_id' => $msId,
                    'tipo_venta_id' => $tipoVentaId,
                    'tipo_stock_id' => $stockId,
                    'abreviatura' => $abrev,
                    'lineas' => $lineas,
                ];
                $lineasTotal += $lineas;
                if (! isset($resumen[$abrev])) {
                    $resumen[$abrev] = ['cabeceras' => 0, 'lineas' => 0, 'tipo_stock_id' => $stockId];
                }
                $resumen[$abrev]['cabeceras']++;
                $resumen[$abrev]['lineas'] += $lineas;
            }
        }

        // Líneas de MS (cualquier cabecera) con tipo venta mapeable y stock vacío.
        $lineasPendientes = 0;
        foreach ($mapaEfectivo as $ventaId => $map) {
            $lineasPendientes += (int) DB::table('articulo_movimiento')
                ->where('tipotransaccion_id', $ventaId)
                ->whereNotNull('movimientostock_id')
                ->where('movimientostock_id', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('tipotransaccion_stock_id')
                        ->orWhere('tipotransaccion_stock_id', 0);
                })
                ->count();
        }

        return [
            'mapa_propuesto' => array_values($mapaPropuesto),
            'mapa_ya_existente' => $mapaExistente,
            'mapa_a_insertar' => $aInsertar,
            'cabeceras_a_actualizar' => $cabeceras,
            'cabeceras_sin_lineas' => $sinLineas,
            'cabeceras_sin_mapa' => $sinMapa,
            'lineas_a_actualizar' => max($lineasTotal, $lineasPendientes),
            'resumen_por_abreviatura' => $resumen,
        ];
    }

    /**
     * @param  array<string, mixed>  $plan  resultado de planificar()
     * @return array{mapa_insertados: int, cabeceras: int, lineas: int}
     */
    public static function ejecutar(array $plan): array
    {
        if (! Schema::hasTable('tipotransaccion_stock_map')) {
            throw new \RuntimeException('Falta tabla tipotransaccion_stock_map.');
        }

        $mapaInsertados = 0;
        $existentes = DB::table('tipotransaccion_stock_map')
            ->pluck('tipotransaccion_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($plan['mapa_propuesto'] as $fila) {
            $ventaId = (int) $fila['tipotransaccion_id'];
            $stockId = (int) $fila['tipotransaccion_stock_id'];
            if (in_array($ventaId, $existentes, true)) {
                continue;
            }
            DB::table('tipotransaccion_stock_map')->insert([
                'tipotransaccion_id' => $ventaId,
                'tipotransaccion_stock_id' => $stockId,
            ]);
            $existentes[] = $ventaId;
            $mapaInsertados++;
        }

        $mapa = DB::table('tipotransaccion_stock_map')
            ->pluck('tipotransaccion_stock_id', 'tipotransaccion_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $cabeceras = 0;
        foreach (array_chunk($plan['cabeceras_a_actualizar'], 200) as $chunk) {
            foreach ($chunk as $fila) {
                $n = DB::table('movimientostock')
                    ->where('id', $fila['movimientostock_id'])
                    ->where(function ($q) {
                        $q->whereNull('tipotransaccion_stock_id')
                            ->orWhere('tipotransaccion_stock_id', 0);
                    })
                    ->update([
                        'tipotransaccion_stock_id' => $fila['tipo_stock_id'],
                        'updated_at' => now(),
                    ]);
                $cabeceras += $n;
            }
        }

        $lineas = 0;
        foreach ($mapa as $ventaId => $stockId) {
            $lineas += DB::table('articulo_movimiento')
                ->where('tipotransaccion_id', $ventaId)
                ->whereNotNull('movimientostock_id')
                ->where('movimientostock_id', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('tipotransaccion_stock_id')
                        ->orWhere('tipotransaccion_stock_id', 0);
                })
                ->update([
                    'tipotransaccion_stock_id' => $stockId,
                    'tipotransaccion_id' => null,
                    'updated_at' => now(),
                ]);
        }

        return [
            'mapa_insertados' => $mapaInsertados,
            'cabeceras' => $cabeceras,
            'lineas' => $lineas,
        ];
    }

    /**
     * @return list<array{abreviatura: string, tipotransaccion_id: int, tipotransaccion_stock_id: int, nombre_stock: string}>
     */
    public static function proponerMapaPorAbreviatura(): array
    {
        $out = [];
        $stocks = DB::table('tipotransaccion_stock')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'abreviatura', 'nombre']);

        foreach ($stocks as $stock) {
            $abrev = trim((string) $stock->abreviatura);
            if ($abrev === '') {
                continue;
            }

            $venta = DB::table('tipotransaccion')
                ->where('abreviatura', $abrev)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->first(['id']);

            if ($venta === null) {
                $venta = DB::table('tipotransaccion')
                    ->where('abreviatura', $abrev)
                    ->orderByDesc('id')
                    ->first(['id']);
            }

            if ($venta === null) {
                continue;
            }

            $out[] = [
                'abreviatura' => $abrev,
                'tipotransaccion_id' => (int) $venta->id,
                'tipotransaccion_stock_id' => (int) $stock->id,
                'nombre_stock' => (string) $stock->nombre,
            ];
        }

        return $out;
    }
}
