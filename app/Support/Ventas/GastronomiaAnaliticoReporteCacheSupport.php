<?php

namespace App\Support\Ventas;

use Illuminate\Support\Facades\Cache;

/**
 * Snapshot del analítico gastronomía: payload en file cache (no sesión — volumen alto).
 */
final class GastronomiaAnaliticoReporteCacheSupport
{
    private const SESSION_FIRMA_KEY = 'gastronomia_analitico_reporte_cache_firma';

    private const TTL_HORAS = 4;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        return GastronomiaAnaliticoReporteFiltros::firma($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function cacheKey(array $filtros): string
    {
        $userId = (int) (auth()->id() ?? 0);

        return 'gastronomia_analitico_reporte_v1_'.$userId.'_'.self::firma($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    public static function guardar(array $filtros, array $resultado): void
    {
        $firma = self::firma($filtros);
        $filas = $resultado['filas'] ?? [];
        if ($filas instanceof \Illuminate\Support\Collection) {
            $resultado['filas'] = $filas->values()->all();
        } elseif ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $resultado['filas'] = collect($filas->items())->values()->all();
        } elseif (! is_array($filas)) {
            $resultado['filas'] = [];
        }

        Cache::store('file')->put(self::cacheKey($filtros), [
            'firma' => $firma,
            'resultado' => $resultado,
        ], now()->addHours(self::TTL_HORAS));

        session()->put(self::SESSION_FIRMA_KEY, $firma);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    public static function recuperar(array $filtros): ?array
    {
        $firma = self::firma($filtros);
        $pack = Cache::store('file')->get(self::cacheKey($filtros));

        if (! is_array($pack) || ($pack['firma'] ?? '') !== $firma) {
            return null;
        }

        $resultado = $pack['resultado'] ?? null;
        if (! is_array($resultado)) {
            return null;
        }

        $filas = $resultado['filas'] ?? [];
        $resultado['filas'] = collect(is_array($filas) ? $filas : [])->map(static function ($f) {
            return is_object($f) ? $f : (object) $f;
        })->values();

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function limpiar(array $filtros = []): void
    {
        if ($filtros !== []) {
            Cache::store('file')->forget(self::cacheKey($filtros));
        }
        session()->forget(self::SESSION_FIRMA_KEY);
    }
}
