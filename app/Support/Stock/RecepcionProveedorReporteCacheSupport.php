<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Snapshot del informe de recepción de proveedores (file cache, no sesión).
 */
final class RecepcionProveedorReporteCacheSupport
{
    private const SESSION_FIRMA_KEY = 'recepcion_proveedor_reporte_cache_firma';

    private const TTL_HORAS = 2;

    /** Snapshot en file cache: un año (~34k líneas) entra; más que esto no vale el pico de serialize. */
    public const MAX_FILAS = 50000;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        return RecepcionProveedorReporteFiltros::firma($filtros);
    }

    public static function cabeEnCache(int $cantidadFilas): bool
    {
        return $cantidadFilas <= self::MAX_FILAS;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function cacheKey(array $filtros): string
    {
        $userId = (int) (auth()->id() ?? 0);

        return 'recepcion_proveedor_reporte_v5_'.$userId.'_'.self::firma($filtros);
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
        } elseif (! is_array($filas)) {
            $resultado['filas'] = [];
        }

        if (! self::cabeEnCache(count($resultado['filas']))) {
            return;
        }

        try {
            Cache::store('file')->put(self::cacheKey($filtros), [
                'firma' => $firma,
                'resultado' => $resultado,
            ], now()->addHours(self::TTL_HORAS));
            session()->put(self::SESSION_FIRMA_KEY, $firma);
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorReporteCache: no se pudo guardar', [
                'filas' => count($resultado['filas']),
                'error' => $e->getMessage(),
            ]);
        }
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
            return is_array($f) ? $f : (array) $f;
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
