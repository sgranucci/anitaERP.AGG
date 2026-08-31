<?php

namespace App\Support\Sueldos;

/**
 * Catalogos de clasificacion del concepto de liquidacion (Anita haberes).
 * Tipo (hab_tipo/rango), momento de liquidacion (hab_momento) y base/acumulador (hab_total).
 */
class ConceptoTipo
{
    /** @var array<string, string> Tipo de concepto */
    public const TIPOS = [
        'remunerativo' => 'Remunerativo',
        'no_remunerativo' => 'No remunerativo',
        'descuento' => 'Descuento',
        'aporte' => 'Aporte (trabajador)',
        'contribucion' => 'Contribución empleador (solo recibo CE)',
        'retencion' => 'Retención',
        'asignacion' => 'Asignación familiar',
        'neto' => 'Neto',
        'informativo' => 'Informativo / solo reportes',
    ];

    /** Tipos que no suman a bruto, descuentos ni neto. */
    public const TIPOS_SIN_IMPACTO_TOTALES = [
        'contribucion',
        'informativo',
    ];

    /** Tipos que pueden imputar en el asiento de devengamiento (no neto ni informativo). */
    public const TIPOS_IMPUTABLES = [
        'remunerativo',
        'no_remunerativo',
        'descuento',
        'aporte',
        'contribucion',
        'retencion',
        'asignacion',
    ];

    /** @var array<string, string> Momento de liquidacion (Anita hab_momento) */
    public const MOMENTOS = [
        'mensual' => 'Siempre / mensual',
        'quincena_1' => '1ra. quincena',
        'quincena_2' => '2da. quincena',
        'mensual_2q' => 'Mensual / 2da. quincena',
        'no_liquida' => 'No se liquida',
        'vacaciones' => 'Vacaciones',
        'vacaciones_1q' => 'Vacaciones p/quincena',
        'vacaciones_2q' => 'Vacaciones s/quincena',
        'sac' => 'S.A.C.',
        'final' => 'Liquidación final',
        'especial' => 'Especial',
    ];

    /** @var array<string, string> Base/acumulador al que impacta (simplificacion de hab_total) */
    public const BASES = [
        'remunerativo' => 'Bruto remunerativo',
        'no_remunerativo' => 'Bruto no remunerativo',
        'descuentos' => 'Descuentos',
        'neto' => 'Neto',
    ];

    public const TIPO_DEFAULT = 'remunerativo';

    public const MOMENTO_DEFAULT = 'mensual';

    public static function etiquetaTipo(?string $tipo): string
    {
        return self::TIPOS[$tipo] ?? (string) $tipo;
    }

    public static function etiquetaMomento(?string $momento): string
    {
        return self::MOMENTOS[$momento] ?? (string) $momento;
    }

    public static function normalizarTipo(?string $tipo): string
    {
        $tipo = trim((string) $tipo);

        return isset(self::TIPOS[$tipo]) ? $tipo : self::TIPO_DEFAULT;
    }

    public static function normalizarMomento(?string $momento): string
    {
        $momento = trim((string) $momento);

        return isset(self::MOMENTOS[$momento]) ? $momento : self::MOMENTO_DEFAULT;
    }

    /** @return list<string> */
    public static function tiposPermitidos(): array
    {
        return array_keys(self::TIPOS);
    }

    /** @return list<string> */
    public static function momentosPermitidos(): array
    {
        return array_keys(self::MOMENTOS);
    }

    /** @return list<string> */
    public static function basesPermitidas(): array
    {
        return array_keys(self::BASES);
    }
}
