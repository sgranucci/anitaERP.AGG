<?php

namespace App\Support\Configuracion;

use Illuminate\Support\Facades\File;

/**
 * Lectura segura de storage/logs para el panel de auditoría.
 */
class ArchivoLogSupport
{
    public static function directorio(): string
    {
        return storage_path('logs');
    }

    /**
     * @return list<array{nombre:string,path:string,bytes:int,humano:string,mtime:string,mtime_ts:int}>
     */
    public static function listar(): array
    {
        $dir = self::directorio();
        if (! is_dir($dir)) {
            return [];
        }

        $out = [];
        foreach (File::files($dir) as $file) {
            $nombre = $file->getFilename();
            if (! preg_match('/^[a-zA-Z0-9._\-]+\.log(\.\d+)?$/', $nombre)
                && ! preg_match('/^[a-zA-Z0-9._\-]+\.log$/', $nombre)) {
                // Permitir laravel-YYYY-MM-DD.log y *.log
                if (! str_ends_with(strtolower($nombre), '.log')) {
                    continue;
                }
            }
            $bytes = (int) $file->getSize();
            $mtime = (int) $file->getMTime();
            $out[] = [
                'nombre' => $nombre,
                'path' => $file->getPathname(),
                'bytes' => $bytes,
                'humano' => BitacoraAccesoDiscoSupport::bytesHumanos($bytes),
                'mtime' => date('d/m/Y H:i', $mtime),
                'mtime_ts' => $mtime,
            ];
        }

        usort($out, static fn ($a, $b) => $b['mtime_ts'] <=> $a['mtime_ts']);

        return $out;
    }

    /** @return array{total_bytes:int,total_humano:string,cantidad:int,archivos:list<array<string,mixed>>} */
    public static function resumenDisco(): array
    {
        $archivos = self::listar();
        $total = 0;
        foreach ($archivos as $a) {
            $total += $a['bytes'];
        }

        return [
            'total_bytes' => $total,
            'total_humano' => BitacoraAccesoDiscoSupport::bytesHumanos($total),
            'cantidad' => count($archivos),
            'archivos' => $archivos,
            'canal_default' => (string) config('logging.default'),
            'canal_stack' => config('logging.channels.stack.channels'),
        ];
    }

    /**
     * @return array{ok:bool,error:?string,nombre:?string,bytes:int,humano:string,lineas:list<string>,total_lineas_leidas:int}
     */
    public static function leerCola(string $nombreArchivo, int $lineas = 200): array
    {
        $nombreArchivo = basename($nombreArchivo);
        $path = self::directorio().DIRECTORY_SEPARATOR.$nombreArchivo;

        if ($nombreArchivo === '' || ! is_file($path) || ! str_ends_with(strtolower($nombreArchivo), '.log')) {
            return [
                'ok' => false,
                'error' => 'Archivo de log no válido o inexistente.',
                'nombre' => null,
                'bytes' => 0,
                'humano' => '0 B',
                'lineas' => [],
                'total_lineas_leidas' => 0,
            ];
        }

        $real = realpath($path);
        $dirReal = realpath(self::directorio());
        if ($real === false || $dirReal === false || ! str_starts_with($real, $dirReal)) {
            return [
                'ok' => false,
                'error' => 'Ruta de log fuera del directorio permitido.',
                'nombre' => null,
                'bytes' => 0,
                'humano' => '0 B',
                'lineas' => [],
                'total_lineas_leidas' => 0,
            ];
        }

        $lineas = max(50, min(2000, $lineas));
        $contenido = self::tailArchivo($real, $lineas);
        $bytes = (int) filesize($real);

        return [
            'ok' => true,
            'error' => null,
            'nombre' => $nombreArchivo,
            'bytes' => $bytes,
            'humano' => BitacoraAccesoDiscoSupport::bytesHumanos($bytes),
            'lineas' => $contenido,
            'total_lineas_leidas' => count($contenido),
        ];
    }

    /**
     * Lee la cola del archivo por chunks (no carácter a carácter).
     *
     * @return list<string>
     */
    private static function tailArchivo(string $path, int $lineas): array
    {
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            return [];
        }

        $stat = fstat($fp);
        $size = (int) ($stat['size'] ?? 0);
        if ($size === 0) {
            fclose($fp);

            return [];
        }

        $chunkSize = 8192;
        $buffer = '';
        $pos = $size;
        $nuevasLineas = 0;

        while ($pos > 0 && $nuevasLineas <= $lineas) {
            $leer = min($chunkSize, $pos);
            $pos -= $leer;
            if (fseek($fp, $pos) !== 0) {
                break;
            }
            $chunk = fread($fp, $leer);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buffer = $chunk.$buffer;
            $nuevasLineas = substr_count($buffer, "\n");
        }
        fclose($fp);

        $parts = preg_split("/\r\n|\n|\r/", rtrim($buffer, "\r\n"));
        if ($parts === false) {
            return [];
        }
        $parts = array_values(array_filter($parts, static fn ($l) => $l !== ''));

        return array_slice($parts, -$lineas);
    }
}
