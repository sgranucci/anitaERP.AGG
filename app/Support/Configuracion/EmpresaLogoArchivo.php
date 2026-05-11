<?php

namespace App\Support\Configuracion;

/**
 * Logos en public/storage/imagenes/logos/
 *
 * Reportes de listado (requisiciones, etc.):
 * - Más de una empresa distinta → solo logo default config('app.empresa').png (ej. AGG.png).
 * - Una sola empresa → mismo criterio que facturas de venta: {nombre comercial}.png
 *
 * Documento único (una cabecera): resolución con fallback a default si no existe PNG de empresa.
 */
final class EmpresaLogoArchivo
{
    private static function directorioLogos(): ?string
    {
        $dir = public_path('storage/imagenes/logos');

        return is_dir($dir) ? $dir : null;
    }

    /**
     * PNG por nombre de empresa (solo .png), igual que facturación: logos/{nombre}.png
     */
    public static function rutaPngEmpresa(?string $nombreEmpresa): ?string
    {
        $nombre = trim((string) $nombreEmpresa);
        if ($nombre === '') {
            return null;
        }

        $dir = self::directorioLogos();
        if (! $dir) {
            return null;
        }

        $base = basename(str_replace(['..', '\\', '/'], '', $nombre));
        $ruta = $dir.DIRECTORY_SEPARATOR.$base.'.png';

        return is_file($ruta) ? $ruta : null;
    }

    /**
     * PNG por defecto: config('app.empresa').png (ej. AGG.png).
     */
    public static function rutaPngDefault(): ?string
    {
        $slug = trim((string) config('app.empresa'));
        if ($slug === '') {
            return null;
        }

        $dir = self::directorioLogos();
        if (! $dir) {
            return null;
        }

        $base = basename(str_replace(['..', '\\', '/'], '', $slug));
        $ruta = $dir.DIRECTORY_SEPARATOR.$base.'.png';

        return is_file($ruta) ? $ruta : null;
    }

    /**
     * Una cabecera: PNG empresa o, si no existe, default app (comportamiento PDF requisición individual).
     */
    public static function rutaResuelta(?string $nombreEmpresa): ?string
    {
        $especifica = self::rutaPngEmpresa($nombreEmpresa);
        if ($especifica !== null) {
            return $especifica;
        }

        return self::rutaPngDefault();
    }

    /**
     * @return array{uri: string, mime: string, path: string}|null
     */
    public static function dataUriDesdeNombre(?string $nombreEmpresa): ?array
    {
        $ruta = self::rutaResuelta($nombreEmpresa);
        if (! $ruta) {
            return null;
        }

        $data = @file_get_contents($ruta);
        if ($data === false || $data === '') {
            return null;
        }

        $mime = 'image/png';

        return [
            'path' => $ruta,
            'mime' => $mime,
            'uri' => 'data:'.$mime.';base64,'.base64_encode($data),
        ];
    }

    /**
     * Nombres de empresa distintos en el reporte (campo nombreempresa).
     *
     * @param  \Illuminate\Support\Collection|\Traversable|array<int, object>  $registros
     * @return list<string>
     */
    public static function nombresEmpresaDistintos($registros): array
    {
        $nombres = [];
        foreach ($registros as $row) {
            $n = is_object($row) ? ($row->nombreempresa ?? null) : ($row['nombreempresa'] ?? null);
            $n = trim((string) $n);
            if ($n !== '') {
                $nombres[$n] = true;
            }
        }

        return array_keys($nombres);
    }

    /**
     * Logos para cabecera de listados PDF.
     *
     * @param  \Illuminate\Support\Collection|\Traversable|array<int, object>  $registros
     * @return list<array{nombre: string, uri: string, mime: string}>
     */
    public static function logosCabeceraDesdeColeccion($registros): array
    {
        $distintos = self::nombresEmpresaDistintos($registros);
        $n = count($distintos);

        if ($n === 0) {
            $def = self::buildDataUriFromPath(self::rutaPngDefault());

            return $def !== null
                ? [['nombre' => (string) config('app.empresa'), 'uri' => $def['uri'], 'mime' => $def['mime']]]
                : [];
        }

        if ($n > 1) {
            $def = self::buildDataUriFromPath(self::rutaPngDefault());

            return $def !== null
                ? [['nombre' => (string) config('app.empresa'), 'uri' => $def['uri'], 'mime' => $def['mime']]]
                : [];
        }

        // Una sola empresa: mismo archivo que facturas — {nombre}.png
        $nombreUnico = $distintos[0];
        $ruta = self::rutaPngEmpresa($nombreUnico);
        if ($ruta === null) {
            $def = self::buildDataUriFromPath(self::rutaPngDefault());

            return $def !== null
                ? [['nombre' => (string) config('app.empresa'), 'uri' => $def['uri'], 'mime' => $def['mime']]]
                : [];
        }

        $dat = self::buildDataUriFromPath($ruta);
        if ($dat === null) {
            return [];
        }

        return [
            [
                'nombre' => $nombreUnico,
                'uri' => $dat['uri'],
                'mime' => $dat['mime'],
            ],
        ];
    }

    /**
     * Rutas físicas PNG para Excel (Drawing); misma regla que logosCabeceraDesdeColeccion.
     *
     * @param  \Illuminate\Support\Collection|\Traversable|array<int, object>  $registros
     * @return list<string>
     */
    public static function rutasLogosCabeceraDesdeColeccion($registros): array
    {
        $distintos = self::nombresEmpresaDistintos($registros);
        $n = count($distintos);

        if ($n === 0) {
            $def = self::rutaPngDefault();

            return ($def !== null && is_file($def)) ? [$def] : [];
        }

        if ($n > 1) {
            $def = self::rutaPngDefault();

            return ($def !== null && is_file($def)) ? [$def] : [];
        }

        $ruta = self::rutaPngEmpresa($distintos[0]);
        if ($ruta !== null && is_file($ruta)) {
            return [$ruta];
        }

        $def = self::rutaPngDefault();

        return ($def !== null && is_file($def)) ? [$def] : [];
    }

    /**
     * @return array{uri: string, mime: string}|null
     */
    private static function buildDataUriFromPath(?string $ruta): ?array
    {
        if (! $ruta || ! is_file($ruta)) {
            return null;
        }

        $data = @file_get_contents($ruta);
        if ($data === false || $data === '') {
            return null;
        }

        $mime = 'image/png';

        return [
            'mime' => $mime,
            'uri' => 'data:'.$mime.';base64,'.base64_encode($data),
        ];
    }
}
