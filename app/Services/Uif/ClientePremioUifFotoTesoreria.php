<?php

namespace App\Services\Uif;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Fotos de pago guardadas por tesorería en {@see ClienteUifFotoDocumento::basePath()}
 * típicamente con nombre {@code pago_}{@code inropremioid}.{@code ext}.
 */
class ClientePremioUifFotoTesoreria
{
    private static function acceptedImageExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff'];
    }

    /**
     * @param  ?string  $hintExtension  extensión informada por Anita (cextfoto), sin punto
     */
    public static function findSourcePath(int $inropremioid, ?string $hintExtension = null): ?string
    {
        if ($inropremioid <= 0) {
            return null;
        }

        $dir = ClienteUifFotoDocumento::basePath();
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

        foreach (@scandir($dir, SCANDIR_SORT_NONE) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.$ds.$entry;
            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }
            $baseFile = pathinfo($entry, PATHINFO_FILENAME);
            if (strcasecmp((string) $baseFile, $stem) !== 0) {
                continue;
            }
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
                    $candidateCi = self::findCaseInsensitiveStemExt($dir, $stem, $tryExt);
                    if ($candidateCi !== null) {
                        return $candidateCi;
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
     * Copia desde tesorería a {@code storage/app/public/imagenes/fotos_uif}.
     * Si el procesamiento a JPG tiene éxito, el archivo destino será {@code pago_{inropremioid}.jpg}.
     * Si falla Intervention, copia binaria conservando la extensión del origen.
     *
     * @return string|null nombre de archivo dentro de imagenes/fotos_uif/ (basename), o null
     */
    public static function importToPublicStorage(int $inropremioid, ?string $hintExtension = null): ?string
    {
        $src = self::findSourcePath($inropremioid, $hintExtension);
        if ($src === null || ! is_readable($src)) {
            return null;
        }

        $relPrefix = 'imagenes/fotos_uif';
        $stem = 'pago_'.$inropremioid;

        try {
            $image = Image::decodePath($src)
                ->resizeDown(300, 300);
            $destName = $stem.'.jpg';
            Storage::disk('public')->put(
                $relPrefix.'/'.$destName,
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
                Storage::disk('public')->put($relPrefix.'/'.$destName, $raw);

                return $destName;
            } catch (\Throwable $e2) {
                return null;
            }
        }
    }

    /** Elimina un archivo previo del disco público si era distinto al nuevo basename. */
    public static function deletePublicFotoIfUnused(?string $basename): void
    {
        if ($basename === null || $basename === '' || strpos($basename, '/') !== false || strpos($basename, '\\') !== false) {
            return;
        }
        Storage::disk('public')->delete('imagenes/fotos_uif/'.$basename);
    }
}
