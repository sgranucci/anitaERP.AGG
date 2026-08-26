<?php

declare(strict_types=1);

namespace App\Support\Archivos;

use Symfony\Component\HttpFoundation\Response;

/**
 * Evita que el navegador muestre un adjunto viejo cuando se pisa el archivo
 * (misma URL / mismo nombre). No cambia rutas ni el guardado.
 */
final class ArchivoAdjuntoCacheSupport
{
    /**
     * Versión para query string. Si no hay archivo, usa time() (mismo criterio UIF).
     */
    public static function versionCache(?string $absolutePath): string
    {
        if ($absolutePath !== null && $absolutePath !== '' && is_file($absolutePath)) {
            $mtime = @filemtime($absolutePath);
            if ($mtime !== false) {
                return (string) $mtime;
            }
        }

        return (string) time();
    }

    /**
     * @return array{v: string}
     */
    public static function queryVersion(?string $absolutePath): array
    {
        return ['v' => self::versionCache($absolutePath)];
    }

    /**
     * Agrega ?v=mtime solo si el archivo existe. No toca la URL si no hay archivo.
     */
    public static function conVersion(string $url, ?string $absolutePath): string
    {
        if ($url === '' || $absolutePath === null || $absolutePath === '' || ! is_file($absolutePath)) {
            return $url;
        }
        $mtime = @filemtime($absolutePath);
        if ($mtime === false) {
            return $url;
        }
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url.$sep.'v='.$mtime;
    }

    /**
     * URL pública /storage/... con versión si el archivo está en public/storage.
     */
    public static function urlStoragePublico(string $relativeUnderStorage): string
    {
        $relativeUnderStorage = ltrim(str_replace('\\', '/', $relativeUnderStorage), '/');
        $url = asset('storage/'.$relativeUnderStorage);
        if ($relativeUnderStorage === '' || str_contains($relativeUnderStorage, '..')) {
            return $url;
        }

        return self::conVersion($url, public_path('storage/'.$relativeUnderStorage));
    }

    /**
     * Laravel file() marca public + Last-Modified; el navegador cachea por días.
     *
     * @template T of Response
     *
     * @param  T  $response
     * @return T
     */
    public static function aplicarAntiCacheNavegador(Response $response): Response
    {
        $response->setPrivate();
        $response->headers->removeCacheControlDirective('public');
        $response->headers->addCacheControlDirective('private', true);
        $response->headers->addCacheControlDirective('no-cache', true);
        $response->headers->addCacheControlDirective('must-revalidate', true);
        $response->headers->addCacheControlDirective('max-age', '0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
