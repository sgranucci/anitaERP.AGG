<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

/**
 * Columnas del listado IVA ventas (equivalente lvtamae listado 1 en Anita).
 */
final class IvaVentasColumnasSupport
{
    /** @var list<array{key: string, label: string}> */
    public const COLUMNAS = [
        ['key' => 'no_gravado', 'label' => 'No gravado'],
        ['key' => 'exento', 'label' => 'Exento'],
        ['key' => 'neto_gravado', 'label' => 'Neto Grav.'],
        ['key' => 'imp_interno', 'label' => 'Imp.Interno'],
        ['key' => 'perc_iibb', 'label' => 'Perc.IIBB'],
        ['key' => 'iva', 'label' => 'IVA'],
        ['key' => 'total', 'label' => 'Total'],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::COLUMNAS, 'key');
    }

    /**
     * @param  array<string, float>  $montos
     * @return array<string, float>
     */
    public static function montosVacios(): array
    {
        $out = [];
        foreach (self::keys() as $key) {
            $out[$key] = 0.0;
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $acum
     * @param  array<string, float>  $delta
     */
    public static function acumular(array &$acum, array $delta): void
    {
        foreach (self::keys() as $key) {
            $acum[$key] = ($acum[$key] ?? 0.0) + (float) ($delta[$key] ?? 0);
        }
    }
}
