<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use InvalidArgumentException;
use RuntimeException;

/**
 * Resuelve y valida rutas de archivos de padrón IIBB (upload storage o ruta en servidor).
 */
final class PadronIibbArchivoRutaSupport
{
    /** @return list<string> */
    public static function directoriosPermitidos(): array
    {
        $directorios = [realpath(storage_path('app')) ?: storage_path('app')];

        foreach (preg_split('/[,;]+/', (string) config('padrones_iibb.directorios', '')) ?: [] as $directorio) {
            $directorio = trim($directorio);
            if ($directorio !== '') {
                $directorios[] = rtrim($directorio, DIRECTORY_SEPARATOR);
            }
        }

        return array_values(array_unique($directorios));
    }

    /**
     * Valida que $path sea un archivo legible bajo un directorio whitelist.
     *
     * @throws InvalidArgumentException
     */
    public static function validarRutaServidor(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException('La ruta en servidor debe ser absoluta.');
        }

        $real = realpath($path);
        if ($real === false || ! is_file($real) || ! is_readable($real)) {
            // Caso típico: el archivo está en un home de usuario, que no es
            // accesible para el usuario del servidor web ni para el worker.
            throw new InvalidArgumentException(
                "No existe o no se puede leer el archivo: {$path}. "
                . 'Si el archivo existe, copielo a ' . storage_path('app/padrones')
                . ' (los directorios personales no son legibles por el servidor).'
            );
        }

        foreach (self::directoriosPermitidos() as $base) {
            $baseReal = realpath($base) ?: $base;
            if (str_starts_with($real, rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
                || $real === $baseReal) {
                return $real;
            }
        }

        throw new InvalidArgumentException(
            'La ruta no está en un directorio permitido. Permitidos: '
            . implode(', ', self::directoriosPermitidos())
        );
    }

    public static function extensionPermitida(string $path, array $extensiones = ['txt', 'csv', 'zip']): void
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, $extensiones, true)) {
            throw new InvalidArgumentException(
                'Extensión no permitida (.' . $ext . '). Use: ' . implode(', ', $extensiones)
            );
        }
    }

    /**
     * Guarda un upload en storage/app/temp/padrones y devuelve ruta absoluta.
     *
     * @throws RuntimeException
     */
    public static function guardarUpload(\Illuminate\Http\UploadedFile $archivo): string
    {
        $dir = 'temp/padrones';
        if (! \Illuminate\Support\Facades\Storage::exists($dir)) {
            \Illuminate\Support\Facades\Storage::makeDirectory($dir);
        }

        $nombre = preg_replace('/[^A-Za-z0-9._-]/', '_', $archivo->getClientOriginalName()) ?: 'padron.bin';
        $rel = $archivo->storeAs($dir, time() . '_' . $nombre);
        if ($rel === false) {
            throw new RuntimeException('No se pudo guardar el archivo subido.');
        }

        $abs = storage_path('app/' . $rel);
        if (! is_file($abs)) {
            throw new RuntimeException('Archivo subido no encontrado tras guardar.');
        }

        return $abs;
    }
}
