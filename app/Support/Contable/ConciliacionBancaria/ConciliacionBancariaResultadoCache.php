<?php

namespace App\Support\Contable\ConciliacionBancaria;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * La exportación no vuelve a correr el mayor: reutiliza la última conciliación del período.
 */
final class ConciliacionBancariaResultadoCache
{
    public static function guardar(int $empresaId, int $cuentacajaId, int $mes, int $anio, array $resultado): void
    {
        try {
            $copia = $resultado;
            if (($copia['cuentacaja'] ?? null) instanceof Model) {
                $cuenta = $copia['cuentacaja'];
                $copia['cuentacaja'] = [
                    'id' => (int) $cuenta->id,
                    'codigo' => (string) ($cuenta->codigo ?? ''),
                    'nombre' => (string) ($cuenta->nombre ?? ''),
                    'cuenta_interbanking' => (string) ($cuenta->cuenta_interbanking ?? ''),
                    'cuentacontable_id' => (int) ($cuenta->cuentacontable_id ?? 0),
                ];
            }
            unset($copia['cuenta_contable']);

            $dir = self::directorio();
            if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                return;
            }
            if (! is_writable($dir)) {
                @chmod($dir, 0775);
            }
            if (! is_writable($dir)) {
                return;
            }

            $tmp = self::ruta($empresaId, $cuentacajaId, $mes, $anio).'.tmp';
            file_put_contents($tmp, serialize($copia), LOCK_EX);
            rename($tmp, self::ruta($empresaId, $cuentacajaId, $mes, $anio));
        } catch (Throwable) {
            // El Excel puede recalcular si la copia no se pudo guardar.
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function leer(int $empresaId, int $cuentacajaId, int $mes, int $anio, int $maxEdadSegundos = 7200): ?array
    {
        $ruta = self::ruta($empresaId, $cuentacajaId, $mes, $anio);
        if (! is_file($ruta)) {
            return null;
        }

        if ($maxEdadSegundos > 0 && (time() - (int) filemtime($ruta)) > $maxEdadSegundos) {
            return null;
        }

        try {
            $datos = unserialize((string) file_get_contents($ruta), ['allowed_classes' => true]);
        } catch (Throwable) {
            return null;
        }

        return is_array($datos) ? $datos : null;
    }

    private static function directorio(): string
    {
        return storage_path('app/conciliacion-bancaria');
    }

    private static function ruta(int $empresaId, int $cuentacajaId, int $mes, int $anio): string
    {
        return self::directorio().sprintf('/e%d_c%d_%04d%02d.ser', $empresaId, $cuentacajaId, $anio, $mes);
    }
}
