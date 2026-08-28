<?php

namespace App\Support\Ventas;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * El Bierzo: Factura / ND / NC letra B cobra percepción IIBB CABA (jurisdicción 901).
 *
 * En el resto de clientes la letra B omite IIBB y perc. IVA 3 % (RG 5329).
 * Acá CABA no se omite: padrón AGIP si hay alícuota; si no, tasa de descarte
 * de provincia_tasaiibb (como si el cliente fuera local a CABA).
 */
final class ElBierzoFacturaBPercepcionCabaSupport
{
    public const JURISDICCION = 901;

    public const FLAG = 'forzar_percepcion_iibb_caba';

    public static function aplicaEnEntorno(): bool
    {
        return EntornoEmpresaSupport::esElBierzo();
    }

    public static function correspondePorLetra(?string $letra): bool
    {
        return self::aplicaEnEntorno() && strtoupper(trim((string) $letra)) === 'B';
    }

    /**
     * @param  array<string, mixed>  $dataCliente
     */
    public static function debeForzarDesdeCliente(array $dataCliente): bool
    {
        return self::aplicaEnEntorno() && ! empty($dataCliente[self::FLAG]);
    }

    public static function esJurisdiccionCaba(mixed $jurisdiccion): bool
    {
        return (int) $jurisdiccion === self::JURISDICCION;
    }
}
