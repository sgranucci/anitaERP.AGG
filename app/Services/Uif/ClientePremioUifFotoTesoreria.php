<?php

namespace App\Services\Uif;

use App\Support\Uif\ClienteUifArchivoStorage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Fotos de pago en file server (/scan/tesoreria/fotos_clientes), patrón pago_{inropremioid}.*
 * Sync: solo resuelve basename; no copia a /var del ERP.
 */
class ClientePremioUifFotoTesoreria
{
    /** @var array<int, string>|null inropremioid => path absoluto */
    private static ?array $indicePagoPorPremio = null;

    private static function acceptedImageExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'];
    }

    /**
     * Escanea una vez dirFotosPremio (pago_*) para sync bulk.
     *
     * @return array{fotos_pago:int}
     */
    public static function warmIndicePago(): array
    {
        self::$indicePagoPorPremio = [];
        $dir = ClienteUifArchivoStorage::dirFotosPremio();
        if ($dir === '' || ! is_dir($dir) || ! is_readable($dir)) {
            return ['fotos_pago' => 0];
        }
        $ds = DIRECTORY_SEPARATOR;
        foreach (scandir($dir, SCANDIR_SORT_NONE) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (! preg_match('/^pago_(\d+)\./i', $entry, $m)) {
                continue;
            }
            $path = $dir.$ds.$entry;
            if (! self::isAcceptedImagePath($path)) {
                continue;
            }
            $pid = (int) $m[1];
            // Preferir la primera encontrada; si hay varias ext, conservar la ya indexada
            if (! isset(self::$indicePagoPorPremio[$pid])) {
                self::$indicePagoPorPremio[$pid] = $path;
            }
        }

        return ['fotos_pago' => count(self::$indicePagoPorPremio)];
    }

    public static function clearIndicePago(): void
    {
        self::$indicePagoPorPremio = null;
    }

    /**
     * @param  ?string  $hintExtension  extensión informada por Anita (cextfoto), sin punto
     */
    public static function findSourcePath(int $inropremioid, ?string $hintExtension = null): ?string
    {
        if ($inropremioid <= 0) {
            return null;
        }

        if (self::$indicePagoPorPremio !== null) {
            return self::$indicePagoPorPremio[$inropremioid] ?? null;
        }

        $dir = ClienteUifArchivoStorage::dirFotosPremio();
        if ($dir === '' || ! is_dir($dir) || ! is_readable($dir)) {
            return null;
        }

        $stem = 'pago_'.$inropremioid;
        $ds = DIRECTORY_SEPARATOR;

        foreach (glob($dir.$ds.$stem.'.*') ?: [] as $path) {
            if (self::isAcceptedImagePath($path)) {
                return $path;
            }
        }

        if ($hintExtension !== null && $hintExtension !== '') {
            $ext = ltrim(strtolower($hintExtension), '.');
            if (in_array($ext, self::acceptedImageExtensions(), true)) {
                foreach (self::acceptedImageExtensions() as $tryExt) {
                    $candidate = $dir.$ds.$stem.'.'.$tryExt;
                    if (is_file($candidate) && is_readable($candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private static function findCaseInsensitiveStemExt(string $dir, string $stem, string $extLower): ?string
    {
        foreach (@scandir($dir, SCANDIR_SORT_NONE) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            if (! is_file($path)) {
                continue;
            }
            if (strcasecmp(pathinfo($entry, PATHINFO_FILENAME), $stem) !== 0) {
                continue;
            }
            if (strcasecmp((string) pathinfo($entry, PATHINFO_EXTENSION), $extLower) === 0) {
                return $path;
            }
        }

        return null;
    }

    private static function isAcceptedImagePath(string $path): bool
    {
        if (! is_file($path) || ! is_readable($path)) {
            return false;
        }
        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, self::acceptedImageExtensions(), true);
    }

    /**
     * Resuelve foto en /scan (o legacy local). No copia a disco del ERP salvo
     * {@see config('uif.SYNC_COPIAR_ARCHIVOS')}.
     *
     * @return string|null basename a guardar en cliente_premio_uif.foto
     */
    public static function importToPublicStorage(int $inropremioid, ?string $hintExtension = null): ?string
    {
        $src = self::findSourcePath($inropremioid, $hintExtension);
        if ($src === null || ! is_readable($src)) {
            return null;
        }

        if (! ClienteUifArchivoStorage::syncDebeCopiar()) {
            return basename($src);
        }

        $relPrefix = 'imagenes/fotos_uif';
        $stem = 'pago_'.$inropremioid;
        $destDir = storage_path('app/public/'.$relPrefix);
        if (! is_dir($destDir)) {
            File::makeDirectory($destDir, 0775, true, true);
        }

        try {
            $image = Image::decodePath($src)
                ->resizeDown(300, 300);
            $destName = $stem.'.jpg';
            file_put_contents(
                $destDir.DIRECTORY_SEPARATOR.$destName,
                $image->encodeUsingFileExtension('jpg', quality: 75)
            );

            return $destName;
        } catch (\Throwable $e) {
            try {
                $raw = @file_get_contents($src);
                if ($raw === false || $raw === '') {
                    return null;
                }
                $ext = strtolower((string) pathinfo($src, PATHINFO_EXTENSION));
                if (! in_array($ext, self::acceptedImageExtensions(), true)) {
                    $ext = 'jpg';
                }
                $destName = $stem.'.'.$ext;
                file_put_contents($destDir.DIRECTORY_SEPARATOR.$destName, $raw);

                return $destName;
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }

    /**
     * Elimina foto previa solo si es generada por el ERP (no pago_* compartidos en /scan).
     */
    public static function deletePublicFotoIfUnused(?string $basename): void
    {
        if ($basename === null || $basename === '' || strpos($basename, '/') !== false || strpos($basename, '\\') !== false) {
            return;
        }
        if (ClienteUifArchivoStorage::esFotoPremioCompartidaAnita($basename)) {
            return;
        }
        $path = ClienteUifArchivoStorage::absoluteFotoPremio($basename);
        if ($path !== null && is_file($path) && Str::startsWith($path, storage_path('app/public'))) {
            @unlink($path);
        }
    }
}
