<?php

namespace App\Support\Cache;

/**
 * Cache de permisos por rol: tags cuando el store los soporta (Redis),
 * fallback sin tags (file/array) para lab PG / entornos sin Redis.
 */
final class PermisoCacheSupport
{
    /**
     * @param  callable(): list<string>  $callback
     * @return list<string>
     */
    public static function rememberSlugsPorRol(int|string|null $rolId, callable $callback): array
    {
        $rolIdNorm = is_numeric($rolId) ? (int) $rolId : 0;
        if ($rolIdNorm <= 0) {
            return [];
        }

        $key = 'Permiso.rolid.'.$rolIdNorm;
        $resolver = static function () use ($callback): array {
            /** @var list<string> $permisos */
            $permisos = $callback();

            return array_values(array_filter(array_map('strval', $permisos)));
        };

        $cache = cache();
        if ($cache->supportsTags()) {
            /** @var list<string> $permisos */
            $permisos = $cache->tags('Permiso')->get($key);
            if (is_array($permisos) && $permisos !== []) {
                return $permisos;
            }
            $permisos = $resolver();
            if ($permisos !== []) {
                $cache->tags('Permiso')->forever($key, $permisos);
            }

            return $permisos;
        }

        /** @var list<string>|null $cached */
        $cached = $cache->get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }
        $permisos = $resolver();
        if ($permisos !== []) {
            $cache->forever($key, $permisos);
        }

        return $permisos;
    }

    public static function forgetRol(int|string|null $rolId): void
    {
        $rolIdNorm = is_numeric($rolId) ? (int) $rolId : 0;
        if ($rolIdNorm <= 0) {
            return;
        }

        $key = 'Permiso.rolid.'.$rolIdNorm;
        $cache = cache();
        if ($cache->supportsTags()) {
            $cache->tags('Permiso')->forget($key);
        }
        $cache->forget($key);
    }

    public static function flush(): void
    {
        $cache = cache();
        if ($cache->supportsTags()) {
            $cache->tags('Permiso')->flush();

            return;
        }

        // Sin tags: no flush global (evita borrar todo el store en lab/file).
    }
}
