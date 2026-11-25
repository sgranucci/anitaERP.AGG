<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Sueldos_Recibo_Firma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Firma del empleador en el pie del recibo Anexo III.
 *
 * Prioridad: fila de la empresa → fila global (empresa_id null) → config/env → default PNG.
 */
final class ReciboFirmaEmpleadorSupport
{
    public const DISCO = 'public';

    public const DIR_RELATIVO = 'sueldos/firmas';

    public static function nombre(?int $empresaId = null): string
    {
        return self::paraEmpresa($empresaId)['nombre'];
    }

    public static function cargo(?int $empresaId = null): string
    {
        return self::paraEmpresa($empresaId)['cargo'];
    }

    public static function dataUri(?int $empresaId = null): ?string
    {
        return self::paraEmpresa($empresaId)['imagen_uri'];
    }

    /**
     * @return array{nombre: string, cargo: string, imagen: string, imagen_uri: ?string, origen: string}
     */
    public static function paraEmpresa(?int $empresaId = null): array
    {
        $fila = self::resolverFila($empresaId);
        $nombreDefault = (string) config('sueldos.recibo_firma_nombre', 'ERIKA PEREZ');
        $cargoDefault = (string) config('sueldos.recibo_firma_cargo', 'CAPITAL HUMANO');

        $nombre = trim((string) ($fila?->nombre ?? ''));
        $cargo = trim((string) ($fila?->cargo ?? ''));
        $imagenRel = trim((string) ($fila?->imagen ?? ''));

        $ruta = self::rutaAbsolutaDesdeRelativo($imagenRel) ?? self::rutaAbsolutaDefault();
        $uri = $ruta !== null ? self::dataUriDesdeRuta($ruta) : null;

        $origen = 'default';
        if ($fila !== null) {
            $origen = $fila->empresa_id === null ? 'global' : 'empresa';
        }

        return [
            'nombre' => $nombre !== '' ? $nombre : $nombreDefault,
            'cargo' => $cargo !== '' ? $cargo : $cargoDefault,
            'imagen' => $imagenRel,
            'imagen_uri' => $uri,
            'origen' => $origen,
        ];
    }

    /**
     * Preview para pantallas admin: siempre data-uri (no depende de /storage ni APP_URL).
     */
    public static function previewSrc(?int $empresaId = null, ?string $relativo = null): ?string
    {
        $rel = trim((string) ($relativo ?? ''));
        if ($rel !== '') {
            $ruta = self::rutaAbsolutaDesdeRelativo($rel);
            if ($ruta !== null) {
                return self::dataUriDesdeRuta($ruta);
            }
        }

        return self::dataUri($empresaId);
    }

    public static function guardar(
        ?int $empresaId,
        string $nombre,
        string $cargo,
        ?UploadedFile $archivo = null,
        bool $quitarImagen = false
    ): Sueldos_Recibo_Firma {
        $fila = self::queryFila($empresaId) ?? new Sueldos_Recibo_Firma([
            'empresa_id' => $empresaId,
        ]);

        $fila->nombre = trim($nombre);
        $fila->cargo = trim($cargo);

        $actual = trim((string) ($fila->imagen ?? ''));

        if ($quitarImagen) {
            self::borrarImagen($actual);
            $fila->imagen = null;
        } elseif ($archivo instanceof UploadedFile && $archivo->isValid()) {
            $destino = self::guardarArchivo($empresaId, $archivo);
            if ($actual !== '' && $actual !== $destino) {
                self::borrarImagen($actual);
            }
            $fila->imagen = $destino;
        }

        $fila->save();

        return $fila;
    }

    public static function rutaRelativaEmpresa(?int $empresaId, string $ext = 'png'): string
    {
        $slug = $empresaId === null || $empresaId <= 0 ? 'global' : 'empresa_'.$empresaId;

        return self::DIR_RELATIVO.'/'.$slug.'.'.$ext;
    }

    private static function resolverFila(?int $empresaId): ?Sueldos_Recibo_Firma
    {
        if (! self::tablaExiste()) {
            return null;
        }

        if ($empresaId !== null && $empresaId > 0) {
            $deEmpresa = self::queryFila($empresaId);
            if ($deEmpresa !== null) {
                return $deEmpresa;
            }
        }

        return self::queryFila(null);
    }

    private static function queryFila(?int $empresaId): ?Sueldos_Recibo_Firma
    {
        if (! self::tablaExiste()) {
            return null;
        }

        $q = Sueldos_Recibo_Firma::query();
        if ($empresaId === null || $empresaId <= 0) {
            return $q->whereNull('empresa_id')->first();
        }

        return $q->where('empresa_id', $empresaId)->first();
    }

    private static function tablaExiste(): bool
    {
        static $ok = null;
        if ($ok === null) {
            try {
                $ok = Schema::hasTable('sueldos_recibo_firma');
            } catch (\Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }

    private static function rutaAbsolutaDesdeRelativo(string $relativo): ?string
    {
        $rel = ltrim(str_replace(['..', '\\'], '', $relativo), '/');
        if ($rel === '' || ! str_starts_with($rel, self::DIR_RELATIVO.'/')) {
            return null;
        }
        if (! Storage::disk(self::DISCO)->exists($rel)) {
            return null;
        }

        return Storage::disk(self::DISCO)->path($rel);
    }

    private static function rutaAbsolutaDefault(): ?string
    {
        $config = trim((string) config('sueldos.recibo_firma_imagen', ''));
        if ($config !== '') {
            $ruta = $config[0] === '/' ? $config : base_path($config);
            if (is_file($ruta) && is_readable($ruta)) {
                return $ruta;
            }
        }

        $default = resource_path('firmas/firma_erika_perez.png');
        if (is_file($default) && is_readable($default)) {
            return $default;
        }

        return null;
    }

    private static function dataUriDesdeRuta(string $ruta): ?string
    {
        $data = @file_get_contents($ruta);
        if ($data === false || $data === '') {
            return null;
        }

        $mime = function_exists('mime_content_type')
            ? (string) (mime_content_type($ruta) ?: 'image/png')
            : 'image/png';
        if (! str_starts_with($mime, 'image/')) {
            $mime = 'image/png';
        }

        return 'data:'.$mime.';base64,'.base64_encode($data);
    }

    private static function guardarArchivo(?int $empresaId, UploadedFile $archivo): string
    {
        $ext = strtolower((string) $archivo->getClientOriginalExtension());
        if (! in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
            $ext = 'png';
        }
        $destino = self::rutaRelativaEmpresa($empresaId, $ext);
        Storage::disk(self::DISCO)->makeDirectory(self::DIR_RELATIVO);
        Storage::disk(self::DISCO)->putFileAs(
            self::DIR_RELATIVO,
            $archivo,
            basename($destino)
        );

        return $destino;
    }

    private static function borrarImagen(string $relativo): void
    {
        $rel = trim($relativo);
        if ($rel === '' || ! str_starts_with($rel, self::DIR_RELATIVO.'/')) {
            return;
        }
        if (Storage::disk(self::DISCO)->exists($rel)) {
            Storage::disk(self::DISCO)->delete($rel);
        }
    }
}
