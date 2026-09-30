<?php

namespace App\Support\Compras;

use App\Models\Compras\Pagoproveedor_Archivo;
use App\Support\Archivos\ArchivoAdjuntoCacheSupport;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Archivos asociados a una orden de pago (pagoproveedor_archivo).
 * Disco: public/storage/archivos/pagoproveedores/{id}/
 */
final class PagoproveedorArchivoSupport
{
    private const EXTENSIONES_BLOQUEADAS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh', 'bat', 'cmd',
        'js', 'html', 'htm', 'svg', 'htaccess',
    ];

    private const MIME_BLOQUEADOS = [
        'application/x-httpd-php',
        'application/x-php',
        'text/x-php',
        'application/x-httpd-php-source',
    ];

    /**
     * @param  list<UploadedFile|null>  $archivos
     * @return list<array{id: int, ruta: string, nombre: string, mime: string}>
     */
    public static function guardarSubidos(int $pagoproveedorId, array $archivos): array
    {
        $guardados = [];
        try {
            foreach ($archivos as $archivo) {
                if (! $archivo instanceof UploadedFile || ! $archivo->isValid()) {
                    continue;
                }
                $guardados[] = self::guardarUno($pagoproveedorId, $archivo);
            }
        } catch (\Throwable $e) {
            self::eliminar($pagoproveedorId, $guardados);
            throw $e;
        }

        return $guardados;
    }

    /**
     * Conserva los nombres indicados, borra el resto y suma los archivos nuevos.
     *
     * @param  list<string|null>  $nombresConservar
     * @param  list<UploadedFile|null>  $archivosNuevos
     */
    public static function sincronizar(int $pagoproveedorId, array $nombresConservar, array $archivosNuevos): void
    {
        $conservar = [];
        foreach ($nombresConservar as $nombre) {
            $seguro = self::nombreSeguro((string) $nombre);
            if ($seguro !== '') {
                $conservar[$seguro] = true;
            }
        }

        $actuales = Pagoproveedor_Archivo::query()
            ->where('pagoproveedor_id', $pagoproveedorId)
            ->get();

        foreach ($actuales as $archivo) {
            $nombre = (string) $archivo->nombrearchivo;
            if (! isset($conservar[$nombre])) {
                self::borrarRegistro($pagoproveedorId, $archivo);
            }
        }

        self::guardarSubidos($pagoproveedorId, $archivosNuevos);
    }

    /**
     * @param  list<array{id?: int, ruta?: string, nombre?: string}>  $guardados
     */
    public static function eliminar(int $pagoproveedorId, array $guardados): void
    {
        foreach ($guardados as $item) {
            $id = (int) ($item['id'] ?? 0);
            $archivo = $id > 0
                ? Pagoproveedor_Archivo::query()
                    ->where('pagoproveedor_id', $pagoproveedorId)
                    ->whereKey($id)
                    ->first()
                : null;

            if ($archivo instanceof Pagoproveedor_Archivo) {
                self::borrarRegistro($pagoproveedorId, $archivo);
                continue;
            }

            $ruta = (string) ($item['ruta'] ?? '');
            if ($ruta !== '') {
                self::unlinkSiEsDelPago($pagoproveedorId, $ruta);
            }
        }
    }

    public static function urlPublica(int $pagoproveedorId, string $nombrearchivo): string
    {
        $nombre = basename(str_replace('\\', '/', $nombrearchivo));
        $relative = 'archivos/pagoproveedores/'.$pagoproveedorId.'/'.$nombre;
        $url = asset('storage/archivos/pagoproveedores/'.$pagoproveedorId.'/'.rawurlencode($nombre));

        return ArchivoAdjuntoCacheSupport::conVersion($url, public_path('storage/'.$relative));
    }

    /**
     * @return array{id: int, ruta: string, nombre: string, mime: string}
     */
    private static function guardarUno(int $pagoproveedorId, UploadedFile $archivo): array
    {
        self::assertArchivoPermitido($archivo);

        $dir = self::directorio($pagoproveedorId);
        if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta de archivos de la orden de pago.');
        }

        $nombre = self::nombreDisponible($pagoproveedorId, $dir, $archivo->getClientOriginalName());
        $mime = (string) ($archivo->getMimeType() ?: 'application/octet-stream');
        $archivo->move($dir, $nombre);
        $ruta = $dir.DIRECTORY_SEPARATOR.$nombre;

        if (! is_file($ruta)) {
            throw new RuntimeException('No se pudo guardar el archivo '.$nombre.'.');
        }

        $registro = Pagoproveedor_Archivo::query()->create([
            'pagoproveedor_id' => $pagoproveedorId,
            'nombrearchivo' => $nombre,
        ]);

        return [
            'id' => (int) $registro->id,
            'ruta' => $ruta,
            'nombre' => $nombre,
            'mime' => $mime,
        ];
    }

    private static function borrarRegistro(int $pagoproveedorId, Pagoproveedor_Archivo $archivo): void
    {
        $ruta = self::directorio($pagoproveedorId).DIRECTORY_SEPARATOR.basename((string) $archivo->nombrearchivo);
        $archivo->delete();
        self::unlinkSiEsDelPago($pagoproveedorId, $ruta);
    }

    private static function assertArchivoPermitido(UploadedFile $archivo): void
    {
        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        if ($ext !== '' && in_array($ext, self::EXTENSIONES_BLOQUEADAS, true)) {
            throw new RuntimeException('No se puede adjuntar un archivo con extensión .'.$ext.'.');
        }

        $mime = strtolower((string) ($archivo->getMimeType() ?: ''));
        if ($mime !== '' && in_array($mime, self::MIME_BLOQUEADOS, true)) {
            throw new RuntimeException('El tipo de archivo no está permitido.');
        }
    }

    private static function nombreDisponible(int $pagoproveedorId, string $dir, string $original): string
    {
        $nombre = self::nombreSeguro($original);
        if ($nombre === '') {
            $nombre = 'archivo';
        }

        $ext = pathinfo($nombre, PATHINFO_EXTENSION);
        $stem = pathinfo($nombre, PATHINFO_FILENAME);
        if ($stem === '') {
            $stem = 'archivo';
        }

        $candidato = $nombre;
        $n = 2;
        while (self::nombreOcupado($pagoproveedorId, $dir, $candidato)) {
            $sufijo = '_'.$n;
            $candidato = $ext !== '' ? $stem.$sufijo.'.'.$ext : $stem.$sufijo;
            $n++;
            if ($n > 50) {
                $candidato = $stem.'_'.date('YmdHis').($ext !== '' ? '.'.$ext : '');
                break;
            }
        }

        return $candidato;
    }

    private static function nombreOcupado(int $pagoproveedorId, string $dir, string $nombre): bool
    {
        if (is_file($dir.DIRECTORY_SEPARATOR.$nombre)) {
            return true;
        }

        return Pagoproveedor_Archivo::query()
            ->where('pagoproveedor_id', $pagoproveedorId)
            ->where('nombrearchivo', $nombre)
            ->exists();
    }

    public static function nombreSeguro(string $original): string
    {
        $base = basename(str_replace('\\', '/', trim($original)));
        $base = preg_replace('/[^\p{L}\p{N}._\-]+/u', '_', $base) ?? '';
        $base = trim((string) $base, '._');
        if ($base === '' || $base === '.' || $base === '..') {
            return '';
        }
        if (strlen($base) > 180) {
            $ext = pathinfo($base, PATHINFO_EXTENSION);
            $stem = pathinfo($base, PATHINFO_FILENAME);
            $base = substr($stem, 0, 160).($ext !== '' ? '.'.$ext : '');
        }

        return $base;
    }

    private static function directorio(int $pagoproveedorId): string
    {
        return public_path('storage/archivos/pagoproveedores/'.$pagoproveedorId);
    }

    private static function unlinkSiEsDelPago(int $pagoproveedorId, string $ruta): void
    {
        if ($ruta === '' || ! is_file($ruta)) {
            return;
        }

        $dir = realpath(self::directorio($pagoproveedorId));
        $real = realpath($ruta);
        if ($dir === false || $real === false) {
            return;
        }

        $prefijo = rtrim($dir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (! str_starts_with($real, $prefijo)) {
            return;
        }

        @unlink($real);
    }
}
