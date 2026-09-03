<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Support\Stock\ArticuloMovimientoPrecioHistoricoSupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Completa costo en movimientos de insumo (venta / NC gastronomía) grabados con costo 0
 * cuando gastronomia.insumos_costo_diferido está habilitado.
 *
 * Acotado por fechajornada (índice) para que cada pasada sea liviana: resuelve última compra
 * una sola vez por artículo y actualiza por lote con DB::table (no dispara observers de saldo:
 * cantidad/depósito no cambian).
 */
final class GastronomiaInsumoCostoDiferidoService
{
    /**
     * @return array{
     *   desde: string, pendientes: int, articulos: int, articulos_procesados: int,
     *   actualizados: int, sin_costo: int, sin_costo_movs: int, ms: int, dry_run: bool
     * }
     */
    public function completar(int $dias, bool $dryRun = false, ?int $maxArticulos = null): array
    {
        $t0 = microtime(true);
        $dias = max(1, $dias);
        $maxArticulos = max(1, $maxArticulos ?? (int) config('gastronomia.insumos_costo_diferido.max_articulos', 300));
        $desde = Carbon::today()->subDays($dias)->toDateString();

        $pendientes = $this->queryPendientes($desde)
            ->selectRaw('articulo_id, COUNT(*) AS n')
            ->groupBy('articulo_id')
            ->orderByDesc('n')
            ->get();

        $totalPend = (int) $pendientes->sum('n');
        $articuloIds = $pendientes->pluck('articulo_id')->map(fn ($id) => (int) $id)->values();
        $aProcesar = $articuloIds->take($maxArticulos)->all();

        $res = [
            'desde' => $desde,
            'pendientes' => $totalPend,
            'articulos' => $articuloIds->count(),
            'articulos_procesados' => count($aProcesar),
            'actualizados' => 0,
            'sin_costo' => 0,
            'sin_costo_movs' => 0,
            'ms' => 0,
            'dry_run' => $dryRun,
        ];

        if ($aProcesar === []) {
            $res['ms'] = (int) round((microtime(true) - $t0) * 1000);

            return $res;
        }

        $precios = ArticuloMovimientoPrecioHistoricoSupport::resolverUltimaCompraInsumoPorArticuloIds($aProcesar);
        $nPorArticulo = $pendientes->keyBy('articulo_id');

        foreach ($aProcesar as $articuloId) {
            $dato = $precios[$articuloId] ?? null;
            $costo = $dato !== null ? round((float) ($dato['costo'] ?? 0), 6) : 0.0;
            if ($costo <= 0) {
                $res['sin_costo']++;
                $res['sin_costo_movs'] += (int) ($nPorArticulo[$articuloId]->n ?? 0);

                continue;
            }

            if ($dryRun) {
                $res['actualizados'] += (int) ($nPorArticulo[$articuloId]->n ?? 0);

                continue;
            }

            $monedaId = ! empty($dato['moneda_id']) ? (int) $dato['moneda_id'] : null;
            $set = ['costo' => $costo, 'precio' => '0'];
            if ($monedaId !== null) {
                $set['moneda_id'] = DB::raw('COALESCE(moneda_id, '.$monedaId.')');
            }

            $res['actualizados'] += $this->queryPendientes($desde)
                ->where('articulo_id', $articuloId)
                ->update($set);
        }

        $res['ms'] = (int) round((microtime(true) - $t0) * 1000);

        Log::info('gastronomia.insumos.costo_diferido', $res);

        return $res;
    }

    /**
     * Movimientos de insumo de venta/NC con costo pendiente dentro de la ventana.
     */
    private function queryPendientes(string $desde): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('articulo_movimiento')
            ->where('fechajornada', '>=', $desde)
            ->whereNotNull('venta_id')
            ->where('costo', 0)
            ->where('precio', 0);

        GastronomiaVentaDetalleSupport::aplicarWhereConceptoEsInsumo($q);

        return $q;
    }
}
