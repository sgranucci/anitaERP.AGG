<?php

declare(strict_types=1);

namespace App\Support\Configuracion\PadronIibb;

use RuntimeException;
use ZipArchive;

/**
 * Deja el archivo del padrón listo para leer: si viene comprimido lo extrae y
 * devuelve el CSV/TXT de adentro.
 *
 * Lo extraído va a un directorio temporal propio: el ZIP puede estar en una
 * carpeta donde el proceso que importa no tiene permiso de escritura. Quien
 * llama debe borrar el temporal con {@see limpiarTemporal()} al terminar.
 */
final class PadronIibbArchivoSupport
{
    private const SUBDIRECTORIO_TEMPORAL = 'temp/padrones/zip';

    /**
     * @param  list<string>  $extensiones  extensiones aceptadas dentro del ZIP
     */
    public static function resolver(string $entrada, array $extensiones = ['csv', 'txt']): string
    {
        if (! is_file($entrada)) {
            throw new RuntimeException("No existe el archivo: {$entrada}");
        }
        if (! is_readable($entrada)) {
            throw new RuntimeException("No se puede leer el archivo: {$entrada}");
        }

        if (strtolower(pathinfo($entrada, PATHINFO_EXTENSION)) !== 'zip') {
            return $entrada;
        }

        return self::extraerDelZip($entrada, $extensiones);
    }

    /**
     * Borra el archivo si quedó dentro del directorio temporal de extracción.
     */
    public static function limpiarTemporal(string $archivo): void
    {
        $real = realpath($archivo);
        if ($real === false) {
            return;
        }

        foreach (self::basesTemporales() as $base) {
            $baseReal = realpath($base);
            if ($baseReal === false) {
                continue;
            }

            if (str_starts_with($real, rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                @unlink($real);
                @rmdir(dirname($real));

                return;
            }
        }
    }

    /**
     * storage primero (queda dentro del proyecto); /tmp como alternativa cuando
     * quien corre la importación no puede escribir en storage (consola manual).
     *
     * @return list<string>
     */
    private static function basesTemporales(): array
    {
        return [
            storage_path('app/' . self::SUBDIRECTORIO_TEMPORAL),
            sys_get_temp_dir() . '/anitaerp-padrones',
        ];
    }

    private static function crearDirectorioTemporal(): string
    {
        $intentos = [];

        foreach (self::basesTemporales() as $base) {
            $destino = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . uniqid('padron_', true);
            $intentos[] = $destino;

            if (@mkdir($destino, 0775, true) || is_dir($destino)) {
                return $destino;
            }
        }

        throw new RuntimeException(
            'No se pudo crear un directorio temporal para descomprimir el padrón. Probé: '
            . implode(', ', $intentos)
        );
    }

    /**
     * @param  list<string>  $extensiones
     */
    private static function extraerDelZip(string $entrada, array $extensiones): string
    {
        $destino = self::crearDirectorioTemporal();

        $zip = new ZipArchive;
        if ($zip->open($entrada) !== true) {
            throw new RuntimeException("No se pudo abrir el ZIP: {$entrada}");
        }

        $candidatos = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = basename((string) ($zip->statIndex($i)['name'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            if (in_array(strtolower(pathinfo($nombre, PATHINFO_EXTENSION)), $extensiones, true)) {
                $candidatos[] = $nombre;
            }
        }

        if ($candidatos === []) {
            $zip->close();
            throw new RuntimeException(
                'El ZIP no contiene ningún archivo ' . strtoupper(implode('/', $extensiones)) . ' del padrón.'
            );
        }

        sort($candidatos);
        $zip->extractTo($destino);
        $zip->close();

        $archivo = $destino . DIRECTORY_SEPARATOR . $candidatos[0];
        if (! is_file($archivo)) {
            throw new RuntimeException("No se pudo extraer {$candidatos[0]} del ZIP.");
        }

        return $archivo;
    }
}
