<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

final class SicoreCriteriosSupport
{
    public const COMPRAS = 'compras';

    public const VENTAS = 'ventas';

    public const SUELDOS = 'sueldos';

    /** @var list<string> */
    public const CRITERIOS_PROCESO = [
        self::COMPRAS,
        self::VENTAS,
        self::SUELDOS,
    ];

    /** @var array<string, list<string>> */
    public const CRITERIOS_CONFIG_POR_PROCESO = [
        self::VENTAS => ['ventas_perc_iva', 'ventas_perc_no_categ'],
        self::COMPRAS => ['compras_ganancias', 'compras_iva'],
        self::SUELDOS => ['sueldos'],
    ];

    public static function etiquetaProceso(string $criterio): string
    {
        return match ($criterio) {
            self::VENTAS => 'Ventas',
            self::COMPRAS => 'Compras',
            self::SUELDOS => 'Sueldos',
            default => $criterio,
        };
    }

    /**
     * @return list<string>
     */
    public static function criteriosConfigParaProceso(string $proceso): array
    {
        return self::CRITERIOS_CONFIG_POR_PROCESO[$proceso] ?? [];
    }
}
