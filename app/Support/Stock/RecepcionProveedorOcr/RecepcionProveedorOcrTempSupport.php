<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

use Illuminate\Support\Facades\File;

/**
 * Directorio temporal escribible para archivos intermedios del OCR.
 */
final class RecepcionProveedorOcrTempSupport
{
    public static function directorio(): string
    {
        $candidatos = [
            (string) config('recepcion_proveedor.ocr.tmp_dir', ''),
            storage_path('app/tmp/ocr'),
            sys_get_temp_dir().'/anitaerp-ocr',
        ];

        foreach ($candidatos as $dir) {
            if ($dir === '') {
                continue;
            }
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }

        return sys_get_temp_dir();
    }

    public static function archivo(string $prefijo, string $extension = 'tmp'): string
    {
        return self::base($prefijo).($extension !== '' ? '.'.$extension : '');
    }

    public static function base(string $prefijo): string
    {
        $dir = self::directorio();
        File::ensureDirectoryExists($dir);

        return $dir.'/'.uniqid($prefijo, true);
    }
}
