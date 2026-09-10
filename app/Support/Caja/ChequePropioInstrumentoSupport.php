<?php

namespace App\Support\Caja;

/**
 * Instrumento del cheque propio: carácter legal vs canal Anita (para_dep / negociable).
 */
final class ChequePropioInstrumentoSupport
{
    public static function negociableDesdeChequera(?string $tipochequera): string
    {
        return strtoupper(trim((string) $tipochequera)) === 'E' ? 'E' : 'N';
    }

    /** Anita cpro_negociable: N física / E electrónica. */
    public static function negociable(string $valor, ?string $tipochequera = 'F'): string
    {
        $v = strtoupper(trim($valor));
        if ($v === 'E' || $v === 'N') {
            return $v;
        }

        return self::negociableDesdeChequera($tipochequera);
    }

    /** Default Anita pago.c en CHP emitidos. */
    public static function paraDepDefault(): string
    {
        return 'E';
    }

    public static function paraDep(string $valor, string $fallback = 'E'): string
    {
        $v = strtoupper(trim($valor));
        if (in_array($v, ['E', 'N', 'S', 'O'], true)) {
            return $v;
        }

        return $fallback === '' ? 'E' : $fallback;
    }

    public static function nroEcheq(string $negociable, string $numerocheque): string
    {
        if (strtoupper(trim($negociable)) !== 'E') {
            return '';
        }

        return trim($numerocheque);
    }
}
