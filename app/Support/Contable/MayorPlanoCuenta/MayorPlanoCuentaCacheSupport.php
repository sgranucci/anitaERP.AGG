<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use Illuminate\Support\Facades\Log;

/**
 * Cache del mayor plano: secciones en archivos gzip independientes.
 *
 * Evita Cache::put / serialize del pack completo (pico ≈ 2× RAM del resultado).
 * Rangos largos (ene–ago) agotaban 1024M en FileStore::put.
 */
final class MayorPlanoCuentaCacheSupport
{
    private const TTL_SEGUNDOS = 14400; // 4 h

    private const VERSION = 'v6';

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function cacheKey(array $filtros, ?int $usuarioId = null): string
    {
        $userId = $usuarioId ?? (int) (auth()->id() ?? 0);

        return 'mayor_plano_cuenta_'.self::VERSION.'_'.$userId.'_'.MayorPlanoCuentaListadoFiltros::firma($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function dirPath(array $filtros, ?int $usuarioId = null): string
    {
        // Bajo cache/data (www-data ya escribe ahí). El dir suelto mayor_plano/ era 755 sergio y fallaba el mkdir.
        return storage_path('framework/cache/data/mayor_plano/'.self::cacheKey($filtros, $usuarioId));
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     */
    public static function guardar(array $resultado, array $filtros, ?int $usuarioId = null): bool
    {
        $firma = MayorPlanoCuentaListadoFiltros::firma($filtros);
        $dir = self::dirPath($filtros, $usuarioId);

        try {
            self::limpiarDirectorio($dir);
            $parent = dirname($dir);
            if (! is_dir($parent) && ! @mkdir($parent, 0775, true) && ! is_dir($parent)) {
                throw new \RuntimeException('No se pudo crear directorio padre de cache: '.$parent);
            }
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new \RuntimeException('No se pudo crear directorio de cache: '.$dir);
            }

            $secciones = $resultado['secciones'] ?? [];
            if (! is_array($secciones)) {
                $secciones = [];
            }

            $n = 0;
            foreach ($secciones as $seccion) {
                $bin = gzcompress(serialize($seccion), 6);
                if ($bin === false) {
                    throw new \RuntimeException('gzcompress falló en sección '.$n);
                }
                if (file_put_contents($dir.'/s'.sprintf('%05d', $n).'.gz', $bin) === false) {
                    throw new \RuntimeException('No se pudo escribir sección '.$n);
                }
                $n++;
            }

            $meta = $resultado;
            unset($meta['secciones']);
            $pack = [
                'firma' => $firma,
                'expires_at' => time() + self::TTL_SEGUNDOS,
                'seccion_count' => $n,
                'resultado' => $meta,
            ];
            $metaBin = gzcompress(serialize($pack), 6);
            if ($metaBin === false || file_put_contents($dir.'/meta.gz', $metaBin) === false) {
                throw new \RuntimeException('No se pudo escribir meta.gz');
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('MayorPlanoCuentaCache: no se pudo guardar', [
                'lineas' => (int) ($resultado['totales']['lineas'] ?? 0),
                'error' => $e->getMessage(),
            ]);
            self::limpiarDirectorio($dir);

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    public static function recuperar(array $filtros, ?int $usuarioId = null): ?array
    {
        $firma = MayorPlanoCuentaListadoFiltros::firma($filtros);
        $dir = self::dirPath($filtros, $usuarioId);
        $metaPath = $dir.'/meta.gz';

        if (! is_file($metaPath)) {
            return null;
        }

        try {
            $raw = @file_get_contents($metaPath);
            if ($raw === false) {
                return null;
            }
            $pack = @unserialize((string) gzuncompress($raw));
            if (! is_array($pack)
                || ($pack['firma'] ?? '') !== $firma
                || ! isset($pack['resultado'])
                || ! is_array($pack['resultado'])
            ) {
                return null;
            }

            $expiresAt = (int) ($pack['expires_at'] ?? 0);
            if ($expiresAt > 0 && $expiresAt < time()) {
                self::limpiarDirectorio($dir);

                return null;
            }

            $count = (int) ($pack['seccion_count'] ?? 0);
            $secciones = [];
            for ($i = 0; $i < $count; $i++) {
                $path = $dir.'/s'.sprintf('%05d', $i).'.gz';
                if (! is_file($path)) {
                    return null;
                }
                $secRaw = @file_get_contents($path);
                if ($secRaw === false) {
                    return null;
                }
                $seccion = @unserialize((string) gzuncompress($secRaw));
                if (! is_array($seccion)) {
                    return null;
                }
                $secciones[] = $seccion;
            }

            $resultado = $pack['resultado'];
            $resultado['secciones'] = $secciones;

            return $resultado;
        } catch (\Throwable $e) {
            Log::warning('MayorPlanoCuentaCache: no se pudo leer', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function olvidar(array $filtros): void
    {
        self::limpiarDirectorio(self::dirPath($filtros));
    }

    private static function limpiarDirectorio(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.'/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }
}
