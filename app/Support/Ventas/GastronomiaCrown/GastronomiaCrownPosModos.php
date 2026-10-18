<?php

namespace App\Support\Ventas\GastronomiaCrown;

/**
 * Modos del POS Crown (paridad a-comandera / confptovta).
 *
 * Letras Anita (cpvta_modo_uso): R=resto S=sala C=comandera O=restosolo.
 * Comanda (cpvta_comanda): C=sola F/A=factura_y_comanda N/vacío=sin_comanda.
 * Sync: config-pv-gastronomia:sincronizar-anita ← confptovta (cncadmin).
 */
final class GastronomiaCrownPosModos
{
    public const RESTO = 'resto';

    public const RESTOSOLO = 'restosolo';

    public const SALA = 'sala';

    public const COMANDERA = 'comandera';

    public const POLITICA_COMANDA_SOLA = 'sola';

    public const POLITICA_FACTURA_Y_COMANDA = 'factura_y_comanda';

    public const POLITICA_SIN_COMANDA = 'sin_comanda';

    /** @return array<string, string> */
    public static function modos(): array
    {
        return [
            self::RESTO => 'Restaurant (mesas)',
            self::RESTOSOLO => 'Restaurant solo',
            self::SALA => 'Sala',
            self::COMANDERA => 'Comandera',
        ];
    }

    /** @return array<string, string> */
    public static function politicasComanda(): array
    {
        return [
            self::POLITICA_COMANDA_SOLA => 'Solo comanda (marcha)',
            self::POLITICA_FACTURA_Y_COMANDA => 'Factura y comanda',
            self::POLITICA_SIN_COMANDA => 'Sin comanda',
        ];
    }

    public static function esModoValido(?string $modo): bool
    {
        return $modo !== null && isset(self::modos()[$modo]);
    }

    public static function esPoliticaValida(?string $politica): bool
    {
        return $politica !== null && isset(self::politicasComanda()[$politica]);
    }

    public static function usaMesas(?string $modo): bool
    {
        return in_array($modo, [self::RESTO, self::RESTOSOLO], true);
    }

    public static function permiteFacturar(?string $modo, bool $soloMarcha): bool
    {
        if ($soloMarcha) {
            return false;
        }

        return $modo !== self::COMANDERA || true;
    }
}
