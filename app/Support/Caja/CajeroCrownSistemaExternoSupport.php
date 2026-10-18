<?php

namespace App\Support\Caja;

/**
 * Sistemas externos enlazados a un cajero Crown (Informix cajeroexterno).
 *
 * En C (carga_cajeroexterno.fc): PXSOL, GST, ANITA.
 * En ERP: ANITA se modela como GASTRONOMIA (mozo_gastronomia).
 */
final class CajeroCrownSistemaExternoSupport
{
    public const PXSOL = 'PXSOL';

    public const GST = 'GST';

    /** anitaERP gastronomía (mozo). En Anita/C legado: ANITA. */
    public const GASTRONOMIA = 'GASTRONOMIA';

    /** Alias Informix al importar desde cajeroexterno. */
    public const ANITA_LEGADO = 'ANITA';

    /**
     * @return list<string>
     */
    public static function sistemas(): array
    {
        return [self::PXSOL, self::GST, self::GASTRONOMIA];
    }

    public static function esValido(string $sistema): bool
    {
        return in_array(self::normalizar($sistema), self::sistemas(), true);
    }

    /**
     * Normaliza códigos del C (ANITA → GASTRONOMIA) y trim/mayúsculas.
     */
    public static function normalizar(string $sistema): string
    {
        $s = strtoupper(trim($sistema));
        if ($s === self::ANITA_LEGADO) {
            return self::GASTRONOMIA;
        }

        return $s;
    }

    public static function usaMozo(string $sistema): bool
    {
        return self::normalizar($sistema) === self::GASTRONOMIA;
    }

    public static function usaCodigoExterno(string $sistema): bool
    {
        $s = self::normalizar($sistema);

        return $s === self::PXSOL || $s === self::GST;
    }
}
