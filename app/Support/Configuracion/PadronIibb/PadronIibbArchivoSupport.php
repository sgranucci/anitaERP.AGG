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
     * @param  list<string>  $extensiones  extensiones aceptadas dentro del ZIP; vacío = cualquiera
     * @param  callable(list<array<string, mixed>>, ZipArchive): array<string, mixed>|null  $selector
     */
    public static function resolver(string $entrada, array $extensiones = ['csv', 'txt'], ?callable $selector = null): string
    {
        if (! is_file($entrada)) {
            throw new RuntimeException("No existe el archivo: {$entrada}");
        }
        if (! is_readable($entrada)) {
            throw new RuntimeException("No se puede leer el archivo: {$entrada}");
        }

        if (! self::pareceZip($entrada)) {
            return $entrada;
        }

        return self::extraerDelZip($entrada, $extensiones, $selector);
    }

    /**
     * ZIP por extensión o por firma (PK), salvo office que también es ZIP (xlsx/docx).
     */
    public static function pareceZip(string $entrada): bool
    {
        $ext = strtolower((string) pathinfo($entrada, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls', 'ods', 'docx', 'doc'], true)) {
            return false;
        }
        if ($ext === 'zip') {
            return true;
        }

        return self::tieneFirmaZip($entrada);
    }

    public static function tieneFirmaZip(string $entrada): bool
    {
        if (! is_file($entrada) || ! is_readable($entrada)) {
            return false;
        }

        $fh = fopen($entrada, 'rb');
        if ($fh === false) {
            return false;
        }

        $magic = fread($fh, 4);
        fclose($fh);

        return $magic === "PK\x03\x04"
            || $magic === "PK\x05\x06"
            || $magic === "PK\x07\x08";
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
                self::eliminarDirectorioTemporal(dirname($real), $baseReal);

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
     * Extrae por índice a un nombre local controlado: no depende de carpetas
     * internas ni de que el CSV se llame de una forma puntual.
     *
     * @param  list<string>  $extensiones
     * @param  callable(list<array<string, mixed>>, ZipArchive): array<string, mixed>|null  $selector
     */
    private static function extraerDelZip(string $entrada, array $extensiones, ?callable $selector = null): string
    {
        $destino = self::crearDirectorioTemporal();

        $zip = new ZipArchive;
        if ($zip->open($entrada) !== true) {
            throw new RuntimeException("No se pudo abrir el ZIP: {$entrada}");
        }

        try {
            $entradas = self::listarEntradasDatos($zip);
            if ($entradas === []) {
                throw new RuntimeException('El ZIP no contiene ningún archivo de datos (solo carpetas o basura).');
            }

            $elegida = $selector !== null
                ? $selector($entradas, $zip)
                : self::elegirPorExtensionYTamanio($entradas, $extensiones);

            if (! is_array($elegida) || ! isset($elegida['indice'])) {
                throw new RuntimeException('No se pudo elegir un archivo de adentro del ZIP.');
            }

            return self::volcarEntrada($zip, $elegida, $destino);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<array{indice: int, nombre: string, interno: string, extension: string, tamanio: int}>
     */
    public static function listarEntradasDatos(ZipArchive $zip): array
    {
        $entradas = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $interno = str_replace('\\', '/', (string) ($stat['name'] ?? ''));
            if ($interno === '' || str_ends_with($interno, '/')) {
                continue;
            }
            if (str_contains($interno, '__MACOSX/') || str_contains($interno, '.DS_Store')) {
                continue;
            }

            $nombre = basename($interno);
            if ($nombre === '' || str_starts_with($nombre, '.')) {
                continue;
            }

            $entradas[] = [
                'indice' => $i,
                'nombre' => $nombre,
                'interno' => $interno,
                'extension' => strtolower((string) pathinfo($nombre, PATHINFO_EXTENSION)),
                'tamanio' => (int) ($stat['size'] ?? 0),
            ];
        }

        return $entradas;
    }

    /**
     * @param  list<array{indice: int, nombre: string, interno: string, extension: string, tamanio: int}>  $entradas
     * @param  list<string>  $extensiones
     * @return array{indice: int, nombre: string, interno: string, extension: string, tamanio: int}
     */
    private static function elegirPorExtensionYTamanio(array $entradas, array $extensiones): array
    {
        $filtradas = $entradas;
        if ($extensiones !== []) {
            $conExt = array_values(array_filter(
                $entradas,
                static fn (array $e): bool => in_array($e['extension'], $extensiones, true)
            ));
            if ($conExt !== []) {
                $filtradas = $conExt;
            }
        }

        if ($filtradas === []) {
            throw new RuntimeException(
                'El ZIP no contiene ningún archivo '
                . strtoupper(implode('/', $extensiones !== [] ? $extensiones : ['de datos']))
                . ' del padrón.'
            );
        }

        usort($filtradas, static fn (array $a, array $b): int => $b['tamanio'] <=> $a['tamanio']);

        return $filtradas[0];
    }

    /**
     * @param  array{indice: int, nombre: string, interno: string, extension: string, tamanio: int}  $entrada
     */
    private static function volcarEntrada(ZipArchive $zip, array $entrada, string $destino): string
    {
        $nombreLocal = self::nombreLocalSeguro((string) $entrada['nombre'], (string) $entrada['extension']);
        $ruta = $destino . DIRECTORY_SEPARATOR . $nombreLocal;

        $in = null;
        if (method_exists($zip, 'getStreamIndex')) {
            $in = $zip->getStreamIndex((int) $entrada['indice']);
        }
        if (! is_resource($in)) {
            $in = $zip->getStream((string) $entrada['interno']);
        }

        if (is_resource($in)) {
            $out = fopen($ruta, 'wb');
            if ($out === false) {
                fclose($in);
                throw new RuntimeException('No se pudo crear el archivo temporal extraído del ZIP.');
            }
            stream_copy_to_stream($in, $out);
            fclose($out);
            fclose($in);
            if (is_file($ruta) && filesize($ruta) > 0) {
                return $ruta;
            }
        }

        $contenido = $zip->getFromIndex((int) $entrada['indice']);
        if ($contenido === false || $contenido === '') {
            throw new RuntimeException(
                'No se pudo leer el archivo de adentro del ZIP'
                . (isset($entrada['nombre']) ? ' (' . $entrada['nombre'] . ')' : '')
                . '. Probé extraerlo por índice, sin usar la carpeta interna.'
            );
        }
        if (file_put_contents($ruta, $contenido) === false) {
            throw new RuntimeException('No se pudo guardar el archivo extraído del ZIP.');
        }

        return $ruta;
    }

    private static function nombreLocalSeguro(string $nombre, string $extension): string
    {
        $base = basename(str_replace('\\', '/', $nombre));
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?? '';
        $base = trim($base, '._');
        if ($base === '') {
            $base = 'padron_extraido' . ($extension !== '' ? '.' . $extension : '.txt');
        }

        return $base;
    }

    private static function eliminarDirectorioTemporal(string $directorio, string $base): void
    {
        $real = realpath($directorio);
        if ($real === false || ! str_starts_with($real, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return;
        }

        $restos = @scandir($real);
        if ($restos === false) {
            return;
        }
        $soloPuntos = array_diff($restos, ['.', '..']) === [];
        if ($soloPuntos) {
            @rmdir($real);
        }
    }
}
