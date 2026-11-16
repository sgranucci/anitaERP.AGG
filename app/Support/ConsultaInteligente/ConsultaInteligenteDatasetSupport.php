<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Materialización liviana en cache (no warehouse): evita repetir queries pesadas
 * del explorador durante unos minutos.
 */
final class ConsultaInteligenteDatasetSupport
{
    public const TTL_SEGUNDOS = 300;

    public const UMBRAL_FILAS = 200;

    /** Tope al cachear el universo completo (evita OOM). */
    public const MAX_CACHE_FILAS = 2000;

    public const PER_PAGE = 10;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function consultar(
        EntidadSemantica $entidad,
        array $filtros,
        bool $paginar = true,
        bool $forzarRefresh = false,
        ?int $page = null
    ): LengthAwarePaginator|Collection {
        $clave = self::claveCache($entidad->clave(), $filtros);
        $page = max(1, $page ?? (int) request()->input('page', 1));

        if ($forzarRefresh) {
            Cache::forget($clave);
        }

        $cached = Cache::get($clave);
        if ($cached instanceof Collection) {
            return $paginar
                ? self::paginarColeccion($cached, $page, self::PER_PAGE)
                : $cached;
        }

        if ($paginar) {
            $paginado = $entidad->consultar($filtros, true);
            if (
                $paginado instanceof LengthAwarePaginator
                && $page === 1
                && (int) $paginado->total() >= self::UMBRAL_FILAS
            ) {
                self::calentarCache($entidad, $filtros, $clave);
                $calentado = Cache::get($clave);
                if ($calentado instanceof Collection) {
                    return self::paginarColeccion($calentado, $page, self::PER_PAGE);
                }
            }

            return $paginado;
        }

        $filas = $entidad->consultar($filtros, false);
        $coleccion = $filas instanceof Collection ? $filas : collect($filas);
        $coleccion = $coleccion->take(self::MAX_CACHE_FILAS)->values();
        if ($coleccion->count() >= self::UMBRAL_FILAS) {
            Cache::put($clave, $coleccion, self::TTL_SEGUNDOS);
        }

        return $coleccion;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function invalidar(string $claveEntidad, array $filtros): void
    {
        Cache::forget(self::claveCache($claveEntidad, $filtros));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function calentarCache(EntidadSemantica $entidad, array $filtros, string $clave): void
    {
        try {
            $filas = $entidad->consultar($filtros, false);
            $coleccion = $filas instanceof Collection ? $filas : collect($filas);
            $coleccion = $coleccion->take(self::MAX_CACHE_FILAS)->values();
            if ($coleccion->count() >= self::UMBRAL_FILAS) {
                Cache::put($clave, $coleccion, self::TTL_SEGUNDOS);
            }
        } catch (\Throwable) {
            // No frenar la consulta por falla de cache.
        }
    }

    private static function paginarColeccion(Collection $coleccion, int $page, int $perPage): LengthAwarePaginator
    {
        $total = $coleccion->count();
        $items = $coleccion->forPage($page, $perPage)->values();

        return new Paginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function claveCache(string $claveEntidad, array $filtros): string
    {
        $firma = md5(json_encode(self::ordenar($filtros)) ?: '');

        return generaKey('ci-dataset-'.$claveEntidad.'-'.$firma);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function ordenar(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::ordenar($v);
            }
        }
        ksort($data);

        return $data;
    }
}
