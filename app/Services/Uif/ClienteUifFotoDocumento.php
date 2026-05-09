<?php

namespace App\Services\Uif;

use App\Models\Uif\Cliente_Archivo_Uif;
use App\Repositories\Uif\AnitaUifArchivosSync;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class ClienteUifFotoDocumento
{
    public const LEGACY_PUBLIC_SUBDIR = 'imagenes/fotos_documentos_uif';

    public static function basePath(): string
    {
        return rtrim(config('uif.FOTOS_CLIENTES_PATH', '/scan/tesoreria/fotos_clientes'), DIRECTORY_SEPARATOR);
    }

    /**
     * Raíz donde Anita guarda las imágenes de DNI (no los demás adjuntos UIF).
     */
    public static function anitaDniMount(): string
    {
        $cfg = config('uif.anita_uif_archivos', []);

        return rtrim((string) ($cfg['dni_mount'] ?? ''), DIRECTORY_SEPARATOR);
    }

    public static function sanitizeNumeroDocumento(string $numerodocumento): string
    {
        return preg_replace('/[^0-9A-Za-z]/', '', $numerodocumento) ?? '';
    }

    public static function buildFilenameForUpload(UploadedFile $file, string $numerodocumento): string
    {
        $base = self::sanitizeNumeroDocumento($numerodocumento);
        if ($base === '') {
            throw new \InvalidArgumentException('El número de documento es necesario para guardar la foto del DNI.');
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $ext = 'jpg';
        }

        return $base.'.'.$ext;
    }

    public static function ensureDirectoryExists(): void
    {
        $path = self::basePath();
        if ($path !== '' && ! is_dir($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    public static function absolutePathForBasename(?string $fotodocumento): ?string
    {
        if ($fotodocumento === null || $fotodocumento === '') {
            return null;
        }
        $base = basename($fotodocumento);
        $primary = self::basePath().DIRECTORY_SEPARATOR.$base;
        if (is_file($primary)) {
            return $primary;
        }
        $legacy = public_path('storage'.DIRECTORY_SEPARATOR.self::LEGACY_PUBLIC_SUBDIR.DIRECTORY_SEPARATOR.$base);
        if (is_file($legacy)) {
            return $legacy;
        }

        // Mismo archivo vía storage/app/public si el enlace public/storage no existe o falla.
        $storagePublic = storage_path('app/public'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::LEGACY_PUBLIC_SUBDIR).DIRECTORY_SEPARATOR.$base);
        if (is_file($storagePublic)) {
            return $storagePublic;
        }

        $dniMount = self::anitaDniMount();
        if ($dniMount !== '') {
            $flat = $dniMount.DIRECTORY_SEPARATOR.$base;
            if (is_file($flat)) {
                return $flat;
            }
            foreach (glob($dniMount.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.$base, GLOB_NOSORT) ?: [] as $p) {
                if (is_file($p)) {
                    return $p;
                }
            }
        }

        return null;
    }

    public static function deleteStoredFile(?string $fotodocumento): void
    {
        if ($fotodocumento === null || $fotodocumento === '') {
            return;
        }
        $base = basename($fotodocumento);
        foreach ([
            self::basePath().DIRECTORY_SEPARATOR.$base,
            public_path('storage'.DIRECTORY_SEPARATOR.self::LEGACY_PUBLIC_SUBDIR.DIRECTORY_SEPARATOR.$base),
        ] as $p) {
            if (is_file($p)) {
                @unlink($p);
            }
        }
        $dniMount = self::anitaDniMount();
        if ($dniMount !== '') {
            $flat = $dniMount.DIRECTORY_SEPARATOR.$base;
            if (is_file($flat)) {
                @unlink($flat);
            }
            foreach (glob($dniMount.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.$base, GLOB_NOSORT) ?: [] as $p) {
                if (is_file($p)) {
                    @unlink($p);
                }
            }
        }
    }

    /**
     * Primera ruta absoluta a una foto del DNI: legacy ({@see basePath()}) y carpetas del montaje Anita.
     *
     * @param  int|null  $inroclienteid  ID cliente en Anita (carpetas clientes_uif/000123/…)
     */
    public static function findFirstMatchingPath(string $numerodocumento, ?int $inroclienteid = null): ?string
    {
        $stem = self::sanitizeNumeroDocumento($numerodocumento);
        if ($stem === '') {
            return null;
        }

        $dir = self::basePath();
        if ($dir !== '' && is_dir($dir)) {
            foreach (glob($dir.DIRECTORY_SEPARATOR.$stem.'.*') ?: [] as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        $dniMount = self::anitaDniMount();
        if ($dniMount !== '' && is_dir($dniMount)) {
            foreach (glob($dniMount.DIRECTORY_SEPARATOR.$stem.'.*') ?: [] as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }
        }
        if ($dniMount !== '' && $inroclienteid !== null && $inroclienteid > 0) {
            foreach (AnitaUifArchivosSync::directoriosCandidatosCliente($dniMount, $inroclienteid) as $sub) {
                if (! is_dir($sub)) {
                    continue;
                }
                foreach (glob($sub.DIRECTORY_SEPARATOR.$stem.'.*') ?: [] as $path) {
                    if (is_file($path)) {
                        return $path;
                    }
                }
            }
        }

        $cfg = config('uif.anita_uif_archivos', []);
        $mount = rtrim((string) ($cfg['mount'] ?? ''), '/');
        if ($mount !== '' && $inroclienteid !== null && $inroclienteid > 0) {
            foreach (AnitaUifArchivosSync::directoriosCandidatosCliente($mount, $inroclienteid) as $sub) {
                if (! is_dir($sub)) {
                    continue;
                }
                foreach (glob($sub.DIRECTORY_SEPARATOR.$stem.'.*') ?: [] as $path) {
                    if (is_file($path)) {
                        return $path;
                    }
                }
            }
        }

        // Primero carpetas dedicadas a DNI; luego el montaje general de adjuntos.
        $bestDni = self::findBestImageInClienteMountRoot($inroclienteid, $numerodocumento, $dniMount);
        if ($bestDni !== null) {
            return $bestDni;
        }

        $bestMount = self::findBestImageInClienteMountRoot($inroclienteid, $numerodocumento, $mount);
        if ($bestMount !== null) {
            return $bestMount;
        }

        return null;
    }

    /**
     * Elige una imagen en el montaje UIF del cliente (puntuación por nombre de archivo).
     */
    public static function findBestImageInMountClienteDirs(?int $inroclienteid, string $numerodocumento): ?string
    {
        $cfg = config('uif.anita_uif_archivos', []);
        $mount = rtrim((string) ($cfg['mount'] ?? ''), '/');

        return self::findBestImageInClienteMountRoot($inroclienteid, $numerodocumento, $mount);
    }

    /**
     * @param  string  $mount  Raíz del árbol (ej. dni_uif o archivos UIF).
     */
    public static function findBestImageInClienteMountRoot(?int $inroclienteid, string $numerodocumento, string $mount): ?string
    {
        $mount = rtrim($mount, '/');
        if ($mount === '' || $inroclienteid === null || $inroclienteid <= 0) {
            return null;
        }

        $stem = strtolower(self::sanitizeNumeroDocumento($numerodocumento));
        $ranked = [];

        foreach (AnitaUifArchivosSync::directoriosCandidatosCliente($mount, $inroclienteid) as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $path) {
                if (! is_file($path)) {
                    continue;
                }
                $e = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
                if (! in_array($e, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    continue;
                }
                $bn = strtolower(basename($path));
                $score = 0;
                if ($stem !== '' && str_contains($bn, $stem)) {
                    $score += 100;
                }
                foreach (['dni', 'documento', 'frente', 'verso'] as $hint) {
                    if (str_contains($bn, $hint)) {
                        $score += 15;
                    }
                }
                foreach (['foto', 'scan', 'img', 'cli'] as $hint) {
                    if (str_contains($bn, $hint)) {
                        $score += 5;
                    }
                }
                $ranked[] = ['path' => $path, 'score' => $score, 'bn' => $bn];
            }
        }

        if ($ranked === []) {
            return null;
        }

        usort($ranked, function ($a, $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            return $a['bn'] <=> $b['bn'];
        });

        return $ranked[0]['path'];
    }

    /**
     * Tras {@see Cliente_Archivo_UifRepository::traerArchivosDeAnita}, copia la mejor imagen adjunta a la carpeta de foto DNI.
     */
    public static function copyFirstClienteAdjuntoImageToFotodocumento(int $clienteUifId, string $numerodocumento): ?string
    {
        $stem = strtolower(self::sanitizeNumeroDocumento($numerodocumento));
        $rows = Cliente_Archivo_Uif::query()
            ->where('cliente_uif_id', $clienteUifId)
            ->orderBy('id')
            ->get(['nombrearchivo']);

        $ranked = [];
        foreach ($rows as $row) {
            $n = (string) $row->nombrearchivo;
            if ($n === '' || ! preg_match('/\.(jpe?g|png|gif|webp)$/i', $n)) {
                continue;
            }
            $src = public_path('storage/archivos/clientes_uif/'.$clienteUifId.'/'.$n);
            if (! is_file($src)) {
                continue;
            }
            $bn = strtolower(basename($n));
            $score = 0;
            if ($stem !== '' && str_contains($bn, $stem)) {
                $score += 100;
            }
            foreach (['dni', 'documento', 'frente', 'verso'] as $hint) {
                if (str_contains($bn, $hint)) {
                    $score += 15;
                }
            }
            $ranked[] = ['nombre' => $n, 'src' => $src, 'score' => $score, 'bn' => $bn];
        }

        if ($ranked === []) {
            return null;
        }

        usort($ranked, function ($a, $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            return $a['bn'] <=> $b['bn'];
        });

        $pick = $ranked[0];
        $destDir = public_path('storage'.DIRECTORY_SEPARATOR.self::LEGACY_PUBLIC_SUBDIR);
        if (! is_dir($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        // nombrearchivo ya viene como "{id}-{original}" desde traerArchivosDeAnita.
        $destName = basename($pick['nombre']);
        $destPath = $destDir.DIRECTORY_SEPARATOR.$destName;
        if (! @copy($pick['src'], $destPath)) {
            return null;
        }

        return $destName;
    }

    /**
     * Copia la foto encontrada a storage público (misma convención que la carga manual: id-nombreorig.ext).
     */
    public static function importAndStorePublic(string $numerodocumento, ?int $inroclienteid, int $clienteUifId): ?string
    {
        $src = self::findFirstMatchingPath($numerodocumento, $inroclienteid);
        if ($src === null || ! is_file($src)) {
            return null;
        }

        $destDir = public_path('storage'.DIRECTORY_SEPARATOR.self::LEGACY_PUBLIC_SUBDIR);
        if (! is_dir($destDir)) {
            File::makeDirectory($destDir, 0755, true);
        }

        $destName = $clienteUifId.'-'.basename($src);
        $destPath = $destDir.DIRECTORY_SEPARATOR.$destName;
        if (! @copy($src, $destPath)) {
            return null;
        }

        return $destName;
    }

    /**
     * Busca un archivo existente en el directorio configurado (mismo criterio que el sistema legacy anita).
     *
     * @deprecated Preferir {@see findFirstMatchingPath} con ID Anita para incluir el montaje UIF.
     */
    public static function detectFilenameOnDisk(string $numerodocumento): ?string
    {
        $path = self::findFirstMatchingPath($numerodocumento, null);

        return $path !== null ? basename($path) : null;
    }

    /**
     * Si cambió el número de documento, renombra el archivo en disco manteniendo la extensión.
     */
    public static function renameIfDocNumberChanged(
        string $oldNumerodocumento,
        string $newNumerodocumento,
        ?string $currentBasename
    ): ?string {
        if ($currentBasename === null || $currentBasename === '') {
            return null;
        }
        $oldStem = self::sanitizeNumeroDocumento($oldNumerodocumento);
        $newStem = self::sanitizeNumeroDocumento($newNumerodocumento);
        if ($oldStem === '' || $newStem === '' || $oldStem === $newStem) {
            return null;
        }
        $oldPath = self::absolutePathForBasename($currentBasename);
        if ($oldPath === null || ! is_file($oldPath)) {
            return null;
        }
        $ext = pathinfo($oldPath, PATHINFO_EXTENSION);
        $newName = $newStem.'.'.$ext;
        $newPath = self::basePath().DIRECTORY_SEPARATOR.$newName;
        if (is_file($newPath)) {
            return null;
        }
        self::ensureDirectoryExists();
        if (@rename($oldPath, $newPath)) {
            return $newName;
        }

        return null;
    }
}
