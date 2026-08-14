<?php

namespace App\Support\Ventas;

use Illuminate\Support\Facades\Cache;

/**
 * Snapshot del informe gerente gastronomía: file cache por firma de filtros
 * (export PDF/Excel/PPTX reutiliza sin regenerar).
 */
final class GastronomiaInformeGerenteCacheSupport
{
    private const TTL_HORAS = 2;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        return GastronomiaInformeGerenteFiltros::firma($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function cacheKey(array $filtros): string
    {
        $userId = (int) (auth()->id() ?? 0);

        return 'gastronomia_informe_gerente_v1_'.$userId.'_'.self::firma($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $informe
     */
    public static function guardar(array $filtros, array $informe): void
    {
        Cache::store('file')->put(self::cacheKey($filtros), [
            'firma' => self::firma($filtros),
            'resultado' => $informe,
        ], now()->addHours(self::TTL_HORAS));
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

        return is_array($resultado) ? $resultado : null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function limpiar(array $filtros = []): void
    {
        if ($filtros !== []) {
            Cache::store('file')->forget(self::cacheKey($filtros));
        }
    }
}
