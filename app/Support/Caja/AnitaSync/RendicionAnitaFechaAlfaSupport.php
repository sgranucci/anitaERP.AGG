<?php

namespace App\Support\Caja\AnitaSync;

/**
 * Formato DD/MM/YY para rendg_fecha_alfa (char 8) en Informix.
 */
final class RendicionAnitaFechaAlfaSupport
{
    public static function desdeFechaEntera(int $fechaEntera): string
    {
        if ($fechaEntera <= 0) {
            return '';
        }

        $s = str_pad((string) $fechaEntera, 8, '0', STR_PAD_LEFT);
        if (strlen($s) !== 8 || ! ctype_digit($s)) {
            return '';
        }

        return substr($s, 6, 2).'/'.substr($s, 4, 2).'/'.substr($s, 2, 2);
    }

    public static function necesitaReparacion(?string $fechaAlfa): bool
    {
        $alfa = trim((string) $fechaAlfa);

        return $alfa === '' || ! str_contains($alfa, '/');
    }
}
