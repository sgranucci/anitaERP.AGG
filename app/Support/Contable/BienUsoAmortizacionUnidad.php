<?php

namespace App\Support\Contable;

/**
 * Unidades de amortización de bienes de uso (Anita biemae.def).
 *
 * BIEM_AMORT_UNID='0', BIEM_AMORT_ANIO='1', BIEM_AMORT_MES='2',
 * BIEM_SIN_AMORT='3', BIEM_REAL_MENSUAL='4'
 */
final class BienUsoAmortizacionUnidad
{
    public const UNIDAD = '0';

    public const ANIO = '1';

    public const MES = '2';

    public const SIN_AMORT = '3';

    public const REAL_MENSUAL = '4';

    /** @return list<string> */
    public static function todas(): array
    {
        return [
            self::UNIDAD,
            self::ANIO,
            self::MES,
            self::SIN_AMORT,
            self::REAL_MENSUAL,
        ];
    }

    public static function esValida(?string $valor): bool
    {
        return $valor !== null && in_array($valor, self::todas(), true);
    }

    /** @return list<array{valor: string, nombre: string}> */
    public static function opciones(): array
    {
        return [
            ['valor' => self::UNIDAD, 'nombre' => 'Por unidad'],
            ['valor' => self::ANIO, 'nombre' => 'Anual'],
            ['valor' => self::MES, 'nombre' => 'Mensual'],
            ['valor' => self::SIN_AMORT, 'nombre' => 'Sin amortización'],
            ['valor' => self::REAL_MENSUAL, 'nombre' => 'Real mensual'],
        ];
    }
}
