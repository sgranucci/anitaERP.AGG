<?php

namespace App\Support\Caja\AnitaSync;

use App\Models\Caja\Cuentacaja;
use RuntimeException;

/**
 * Mapeo cuenta de caja ERP → rendv_codigo Informix por empresa.
 */
final class RendicionEstacionamientoRendvalorCodigoSupport
{
    public const FAMILIA_EFECTIVO = 'efectivo';

    public const FAMILIA_MERCADOPAGO = 'mercadopago';

    public const FAMILIA_FISERV = 'fiserv';

    public const FAMILIA_TOTALCOIN = 'totalcoin';

    public const FAMILIA_CANJE_TARJETA = 'canje_tarjeta';

    /**
     * Cuentas puente que no deben replicarse en rendvalor.
     * Estacionamiento no usa cuenta TOTEM Waitry (solo gastronomía).
     */
    public static function omitirEnRendvalorAnita(Cuentacaja $cuenta): bool
    {
        return false;
    }

    public static function familiaDesdeCuentacaja(Cuentacaja $cuenta): ?string
    {
        $codigo = mb_strtoupper(trim((string) $cuenta->codigo));
        $texto = mb_strtoupper(trim((string) $cuenta->nombre).' '.$codigo);

        if (str_contains($texto, 'MERCADO PAGO') || str_contains($texto, 'MERCADOPAGO') || str_contains($texto, ' GMEP')) {
            return self::FAMILIA_MERCADOPAGO;
        }
        if (str_contains($texto, 'TOTALCOIN') || str_contains($texto, 'TOTAL COIN')) {
            return self::FAMILIA_TOTALCOIN;
        }
        if (str_contains($texto, 'FISERV')) {
            return self::FAMILIA_FISERV;
        }
        if (str_contains($texto, 'CANJE TARJETA') || str_contains($texto, ' CTG')) {
            return self::FAMILIA_CANJE_TARJETA;
        }
        if (str_contains($texto, 'EFECTIVO') || str_contains($texto, 'CAJA PESOS')) {
            return self::FAMILIA_EFECTIVO;
        }

        return null;
    }

    public static function codigoParaEmpresa(int $empresaId, string $familia): int
    {
        $mapa = config('rendicion_estacionamiento_anita.codigos_rendvalor', []);
        $empresa = $mapa[$empresaId] ?? null;
        if (! is_array($empresa) || ! isset($empresa[$familia])) {
            throw new RuntimeException(
                'No hay código rendvalor Anita para empresa #'.$empresaId.' y medio «'.$familia.'».'
            );
        }

        return (int) $empresa[$familia];
    }

    public static function codigoDesdeCuentacaja(int $empresaId, Cuentacaja $cuenta): int
    {
        $familia = self::familiaDesdeCuentacaja($cuenta);
        if ($familia === null) {
            throw new RuntimeException(
                'La cuenta de caja «'.($cuenta->nombre ?? $cuenta->codigo).'» no se reconoce como '
                .'Efectivo, Mercado Pago, FISERV, Totalcoin ni Canje tarjeta para rendición Anita.'
            );
        }

        return self::codigoParaEmpresa($empresaId, $familia);
    }
}
