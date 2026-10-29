<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Snapshot de Stock por OT (file cache, no sesión).
 * El filtro de texto se aplica después, sobre estas filas.
 */
final class ReporteStockOtReporteCacheSupport
{
    private const TTL_HORAS = 2;

    public const MAX_FILAS = 50000;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function cacheKey(array $filtros): string
    {
        $userId = (int) (auth()->id() ?? 0);

        return 'repstockot_v1_'.$userId.'_'.ReporteStockOtListadoFiltros::firmaConsulta($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array<string, mixed>>  $filas
     */
    public static function guardar(array $filtros, array $filas): void
    {
        if (count($filas) > self::MAX_FILAS) {
            return;
        }

        try {
            Cache::store('file')->put(self::cacheKey($filtros), [
                'firma' => ReporteStockOtListadoFiltros::firmaConsulta($filtros),
                'filas' => array_values($filas),
            ], now()->addHours(self::TTL_HORAS));
        } catch (\Throwable $e) {
            Log::warning('ReporteStockOtReporteCache: no se pudo guardar', [
                'filas' => count($filas),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>|null
     */
    public static function recuperar(array $filtros): ?array
    {
        $firma = ReporteStockOtListadoFiltros::firmaConsulta($filtros);
        $pack = Cache::store('file')->get(self::cacheKey($filtros));
        if (! is_array($pack) || ($pack['firma'] ?? '') !== $firma || ! is_array($pack['filas'] ?? null)) {
            return null;
        }

        $filas = [];
        foreach ($pack['filas'] as $fila) {
            $filas[] = is_array($fila) ? $fila : (array) $fila;
        }

        return $filas;
    }
}
