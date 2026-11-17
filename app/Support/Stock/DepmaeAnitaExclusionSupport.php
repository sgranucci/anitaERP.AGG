<?php

namespace App\Support\Stock;

/** Omite depósitos Anita que representan máquinas (codigo numérico > umbral). */
final class DepmaeAnitaExclusionSupport
{
    /**
     * Copias consolidadas en AC (Anita acncadmin) con nombre de casino.
     * El maestro real vive en el bridge local de cada sala.
     *
     * @var list<string>
     */
    private const PREFIJOS_COPIA_SALA_EN_AC = [
        'CIPOLLETTI -',
        'CATRIEL -',
        'LAS GRUTAS -',
        'GRAL ROCA -',
        'GRAL. ROCA -',
        'CHOELE -',
        'CHOELE CHOEL -',
        'VIEDMA -',
        'EL FARO -',
        'HOTEL LAS GRUTAS',
        'HOTEL ROCA',
        'VILLA REGINA -',
        'CINCO SALTOS -',
    ];

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

    /**
     * En fuente AC, no importar depósitos que son espejo de otra sala.
     */
    public static function debeOmitirCopiaSalaEnAc(?string $nombre, ?string $salaCodigo = null): bool
    {
        if ($salaCodigo !== null && strtoupper(trim($salaCodigo)) !== 'AC') {
            return false;
        }

        $nombre = strtoupper(trim((string) ($nombre ?? '')));
        if ($nombre === '') {
            return false;
        }

        foreach (self::PREFIJOS_COPIA_SALA_EN_AC as $prefijo) {
            if (str_starts_with($nombre, $prefijo)) {
                return true;
            }
        }

        return false;
    }
}
