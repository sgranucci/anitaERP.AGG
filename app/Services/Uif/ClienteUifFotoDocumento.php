<?php

namespace App\Services\Uif;

use App\Models\Uif\Cliente_Archivo_Uif;
use App\Repositories\Uif\AnitaUifArchivosSync;
use App\Support\Uif\ClienteUifArchivoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class ClienteUifFotoDocumento
{
    public const LEGACY_PUBLIC_SUBDIR = 'imagenes/fotos_documentos_uif';

    public static function basePath(): string
    {
        // Con withOrigen() escribe en fotos_clientes / _KSA / _RSA según la PC.
        $desdeOrigen = ClienteUifArchivoStorage::dirFotosPremio();
        if ($desdeOrigen !== '') {
            return $desdeOrigen;
        }

        return rtrim((string) config('uif.FOTOS_CLIENTES_PATH', '/scan/tesoreria/fotos_clientes'), DIRECTORY_SEPARATOR);
    }

    public static function fallbackPath(): string
    {
        return rtrim((string) config('uif.FOTOS_CLIENTES_PATH_FALLBACK', storage_path('app/uif/fotos_clientes')), DIRECTORY_SEPARATOR);
    }

    /**
     * Comprueba que el directorio admita crear y borrar un archivo (más fiable que is_writable en NFS/montajes).
     */
    public static function directoryAcceptsUploads(string $dir): bool
    {
        if ($dir === '' || ! is_dir($dir) || ! is_writable($dir)) {
            return false;
        }

        $probe = $dir.DIRECTORY_SEPARATOR.'.uif_upload_probe_'.uniqid('', true);
        if (@file_put_contents($probe, '1') === false) {
            return false;
        }

        if (! @unlink($probe)) {
            @unlink($probe);
        }

        return true;
    }

    public static function phpProcessUser(): string
    {
        if (! function_exists('posix_geteuid') || ! function_exists('posix_getpwuid')) {
            return 'php';
        }

        $info = posix_getpwuid(posix_geteuid());

        return is_array($info) ? (string) ($info['name'] ?? 'php') : 'php';
    }

    public static function ensurePrimaryWritePathReady(): string
    {
        $path = self::basePath();
        if ($path === '') {
            throw new \RuntimeException('UIF_FOTOS_CLIENTES_PATH no está configurado.');
        }

        if (! is_dir($path)) {
            File::makeDirectory($path, 0777, true, true);
        }

        if (! self::directoryAcceptsUploads($path)) {
            throw new \RuntimeException(
                'No se puede escribir en '.$path.' (usuario PHP: '.self::phpProcessUser().'). '
                .'El montaje /scan debe permitir escritura a www-data (chown, ACL o opciones NFS/Samba). '
                .'No se guardan fotos en el disco del servidor web salvo UIF_FOTOS_CLIENTES_PERMITIR_FALLBACK_ESCRITURA=true.'
            );
        }

        return $path;
    }

    public static function ensureFallbackReady(): string
    {
        $fallback = self::fallbackPath();
        if ($fallback === '') {
            throw new \RuntimeException('No está configurada UIF_FOTOS_CLIENTES_PATH_FALLBACK.');
        }

        if (! is_dir($fallback)) {
            File::makeDirectory($fallback, 0755, true, true);
        }

        if (! self::directoryAcceptsUploads($fallback)) {
            throw new \RuntimeException('No se puede escribir en el directorio de respaldo: '.$fallback);
        }

        return $fallback;
    }

    /**
     * Directorio de grabación: siempre {@see basePath()} (/scan) salvo fallback explícito en config.
     */
    public static function writableBasePath(): string
    {
        return self::ensurePrimaryWritePathReady();
    }

    /**
     * Rutas donde buscar fotos ya existentes (montaje + fallback + legacy).
     *
     * @return array<int, string>
     */
    public static function storagePathsForRead(): array
    {
        $paths = array_filter(array_merge(
            ClienteUifArchivoStorage::dirsFotosPremioTodos(),
            [
                self::basePath(),
                self::fallbackPath(),
            ]
        ));

        return array_values(array_unique($paths));
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
        self::writableBasePath();
    }

    public static function legacyPublicDir(): string
    {
        return public_path('storage'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::LEGACY_PUBLIC_SUBDIR));
    }

    public static function legacyStoragePublicDir(): string
    {
        return storage_path('app/public'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::LEGACY_PUBLIC_SUBDIR));
    }

    /**
     * Prefijos de nombre usados al migrar desde Anita (ej. `1-171784` además de `171784`).
     *
     * @return array<int, string>
     */
    public static function legacyBasenameStems(?int $inroclienteid, string $numerodocumento): array
    {
        $stem = self::sanitizeNumeroDocumento($numerodocumento);
        if ($stem === '') {
            return [];
        }
        $stems = [$stem];
        if ($inroclienteid !== null && $inroclienteid > 0) {
            $stems[] = $inroclienteid.'-'.$stem;
        }

        return array_values(array_unique($stems));
    }

    /**
     * @param  array<int, string>  $stems
     */
    public static function findFirstFileByStemGlob(string $dir, array $stems): ?string
    {
        if ($dir === '' || ! is_dir($dir)) {
            return null;
        }
        foreach ($stems as $stem) {
            foreach (glob($dir.DIRECTORY_SEPARATOR.$stem.'.*', GLOB_NOSORT) ?: [] as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * Resuelve la ruta absoluta de la foto DNI usando el basename en BD y, si falla,
     * el mismo criterio de búsqueda que la sincronización desde Anita.
     */
    public static function absolutePathForCliente(?string $fotodocumento, string $numerodocumento, ?int $inroclienteid = null): ?string
    {
        if ($fotodocumento !== null && $fotodocumento !== '') {
            $path = self::absolutePathForBasename($fotodocumento);
            if ($path !== null) {
                return $path;
            }
        }

        return self::findFirstMatchingPath($numerodocumento, $inroclienteid);
    }

    /**
     * Guarda un upload en {@see basePath()} con nombre `{numerodocumento_sanitizado}.{ext}`.
     * Si ya había otra foto distinta en BD, intenta eliminarla del almacén propio (no montajes externos).
     */
    public static function storeUploadedFile(UploadedFile $file, string $numerodocumento, ?string $previousBasename = null): string
    {
        $basename = self::buildFilenameForUpload($file, $numerodocumento);
        if ($previousBasename !== null && $previousBasename !== '' && basename($previousBasename) !== $basename) {
            self::deleteStoredFile($previousBasename);
        }

        $primary = self::ensurePrimaryWritePathReady();
        $saved = self::persistUploadToDirectory($file, $primary, $basename);
        if ($saved) {
            return $basename;
        }

        if (config('uif.FOTOS_CLIENTES_PERMITIR_FALLBACK_ESCRITURA')) {
            $fallback = self::ensureFallbackReady();
            if (self::persistUploadToDirectory($file, $fallback, $basename)) {
                return $basename;
            }
        }

        throw new \RuntimeException(
            'No se pudo guardar la foto del DNI en '.$primary
            .' (usuario PHP: '.self::phpProcessUser().'). '
            .'Revise permisos de escritura en el montaje /scan para www-data.'
        );
    }

    /**
     * Guarda el upload en $dir usando move_uploaded_file (preferido) o copy.
     */
    public static function persistUploadToDirectory(UploadedFile $file, string $dir, string $basename): bool
    {
        $dest = $dir.DIRECTORY_SEPARATOR.$basename;
        $tmp = $file->getPathname();

        if ($tmp !== '' && is_uploaded_file($tmp) && @move_uploaded_file($tmp, $dest)) {
            @chmod($dest, 0664);

            return true;
        }

        $src = $file->getRealPath();
        if ($src !== false && is_readable($src) && @copy($src, $dest)) {
            @chmod($dest, 0664);

            return true;
        }

        try {
            $moved = $file->move($dir, $basename);
            if ($moved->isFile()) {
                @chmod($dest, 0664);

                return true;
            }
        } catch (\Throwable $e) {
            // Sin fallback silencioso: el llamador informa error sobre /scan
        }

        return false;
    }

    public static function absolutePathForBasename(?string $fotodocumento): ?string
    {
        if ($fotodocumento === null || $fotodocumento === '') {
            return null;
        }
        $base = basename($fotodocumento);
        foreach (self::storagePathsForRead() as $dir) {
            $candidate = $dir.DIRECTORY_SEPARATOR.$base;
            if (is_file($candidate)) {
                return $candidate;
            }
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
        foreach (self::storagePathsForRead() as $dir) {
            $p = $dir.DIRECTORY_SEPARATOR.$base;
            if (is_file($p)) {
                @unlink($p);
            }
        }
        $legacy = public_path('storage'.DIRECTORY_SEPARATOR.self::LEGACY_PUBLIC_SUBDIR.DIRECTORY_SEPARATOR.$base);
        if (is_file($legacy)) {
            @unlink($legacy);
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

        foreach (self::storagePathsForRead() as $dir) {
            if ($dir === '' || ! is_dir($dir)) {
                continue;
            }
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

        $legacyStems = self::legacyBasenameStems($inroclienteid, $numerodocumento);
        foreach ([self::legacyPublicDir(), self::legacyStoragePublicDir()] as $legacyDir) {
            $legacyPath = self::findFirstFileByStemGlob($legacyDir, $legacyStems);
            if ($legacyPath !== null) {
                return $legacyPath;
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
        $stem = self::sanitizeNumeroDocumento($numerodocumento);
        $stemLower = strtolower($stem);
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
            $src = ClienteUifArchivoStorage::absoluteClienteAdjunto($clienteUifId, $n);
            if ($src === null || ! is_file($src)) {
                continue;
            }
            $bn = strtolower(basename($n));
            $score = 0;
            if ($stemLower !== '' && str_contains($bn, $stemLower)) {
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
        if ($stem === '') {
            return null;
        }
        $ext = strtolower((string) pathinfo($pick['src'], PATHINFO_EXTENSION));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return null;
        }

        self::ensureDirectoryExists();
        $destName = $stem.'.'.$ext;
        $destPath = self::writableBasePath().DIRECTORY_SEPARATOR.$destName;
        if (! @copy($pick['src'], $destPath)) {
            return null;
        }

        return $destName;
    }

    /**
     * @deprecated Las fotos viven en {@see basePath()} o montajes; no duplicar en public/storage salvo legacy.
     */
    public static function importAndStorePublic(string $numerodocumento, ?int $inroclienteid, int $clienteUifId): ?string
    {
        $src = self::findFirstMatchingPath($numerodocumento, $inroclienteid);
        if ($src === null || ! is_file($src)) {
            return null;
        }

        return basename($src);
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
        $newPath = self::writableBasePath().DIRECTORY_SEPARATOR.$newName;
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
