<?php

namespace App\Support\Configuracion;

/**
 * Logos de empresa para facturas, remitos y listados.
 *
 * Directorios (en orden de prioridad):
 * 1. public/assets/img/empresa/ — override local (p. ej. Ferli desplegable sin tocar CIFS)
 * 2. public/storage/imagenes/logos/ — storage compartido histórico
 *
 * Acepta PNG y JPG/JPEG (prioridad: .png → .jpg → .jpeg).
 *
 * Reportes de listado (requisiciones, etc.):
 * - Más de una empresa distinta → solo logo default config('app.empresa').
 * - Una sola empresa → mismo criterio que facturas de venta: {nombre comercial}.
 *
 * Documento único (una cabecera): resolución con fallback a default si no existe logo de empresa.
 */
final class EmpresaLogoArchivo
{
    /** @var list<string> */
    private const EXTENSIONES = ['png', 'jpg', 'jpeg'];

    /**
     * @return list<string>
     */
    private static function directoriosLogos(): array
    {
        $dirs = [];
        foreach ([
            public_path('assets/img/empresa'),
            public_path('storage/imagenes/logos'),
        ] as $dir) {
            if (is_dir($dir)) {
                $dirs[] = $dir;
            }
        }

        return $dirs;
    }

    /**
     * Logo por nombre de empresa: logos/{nombre}.png|.jpg|.jpeg
     * (nombre histórico rutaPngEmpresa; también resuelve JPG).
     */
    public static function rutaPngEmpresa(?string $nombreEmpresa): ?string
    {
        return self::rutaLogoEmpresa($nombreEmpresa);
    }

    public static function rutaLogoEmpresa(?string $nombreEmpresa): ?string
    {
        $nombre = trim((string) $nombreEmpresa);
        if ($nombre === '') {
            return null;
        }

        $dirs = self::directoriosLogos();
        if ($dirs === []) {
            return null;
        }

        $base = self::baseArchivoSeguro($nombre);
        foreach ($dirs as $dir) {
            $ruta = self::primeraConExtension($dir, $base);
            if ($ruta !== null) {
                return $ruta;
            }
        }

        foreach (self::aliasLogoEmpresa($nombre) as $archivo) {
            $rutaAlias = self::primeraRutaArchivoEnDirectorios($dirs, $archivo);
            if ($rutaAlias !== null) {
                return $rutaAlias;
            }
        }

        return null;
    }

    /**
     * Logo por defecto: config('app.empresa').png|.jpg|.jpeg
     * Si no existe, prueba alias conocidos (ej. EL BIERZO, Ferli).
     * (nombre histórico rutaPngDefault; también resuelve JPG).
     */
    public static function rutaPngDefault(): ?string
    {
        return self::rutaLogoDefault();
    }

    public static function rutaLogoDefault(): ?string
    {
        $slug = trim((string) config('app.empresa'));
        if ($slug === '') {
            return null;
        }

        $dirs = self::directoriosLogos();
        if ($dirs === []) {
            return null;
        }

        $base = self::baseArchivoSeguro($slug);
        foreach ($dirs as $dir) {
            $ruta = self::primeraConExtension($dir, $base);
            if ($ruta !== null) {
                return $ruta;
            }
        }

        foreach (self::aliasLogoDefault($base) as $archivo) {
            $rutaAlias = self::primeraRutaArchivoEnDirectorios($dirs, $archivo);
            if ($rutaAlias !== null) {
                return $rutaAlias;
            }
        }

        return null;
    }

    /**
     * Archivos alternativos cuando config('app.empresa').{png,jpg} no existe.
     *
     * @return list<string>
     */
    private static function aliasLogoDefault(string $slugEmpresa): array
    {
        $slug = strtoupper(trim($slugEmpresa));

        return match (true) {
            $slug === 'EL BIERZO' => ['logo-bierzo.png', 'Frig.El Bierzo SA.png'],
            $slug === 'INTERFORMING' => ['INTERFORMING S.A.png', 'INTERFORMING S.A..png'],
            self::esSlugFerli($slug) => self::archivosLogoFerli(),
            default => [],
        };
    }

