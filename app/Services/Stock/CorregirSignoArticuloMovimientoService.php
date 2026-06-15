<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo_Movimiento;
use App\Support\Stock\ArticuloMovimientoCantidadSignoSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Corrige cantidad firmada en articulo_movimiento histórico según tipo stock o operacionstock venta.
 */
final class CorregirSignoArticuloMovimientoService
{
    /**
     * @return array{
     *   escaneados:int,
     *   corregidos:int,
     *   ya_correctos:int,
     *   omitidos_sin_tipo:int,
     *   omitidos_sin_operacion:int,
     *   muestra:list<array{id:int,antes:float,despues:float,origen:string}>
     * }
     */
    public function ejecutar(
        bool $dryRun = true,
        ?int $ventaId = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        ?int $depositoId = null,
        bool $soloIncorrectos = true,
        bool $anularSinOperacionStock = false,
        int $chunkSize = 500,
        int $muestraMax = 15,
    ): array {
        $stats = [
            'escaneados' => 0,
            'corregidos' => 0,
            'ya_correctos' => 0,
            'omitidos_sin_tipo' => 0,
            'omitidos_sin_operacion' => 0,
            'muestra' => [],
        ];

        $query = $this->queryBase($ventaId, $fechaDesde, $fechaHasta, $depositoId, $soloIncorrectos);

        $query->orderBy('am.id')->chunkById($chunkSize, function (Collection $filas) use (
            &$stats,
            $dryRun,
            $anularSinOperacionStock,
            $muestraMax,
        ) {
            $updates = [];

            foreach ($filas as $fila) {
                $stats['escaneados']++;

                $cantidadActual = (float) $fila->cantidad;
                $tieneStock = ! empty($fila->tipotransaccion_stock_id);
                $tieneVenta = ! empty($fila->tipotransaccion_id);
                $operacionstock = $fila->operacionstock ?? null;

                $cantidadNueva = ArticuloMovimientoCantidadSignoSupport::cantidadCorregida(
                    $cantidadActual,
                    $tieneStock ? (int) $fila->tipotransaccion_stock_id : null,
                    isset($fila->ts_signo) ? (int) $fila->ts_signo : null,
                    $tieneVenta ? (int) $fila->tipotransaccion_id : null,
                    $operacionstock,
                );

                if ($cantidadNueva === null) {
                    if ($tieneVenta && ! $tieneStock && $operacionstock !== null
                        && ! in_array($operacionstock, ['S', 'E'], true)) {
                        if ($anularSinOperacionStock && abs($cantidadActual) > 1e-9) {
                            $cantidadNueva = 0.0;
                        } else {
                            $stats['omitidos_sin_operacion']++;

                            continue;
                        }
                    } else {
                        $stats['omitidos_sin_tipo']++;

                        continue;
                    }
                }

                if (! ArticuloMovimientoCantidadSignoSupport::necesitaCorreccion($cantidadActual, $cantidadNueva)) {
                    $stats['ya_correctos']++;

                    continue;
                }

                $updates[(int) $fila->id] = $cantidadNueva;

                if (count($stats['muestra']) < $muestraMax) {
                    $stats['muestra'][] = [
                        'id' => (int) $fila->id,
                        'antes' => $cantidadActual,
                        'despues' => $cantidadNueva,
                        'origen' => $tieneStock ? 'stock' : 'venta',
                        'operacionstock' => $operacionstock,
                        'venta_id' => $fila->venta_id ?? null,
                    ];
                }
            }

            if ($dryRun || $updates === []) {
                $stats['corregidos'] += count($updates);

                return;
            }

            DB::transaction(function () use ($updates) {
                foreach ($updates as $id => $cantidad) {
                    $movimiento = Articulo_Movimiento::query()->find((int) $id);
                    if ($movimiento !== null) {
                        $movimiento->update(['cantidad' => $cantidad]);
                    }
                }
            });

            $stats['corregidos'] += count($updates);
        }, 'am.id', 'id');

        return $stats;
    }

    public function contarIncorrectos(
        ?int $ventaId = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        ?int $depositoId = null,
    ): int {
        return (int) $this->queryBase($ventaId, $fechaDesde, $fechaHasta, $depositoId, true)->count();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function queryBase(
        ?int $ventaId,
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?int $depositoId,
        bool $soloIncorrectos,
    ) {
        $query = DB::table('articulo_movimiento as am')
            ->leftJoin('tipotransaccion as tt', 'tt.id', '=', 'am.tipotransaccion_id')
            ->leftJoin('tipotransaccion_stock as ts', 'ts.id', '=', 'am.tipotransaccion_stock_id')
            ->whereNull('am.deleted_at')
            ->select([
                'am.id',
                'am.cantidad',
                'am.venta_id',
                'am.tipotransaccion_id',
                'am.tipotransaccion_stock_id',
                'tt.operacionstock',
                'ts.signo as ts_signo',
            ]);

        if ($ventaId !== null && $ventaId > 0) {
            $query->where('am.venta_id', $ventaId);
        }

        if ($fechaDesde !== null && $fechaDesde !== '') {
            $query->whereDate('am.fecha', '>=', $fechaDesde);
        }

        if ($fechaHasta !== null && $fechaHasta !== '') {
            $query->whereDate('am.fecha', '<=', $fechaHasta);
        }

        if ($depositoId !== null && $depositoId > 0) {
            $query->where('am.deposito_id', $depositoId);
        }

        if ($soloIncorrectos) {
            $query->where(function ($w) {
                $w->where(function ($v) {
                    $v->whereNotNull('am.tipotransaccion_id')
                        ->whereNull('am.tipotransaccion_stock_id')
                        ->whereRaw('('.ArticuloMovimientoCantidadSignoSupport::sqlFiltroSignoIncorrectoVenta().')');
                })->orWhere(function ($s) {
                    $s->whereNotNull('am.tipotransaccion_stock_id')
                        ->whereRaw('('.ArticuloMovimientoCantidadSignoSupport::sqlFiltroSignoIncorrectoStock().')');
                });
            });
        }

        return $query;
    }
}
