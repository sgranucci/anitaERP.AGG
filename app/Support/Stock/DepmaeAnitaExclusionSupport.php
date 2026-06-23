<?php

namespace App\Support\Stock;

/** Omite depósitos Anita que representan máquinas (codigo numérico > umbral). */
final class DepmaeAnitaExclusionSupport
{
    public static function codigoMaximoPermitido(): int
    {
        return max(0, (int) config('stock.depmae_anita_codigo_maximo', 100000));
    }

    public static function debeOmitirCodigo(?string $codigo): bool
    {
        $codigo = trim((string) ($codigo ?? ''));
        if ($codigo === '') {
            return true;
        }

        if (! preg_match('/^\d+$/', $codigo)) {
            return false;
        }

        return (int) $codigo > self::codigoMaximoPermitido();
    }
}
