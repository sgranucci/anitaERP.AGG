<?php

namespace App\Support\Archivo;

use Illuminate\Http\UploadedFile;

/**
 * Normaliza texto/archivos CSV a UTF-8.
 *
 * Excel / Anita suelen exportar ISO-8859-1 o Windows-1252; sin convertir,
 * json_encode del preview falla con "Malformed UTF-8 characters…".
 */
final class TextoUtf8Support
{
    /** @var list<string> */
    private const EXTENSIONES_TEXTO = ['csv', 'txt'];

    public static function quitarBom(string $contenido): string
    {
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            return substr($contenido, 3);
        }

        return $contenido;
    }

    public static function normalizar(string $contenido): string
    {
        $contenido = self::quitarBom($contenido);
        if ($contenido === '' || mb_check_encoding($contenido, 'UTF-8')) {
            return $contenido;
        }

        foreach (['Windows-1252', 'ISO-8859-1'] as $desde) {
            $utf8 = @mb_convert_encoding($contenido, 'UTF-8', $desde);
            if (is_string($utf8) && $utf8 !== '' && mb_check_encoding($utf8, 'UTF-8')) {
                return $utf8;
            }
        }

        $fallback = @iconv('UTF-8', 'UTF-8//IGNORE', $contenido);

        return is_string($fallback) ? $fallback : $contenido;
    }

    /**
     * Si el upload es CSV/TXT y no está en UTF-8, reescribe el temporal en UTF-8
     * para que PhpSpreadsheet / Maatwebsite lo lean bien.
     */
    public static function asegurarCsvUtf8(UploadedFile $archivo): UploadedFile
    {
        if (! self::pareceArchivoTexto($archivo)) {
            return $archivo;
        }

        $path = $archivo->getRealPath() ?: $archivo->path();
        if ($path === false || $path === '' || ! is_file($path)) {
            return $archivo;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return $archivo;
        }

        $utf8 = self::normalizar($raw);
        if ($utf8 !== $raw) {
            file_put_contents($path, $utf8);
        }

        return $archivo;
    }

    private static function pareceArchivoTexto(UploadedFile $archivo): bool
    {
        $ext = strtolower($archivo->getClientOriginalExtension() ?: '');
        if (in_array($ext, self::EXTENSIONES_TEXTO, true)) {
            return true;
        }

        $mime = strtolower((string) $archivo->getMimeType());

        return str_contains($mime, 'csv')
            || str_contains($mime, 'text/plain')
            || str_contains($mime, 'text/csv');
    }
}
