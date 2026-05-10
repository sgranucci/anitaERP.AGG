<?php

namespace App\Services\Uif;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

/**
 * Fotos de premio guardadas por tesorería en {@see ClienteUifFotoDocumento::basePath()}
 * con nombre {@code pago_}{@code inropremioid}.{@code ext}.
 */
class ClientePremioUifFotoTesoreria
{
    public static function findSourcePath(int $inropremioid, ?string $hintExtension = null): ?string
    {
        if ($inropremioid <= 0) {
            return null;
        }

        $dir = ClienteUifFotoDocumento::basePath();
        if ($dir === '' || ! is_dir($dir)) {
            return null;
        }

        $stem = 'pago_'.$inropremioid;

        foreach (glob($dir.DIRECTORY_SEPARATOR.$stem.'.*') ?: [] as $path) {
            if (! is_file($path)) {
                continue;
            }
            $e = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($e, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                return $path;
            }
        }

        if ($hintExtension !== null && $hintExtension !== '') {
            $ext = ltrim(strtolower($hintExtension), '.');
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $candidate = $dir.DIRECTORY_SEPARATOR.$stem.'.'.$ext;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Copia desde el escáner de tesorería a {@code storage/app/public/imagenes/fotos_uif},
     * mismo criterio que {@see \App\Models\Uif\Cliente_Premio_Uif::setFoto}.
     *
     * @param  int  $inropremioid  ID del premio en Anita (nombre del archivo en /scan).
     * @return string|null basename guardado en disco público, o null si no hay origen o falla el procesado
     */
    public static function importToPublicStorage(int $inropremioid, ?string $hintExtension = null): ?string
    {
        $src = self::findSourcePath($inropremioid, $hintExtension);
        if ($src === null || ! is_readable($src)) {
            return null;
        }

        try {
            $imageName = Str::random(20).'.jpg';
            $imagen = Image::make($src)->encode('jpg', 75);
            $imagen->resize(300, 300, function ($constraint) {
                $constraint->upsize();
            });
            Storage::disk('public')->put('imagenes/fotos_uif/'.$imageName, $imagen->stream());

            return $imageName;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
