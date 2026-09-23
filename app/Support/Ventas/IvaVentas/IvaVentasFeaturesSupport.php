<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

/**
 * Flags del reporte IVA ventas (Bingo/FSL, unidades de negocio, host).
 * Defaults por EMPRESA; override con IVA_VENTAS_* en .env.
 */
final class IvaVentasFeaturesSupport
{
    public static function bingoFsl(): bool
    {
        return (bool) config('iva_ventas.features.bingo_fsl', false);
    }

    public static function unidadesNegocio(): bool
    {
        return (bool) config('iva_ventas.features.unidades_negocio', false);
    }

    public static function clasificarPorHost(): bool
    {
        return (bool) config('iva_ventas.features.clasificar_por_host', false);
    }

    public static function completarFslAnita(): bool
    {
        return (bool) config('iva_ventas.features.completar_fsl_anita', false);
    }

    /**
     * @return array{
     *   bingo_fsl: bool,
     *   unidades_negocio: bool,
     *   clasificar_por_host: bool,
     *   completar_fsl_anita: bool
     * }
     */
    public static function all(): array
    {
        return [
            'bingo_fsl' => self::bingoFsl(),
            'unidades_negocio' => self::unidadesNegocio(),
            'clasificar_por_host' => self::clasificarPorHost(),
            'completar_fsl_anita' => self::completarFslAnita(),
        ];
    }
}
