<?php

namespace App\Support\Solicitudpago;

use App\Models\Solicitudpago\Solicitudpago_Archivo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Archivos de SP viven en el mount Anita /scan/compras/sol_files
 * con nombre disco SOLP-{codigo}.{nombre_anita}.
 * En BD se guarda el nombre Anita (compatible con solpagoarch.solpa_archivo).
 */
final class SolicitudpagoArchivoStorageSupport
{
    public static function diskName(): string
    {
        return (string) config('solicitudpago.archivos.disk', 'solicitudpago_scan');
    }

    public static function root(): string
    {
        return rtrim((string) config('solicitudpago.archivos.root', '/scan/compras/sol_files'), '/');
    }

    public static function prefijo(): string
    {
        return (string) config('solicitudpago.archivos.prefijo', 'SOLP-');
    }

    public static function esRutaLocalErp(string $archivo): bool
    {
        return str_starts_with(ltrim($archivo, '/'), 'solicitudpago/');
    }

    /**
     * Nombre relativo en el disco scan (SOLP-{codigo}.{nombre}).
     */
    public static function nombreEnDisco(int $codigoSp, string $nombreAnita): string
    {
        $nombre = self::sanitizarNombreAnita($nombreAnita);

        return self::prefijo().$codigoSp.'.'.$nombre;
    }

    public static function sanitizarNombreAnita(string $nombre): string
    {
        $nombre = basename(str_replace(["\0", '\\'], '', trim($nombre)));
        $nombre = preg_replace('/\s+/', '_', $nombre) ?? $nombre;
        $nombre = preg_replace('/[^\w.\-()\[\]]+/u', '_', $nombre) ?? $nombre;

        return self::recortar($nombre !== '' ? $nombre : 'archivo', 200);
    }

    /**
     * Ruta absoluta del archivo físico, o null si no existe.
     */
    public static function rutaAbsoluta(Solicitudpago_Archivo $arch, ?int $codigoSp = null): ?string
    {
        $archivo = trim((string) ($arch->archivo ?? ''));
        if ($archivo === '') {
            return null;
        }

        if (self::esRutaLocalErp($archivo)) {
            $path = Storage::disk('public')->path($archivo);

            return is_file($path) ? $path : null;
        }

        $codigo = $codigoSp;
        if ($codigo === null || $codigo <= 0) {
            $codigo = (int) optional($arch->solicitudpagos)->codigo;
        }
        if ($codigo <= 0 && $arch->solicitudpago_id) {
            $codigo = (int) (\App\Models\Solicitudpago\Solicitudpago::query()
                ->whereKey($arch->solicitudpago_id)
                ->value('codigo') ?? 0);
        }

        foreach (self::candidatosRelativos($archivo, $codigo > 0 ? $codigo : null) as $relativo) {
            $abs = self::root().'/'.$relativo;
            if (is_file($abs) && is_readable($abs)) {
                return $abs;
            }
        }

        return null;
    }

    public static function existe(Solicitudpago_Archivo $arch, ?int $codigoSp = null): bool
    {
        return self::rutaAbsoluta($arch, $codigoSp) !== null;
    }

    /**
     * Guarda un upload en el mount scan. Devuelve metadatos para la fila ERP.
     *
     * @return array{archivo: string, nombre_original: string, ruta_disco: string}
     */
    public static function guardarUpload(UploadedFile $file, int $codigoSp): array
    {
        if ($codigoSp <= 0) {
            throw new \InvalidArgumentException('Código de SP inválido para guardar archivo.');
        }

        $nombreOriginal = (string) $file->getClientOriginalName();
        $nombreAnita = self::sanitizarNombreAnita($nombreOriginal !== '' ? $nombreOriginal : ('archivo.'.$file->getClientOriginalExtension()));
        $nombreAnita = self::nombreAnitaUnico($codigoSp, $nombreAnita);
        $nombreDisco = self::nombreEnDisco($codigoSp, $nombreAnita);

        $contenido = file_get_contents($file->getRealPath());
        if ($contenido === false) {
            throw new \RuntimeException('No se pudo leer el archivo subido.');
        }

        $ok = Storage::disk(self::diskName())->put($nombreDisco, $contenido);
        if (! $ok) {
            // Fallback directo por si el disk no está registrado aún.
            $abs = self::root().'/'.$nombreDisco;
            if (@file_put_contents($abs, $contenido) === false) {
                throw new \RuntimeException('No se pudo grabar el archivo en '.self::root());
            }
        }

        return [
            'archivo' => $nombreAnita,
            'nombre_original' => self::recortar($nombreOriginal !== '' ? $nombreOriginal : $nombreAnita, 255),
            'ruta_disco' => $nombreDisco,
        ];
    }

    public static function eliminar(Solicitudpago_Archivo $arch, ?int $codigoSp = null): void
    {
        $archivo = trim((string) ($arch->archivo ?? ''));
        if ($archivo === '') {
            return;
        }

        if (self::esRutaLocalErp($archivo)) {
            if (Storage::disk('public')->exists($archivo)) {
                Storage::disk('public')->delete($archivo);
            }

            return;
        }

        $abs = self::rutaAbsoluta($arch, $codigoSp);
        if ($abs !== null && is_file($abs)) {
            @unlink($abs);
        }
    }

    /**
     * Nombre Anita a enviar / comparar (basename del campo archivo).
     */
    public static function nombreParaAnita(Solicitudpago_Archivo $arch): string
    {
        $archivo = trim((string) ($arch->archivo ?? ''));
        if ($archivo === '' || self::esRutaLocalErp($archivo)) {
            return self::sanitizarNombreAnita((string) ($arch->nombre_original ?: basename($archivo)));
        }

        return self::sanitizarNombreAnita(basename($archivo));
    }

    /**
     * @return list<string>
     */
    private static function candidatosRelativos(string $archivo, ?int $codigoSp): array
    {
        $base = basename($archivo);
        $baseUnderscore = str_replace(' ', '_', $base);
        $out = [];

        if (str_starts_with($base, self::prefijo())) {
            $out[] = $base;
        }

        if ($codigoSp !== null && $codigoSp > 0) {
            $out[] = self::prefijo().$codigoSp.'.'.$base;
            if ($baseUnderscore !== $base) {
                $out[] = self::prefijo().$codigoSp.'.'.$baseUnderscore;
            }
        }

        // Legacy Anita muy antiguo: SOLP-0.{nombre}
        $out[] = self::prefijo().'0.'.$base;
        if ($baseUnderscore !== $base) {
            $out[] = self::prefijo().'0.'.$baseUnderscore;
        }

        $out[] = $base;
        if ($baseUnderscore !== $base) {
            $out[] = $baseUnderscore;
        }

        return array_values(array_unique($out));
    }

    private static function nombreAnitaUnico(int $codigoSp, string $nombreAnita): string
    {
        $ext = pathinfo($nombreAnita, PATHINFO_EXTENSION);
        $stem = pathinfo($nombreAnita, PATHINFO_FILENAME);
        $candidato = $nombreAnita;
        for ($i = 0; $i < 50; $i++) {
            $rel = self::nombreEnDisco($codigoSp, $candidato);
            if (! is_file(self::root().'/'.$rel)) {
                return $candidato;
            }
            $sufijo = '_'.($i + 2);
            $candidato = $ext !== ''
                ? self::recortar($stem.$sufijo, 200 - strlen($ext) - 1).'.'.$ext
                : self::recortar($stem.$sufijo, 200);
        }

        return self::recortar($stem.'_'.Str::lower(Str::random(6)), 200).($ext !== '' ? '.'.$ext : '');
    }

    private static function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