    /**
     * Alias cuando el nombre comercial de la tabla empresa no coincide con el archivo.
     *
     * @return list<string>
     */
    private static function aliasLogoEmpresa(string $nombreEmpresa): array
    {
        $nombre = strtoupper(trim($nombreEmpresa));
        if (self::esSlugFerli($nombre) || str_contains($nombre, 'FERLI')) {
            return self::archivosLogoFerli();
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private static function archivosLogoFerli(): array
    {
        return [
            'logoFerli.jpg',
            'logo_ferli.jpg',
            'logoFerli.jpeg',
            'logoFerli.png',
            'CALZADOS FERLI S.A.png',
            'CALZADOS FERLI S.A.jpg',
            'Calzados Ferli.png',
            'Calzados Ferli.jpg',
        ];
    }

    private static function esSlugFerli(string $slugUpper): bool
    {
        $n = strtoupper(trim($slugUpper));

        return $n === 'CALZADOS FERLI'
            || $n === 'CALZADOS FERLI S.A.'
            || $n === 'CALZADOS FERLI S.A'
            || $n === 'C.FERLI SA'
            || $n === 'C.FERLI S.A.'
            || $n === EntornoEmpresaSupport::FERLI
            || str_contains($n, 'FERLI');
    }

    /**
     * Una cabecera: logo empresa o, si no existe, default app.
     */
    public static function rutaResuelta(?string $nombreEmpresa): ?string
    {
        $especifica = self::rutaLogoEmpresa($nombreEmpresa);
        if ($especifica !== null) {
            return $especifica;
        }

        return self::rutaLogoDefault();
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

        $dat = self::buildDataUriFromPath($ruta);
        if ($dat === null) {
            return null;
        }

        return [
            'path' => $ruta,
            'mime' => $dat['mime'],
            'uri' => $dat['uri'],
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

        if ($n === 0 || $n > 1) {
            $def = self::buildDataUriFromPath(self::rutaLogoDefault());

            return $def !== null
                ? [['nombre' => (string) config('app.empresa'), 'uri' => $def['uri'], 'mime' => $def['mime']]]
                : [];
        }

        $nombreUnico = $distintos[0];
        $ruta = self::rutaLogoEmpresa($nombreUnico);
        if ($ruta === null) {
            $def = self::buildDataUriFromPath(self::rutaLogoDefault());

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
     * Rutas físicas para Excel (Drawing); misma regla que logosCabeceraDesdeColeccion.
     *
     * @param  \Illuminate\Support\Collection|\Traversable|array<int, object>  $registros
     * @return list<string>
     */
    public static function rutasLogosCabeceraDesdeColeccion($registros): array
    {
        $distintos = self::nombresEmpresaDistintos($registros);
        $n = count($distintos);

        if ($n === 0 || $n > 1) {
            $def = self::rutaLogoDefault();

            return ($def !== null && is_file($def)) ? [$def] : [];
        }

        $ruta = self::rutaLogoEmpresa($distintos[0]);
        if ($ruta !== null && is_file($ruta)) {
            return [$ruta];
        }

        $def = self::rutaLogoDefault();

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

        $mime = self::mimeDesdeRuta($ruta);

        return [
            'mime' => $mime,
            'uri' => 'data:'.$mime.';base64,'.base64_encode($data),
        ];
    }

    private static function mimeDesdeRuta(string $ruta): string
    {
        $ext = strtolower((string) pathinfo($ruta, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    private static function baseArchivoSeguro(string $nombre): string
    {
        return basename(str_replace(['..', '\\', '/'], '', $nombre));
    }

    private static function primeraConExtension(string $dir, string $baseSinExt): ?string
    {
        foreach (self::EXTENSIONES as $ext) {
            $ruta = $dir.DIRECTORY_SEPARATOR.$baseSinExt.'.'.$ext;
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $dirs
     */
    private static function primeraRutaArchivoEnDirectorios(array $dirs, string $archivo): ?string
    {
        $archivoSeguro = basename(str_replace(['..', '\\', '/'], '', $archivo));
        if ($archivoSeguro === '') {
            return null;
        }

        foreach ($dirs as $dir) {
            $ruta = $dir.DIRECTORY_SEPARATOR.$archivoSeguro;
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }
}
