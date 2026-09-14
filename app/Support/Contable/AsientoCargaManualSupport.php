<?php

namespace App\Support\Contable;

/**
 * Detecta si el asiento del formulario fue editado a mano (no regenerar al grabar).
 *
 * Flags típicos:
 * - 'N' / '0' / '' → generado automáticamente
 * - 'S' / '1' / 'Y' → línea o renglón cargado/editado manualmente
 */
final class AsientoCargaManualSupport
{
    /**
     * @param  list<mixed>|mixed  $flags
     */
    public static function fueEditadoManual(mixed $flags): bool
    {
        if (! is_array($flags)) {
            return false;
        }

        foreach ($flags as $flag) {
            if (self::flagEsManual($flag)) {
                return true;
            }
        }

        return false;
    }

    public static function flagEsManual(mixed $flag): bool
    {
        $v = strtoupper(trim((string) $flag));

        return $v !== '' && $v !== 'N' && $v !== '0';
    }
}
