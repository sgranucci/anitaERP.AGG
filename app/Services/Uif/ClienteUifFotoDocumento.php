<?php

namespace App\Services\Uif;

use App\Models\Uif\Cliente_Archivo_Uif;
use App\Repositories\Uif\AnitaUifArchivosSync;
use App\Support\Uif\ClienteUifArchivoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class ClienteUifFotoDocumento
{
    public const LEGACY_PUBLIC_SUBDIR = 'imagenes/fotos_documentos_uif';

    /** Extensiones admitidas en el campo DNI (imagen o PDF, como en Anita dni_uif). */
    public const EXTENSIONES_PERMITIDAS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

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
     * Directorio de grabación: {@see anitaDniMount()} si admite escritura;
     * si no, la carpeta de fotos del origen (fotos_clientes / _KSA / _RSA).
     */
    public static function writableBasePath(): string
    {
        $canonical = self::writableDniCanonicalPath();
        if ($canonical !== null) {
            return $canonical;
        }

        return self::ensurePrimaryWritePathReady();
    }

    /** Raíz plana de DNI si existe y www-data puede escribir. */
    public static function writableDniCanonicalPath(): ?string
    {
        $mount = self::anitaDniMount();
        if ($mount === '' || ! is_dir($mount)) {
            return null;
        }

        return self::directoryAcceptsUploads($mount) ? $mount : null;
    }

    /**
     * Rutas de lectura de foto DNI. No incluye fotos_clientes* (retratos / pago_* de tesorería).
     *
     * @return array<int, string>
     */
    public static function storagePathsForRead(): array
    {
        $paths = array_filter([
            self::anitaDniMount(),
            self::fallbackPath(),
        ]);

        return array_values(array_unique($paths));
    }

    /**
     * Carpetas de retrato/pago de tesorería. No son DNI.
     *
     * @return array<int, string>
     */
    public static function directoriosFotoTesoreria(?array $override = null): array
    {
        if ($override !== null) {
            return array_values(array_filter($override));
        }
        try {
            return ClienteUifArchivoStorage::dirsFotosPremioTodos();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function esRutaFotoTesoreria(string $path, ?array $prizeDirs = null): bool
    {
        $real = realpath($path) ?: $path;
        $real = rtrim(str_replace(['\\'], ['/'], $real), '/');
        foreach (self::directoriosFotoTesoreria($prizeDirs) as $dir) {
            $realDir = realpath($dir) ?: $dir;
            $realDir = rtrim(str_replace(['\\'], ['/'], (string) $realDir), '/');
            if ($realDir !== '' && ($real === $realDir || str_starts_with($real, $realDir.'/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Raíz donde Anita guarda las imágenes de DNI (no los demás adjuntos UIF).
     */
    public static function anitaDniMount(): string
    {
        $cfg = config('uif.anita_uif_archivos', []);

        return rtrim((string) ($cfg['dni_mount'] ?? ''), DIRECTORY_SEPARATOR);
    }

    /**
     * Subcarpetas de dni_mount (Rebisco/Kandiko guardan el PDF dos niveles más abajo).
     *
     * @return array<int, string>
     */
    public static function dniMountExtraRelDirs(): array
    {
        $defaults = ['Kandiko/rebisco', 'Kandiko/DNI', 'DNI VIEJOS'];
        try {
            $cfg = config('uif.anita_uif_archivos.dni_extra_dirs', $defaults);
        } catch (\Throwable $e) {
            return $defaults;
        }

        if (! is_array($cfg) || $cfg === []) {
            return $defaults;
        }

        $out = [];
        foreach ($cfg as $rel) {
            $rel = trim(str_replace(['\\', '..'], ['/', ''], (string) $rel), '/');
            if ($rel !== '') {
                $out[] = $rel;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : $defaults;
    }

    /**
     * Busca {stem}.* en la raíz de dni_mount y en subcarpetas conocidas (Kandiko/rebisco, etc.).
     */
    public static function findInDniMountTree(string $dniMount, string $stem): ?string
    {
        $dniMount = rtrim($dniMount, DIRECTORY_SEPARATOR);
        $stem = self::sanitizeNumeroDocumento($stem);
        if ($dniMount === '' || $stem === '' || ! is_dir($dniMount)) {
            return null;
        }

        $dirs = [$dniMount];
        foreach (self::dniMountExtraRelDirs() as $rel) {
            $dirs[] = $dniMount.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
        }

        foreach (array_unique($dirs) as $dir) {
            $found = self::findFirstFileByStemGlob($dir, [$stem]);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Busca un basename exacto en la raíz de dni_mount y en subcarpetas conocidas.
     */
    public static function findBasenameInDniMountTree(string $dniMount, string $basename): ?string
    {
        $dniMount = rtrim($dniMount, DIRECTORY_SEPARATOR);
        $basename = basename($basename);
        if ($dniMount === '' || $basename === '' || ! is_dir($dniMount)) {
            return null;
        }

        $dirs = [$dniMount];
        foreach (self::dniMountExtraRelDirs() as $rel) {
            $dirs[] = $dniMount.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
        }

        foreach (array_unique($dirs) as $dir) {
            $candidate = $dir.DIRECTORY_SEPARATOR.$basename;
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Copia un DNI hallado en subcarpeta (o fotos_*) a dni_mount/{documento}.{ext}.
     * No pisa un archivo que ya esté en la raíz (mismo DNI, otro scan).
     */
    public static function promoverADniMountCanonico(string $srcPath, string $numerodocumento, ?string $dniMount = null): ?string
    {
        $dniMount = rtrim((string) ($dniMount ?? self::anitaDniMount()), DIRECTORY_SEPARATOR);
        $stem = self::sanitizeNumeroDocumento($numerodocumento);
        if ($dniMount === '' || $stem === '' || ! is_file($srcPath) || ! is_readable($srcPath)) {
            return null;
        }
        if (self::esRutaFotoTesoreria($srcPath)) {
            return null;
        }
        $ext = strtolower((string) pathinfo($srcPath, PATHINFO_EXTENSION));
        if (! in_array($ext, self::EXTENSIONES_PERMITIDAS, true)) {
            return null;
        }

        $dest = $dniMount.DIRECTORY_SEPARATOR.$stem.'.'.$ext;
        $srcReal = realpath($srcPath) ?: $srcPath;
        if (is_file($dest)) {
            $destReal = realpath($dest) ?: $dest;

            return $destReal;
        }
        if ($srcReal === $dest) {
            return $srcReal;
        }
        if (! is_dir($dniMount) || ! is_writable($dniMount)) {
            return null;
        }
        if (! @copy($srcPath, $dest)) {
            return null;
        }
        @chmod($dest, 0664);

        return $dest;
    }

    /**
     * Copia a la raíz los PDF/imagen nombrados {DNI}.* que están en subcarpetas conocidas.
     *
     * @return array{copiados:int, ya_estaban:int, omitidos:int}
     */
    public static function promoverNumeradosDesdeExtraDirs(?string $dniMount = null): array
    {
        $dniMount = rtrim((string) ($dniMount ?? self::anitaDniMount()), DIRECTORY_SEPARATOR);
        $stats = ['copiados' => 0, 'ya_estaban' => 0, 'omitidos' => 0];
        if ($dniMount === '' || ! is_dir($dniMount)) {
            return $stats;
        }

        foreach (self::dniMountExtraRelDirs() as $rel) {
            $dir = $dniMount.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (! is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir, SCANDIR_SORT_NONE) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                if (! preg_match('/^(\d+)\.(pdf|jpe?g|png|gif|webp)$/i', $entry, $m)) {
                    $stats['omitidos']++;
                    continue;
                }
                $src = $dir.DIRECTORY_SEPARATOR.$entry;
                if (! is_file($src)) {
                    continue;
                }
                $dest = $dniMount.DIRECTORY_SEPARATOR.$entry;
                if (is_file($dest)) {
                    $stats['ya_estaban']++;
                    continue;
                }
                if (self::promoverADniMountCanonico($src, $m[1], $dniMount) !== null && is_file($dest)) {
                    $stats['copiados']++;
                }
            }
        }

        return $stats;
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
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        if (! in_array($ext, self::EXTENSIONES_PERMITIDAS, true)) {
            $mime = strtolower((string) $file->getMimeType());
            $ext = str_contains($mime, 'pdf') ? 'pdf' : 'jpg';
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

        $primary = self::writableBasePath();
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
            $nested = self::findBasenameInDniMountTree($dniMount, $base);
            if ($nested !== null) {
                return $nested;
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
            $nested = self::findInDniMountTree($dniMount, $stem);
            if ($nested !== null) {
                return $nested;
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
     * DDJJ, NOSIS, PEP e informes no son foto de DNI aunque el número figure en el nombre.
     */
    public static function esNombreAdjuntoNoDni(string $basename): bool
    {
        $bn = strtolower(basename($basename));
        if ($bn === '') {
            return false;
        }

        foreach (['ddjj', 'declaracion', 'declaraci', 'nosis', 'djpep', 'informepep', 'informe_pep', 'informe-pep'] as $token) {
            if (str_contains($bn, $token)) {
                return true;
            }
        }

        return (bool) preg_match('/(^|[-_ ])(cp|li|lt)[-_]/i', $bn);
    }

    /**
     * Puntaje para elegir foto DNI. null = no usar (declaración / informe).
     * Solo valores > 0 son candidatos (número de documento o pistas dni/frente/…).
     */
    public static function puntajeCandidatoFotoDni(string $basename, string $numerodocumento): ?int
    {
        if (self::esNombreAdjuntoNoDni($basename)) {
            return null;
        }
        $bn = strtolower(basename($basename));
        $stem = strtolower(self::sanitizeNumeroDocumento($numerodocumento));
        $score = 0;
        if ($stem !== '' && str_contains($bn, $stem)) {
            $score += 100;
        }
        foreach (['dni', 'documento', 'frente', 'verso'] as $hint) {
            if (str_contains($bn, $hint)) {
                $score += 15;
            }
        }
        foreach (['foto', 'scan', 'img'] as $hint) {
            if (str_contains($bn, $hint)) {
                $score += 5;
            }
        }

        return $score;
    }

    /**
     * True si $absolutePath es la misma bytes que un adjunto DDJJ/NOSIS del cliente.
     */
    public static function esCopiaDeAdjuntoNoDni(int $clienteUifId, string $absolutePath): bool
    {
        if ($clienteUifId <= 0 || $absolutePath === '' || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return false;
        }
        $fotoSize = filesize($absolutePath);
        if ($fotoSize === false || $fotoSize <= 0) {
            return false;
        }
        $fotoHash = md5_file($absolutePath);
        if ($fotoHash === false) {
            return false;
        }

        $rows = Cliente_Archivo_Uif::query()
            ->where('cliente_uif_id', $clienteUifId)
            ->orderBy('id')
            ->get(['nombrearchivo']);

        foreach ($rows as $row) {
            $n = (string) $row->nombrearchivo;
            if ($n === '' || ! self::esNombreAdjuntoNoDni($n)) {
                continue;
            }
            $src = ClienteUifArchivoStorage::absoluteClienteAdjunto($clienteUifId, $n);
            if ($src === null || ! is_file($src) || ! is_readable($src)) {
                continue;
            }
            if (filesize($src) !== $fotoSize) {
                continue;
            }
            if (md5_file($src) === $fotoHash) {
                return true;
            }
        }

        return false;
    }

    /**
     * True si $absolutePath es la misma bytes que el retrato {dni}.* de tesorería.
     */
    public static function esCopiaDeFotoTesoreria(string $absolutePath, string $numerodocumento, ?array $prizeDirs = null): bool
    {
        if ($absolutePath === '' || ! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return false;
        }
        if (self::esRutaFotoTesoreria($absolutePath, $prizeDirs)) {
            return false;
        }
        $stem = self::sanitizeNumeroDocumento($numerodocumento);
        if ($stem === '') {
            return false;
        }
        $fotoSize = filesize($absolutePath);
        $fotoHash = md5_file($absolutePath);
        if ($fotoSize === false || $fotoSize <= 0 || $fotoHash === false) {
            return false;
        }
        foreach (self::directoriosFotoTesoreria($prizeDirs) as $dir) {
            if ($dir === '' || ! is_dir($dir)) {
                continue;
            }
            foreach (glob($dir.DIRECTORY_SEPARATOR.$stem.'.*') ?: [] as $src) {
                if (! is_file($src) || ! is_readable($src)) {
                    continue;
                }
                if (filesize($src) === $fotoSize && md5_file($src) === $fotoHash) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Si la foto canónica es DDJJ/NOSIS o un retrato de tesorería copiado a dni_uif, la borra.
     */
    public static function descartarCopiaCanonicoSiEsAdjuntoNoDni(int $clienteUifId, string $absolutePath): bool
    {
        return self::descartarCopiaCanonicoSiNoEsDni($clienteUifId, $absolutePath, '');
    }

    public static function descartarCopiaCanonicoSiNoEsDni(int $clienteUifId, string $absolutePath, string $numerodocumento): bool
    {
        $esAdjunto = $clienteUifId > 0 && self::esCopiaDeAdjuntoNoDni($clienteUifId, $absolutePath);
        $esTesoreria = $numerodocumento !== '' && self::esCopiaDeFotoTesoreria($absolutePath, $numerodocumento);
        if (! $esAdjunto && ! $esTesoreria) {
            return false;
        }
        $mount = self::anitaDniMount();
        if ($mount === '') {
            return false;
        }
        $realFile = realpath($absolutePath);
        $realMount = realpath($mount);
        if ($realFile === false || $realMount === false) {
            return false;
        }
        if (! str_starts_with($realFile, $realMount.DIRECTORY_SEPARATOR)) {
            return false;
        }
        if (dirname($realFile) !== $realMount) {
            return false;
        }

        return @unlink($realFile);
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
                if (! in_array($e, self::EXTENSIONES_PERMITIDAS, true)) {
                    continue;
                }
                $score = self::puntajeCandidatoFotoDni(basename($path), $numerodocumento);
                if ($score === null || $score <= 0) {
                    continue;
                }
                $ranked[] = ['path' => $path, 'score' => $score, 'bn' => strtolower(basename($path))];
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
     * No copia declaraciones juradas, NOSIS ni informes PEP.
     */
    public static function copyFirstClienteAdjuntoImageToFotodocumento(int $clienteUifId, string $numerodocumento): ?string
    {
        $stem = self::sanitizeNumeroDocumento($numerodocumento);
        if ($stem === '') {
            return null;
        }
        $rows = Cliente_Archivo_Uif::query()
            ->where('cliente_uif_id', $clienteUifId)
            ->orderBy('id')
            ->get(['nombrearchivo']);

        $ranked = [];
        foreach ($rows as $row) {
            $n = (string) $row->nombrearchivo;
            if ($n === '' || ! preg_match('/\.(jpe?g|png|gif|webp|pdf)$/i', $n)) {
                continue;
            }
            $score = self::puntajeCandidatoFotoDni($n, $numerodocumento);
            if ($score === null || $score <= 0) {
                continue;
            }
            $src = ClienteUifArchivoStorage::absoluteClienteAdjunto($clienteUifId, $n);
            if ($src === null || ! is_file($src)) {
                continue;
            }
            $ranked[] = ['nombre' => $n, 'src' => $src, 'score' => $score, 'bn' => strtolower(basename($n))];
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
        $ext = strtolower((string) pathinfo($pick['src'], PATHINFO_EXTENSION));
        if (! in_array($ext, self::EXTENSIONES_PERMITIDAS, true)) {
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

    /** @see ClienteUifArchivoStorage::versionCache() */
    public static function versionCache(?string $absolutePath): string
    {
        return ClienteUifArchivoStorage::versionCache($absolutePath);
    }

    /**
     * @return array{v: string}
     */
    public static function queryVersion(?string $absolutePath): array
    {
        return ClienteUifArchivoStorage::queryVersion($absolutePath);
    }

    /** @see ClienteUifArchivoStorage::aplicarAntiCacheNavegador() */
    public static function aplicarAntiCacheNavegador(Response $response): Response
    {
        return ClienteUifArchivoStorage::aplicarAntiCacheNavegador($response);
    }
}
