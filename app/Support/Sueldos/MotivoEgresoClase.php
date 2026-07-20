<?php

namespace App\Support\Sueldos;

/**
 * Clasificacion normativa del motivo de egreso. Permite que las formulas de la
 * liquidacion final decidan que conceptos disparan (indemnizaciones, preaviso,
 * integracion mes) segun la causa, sin depender del codigo libre del motivo.
 *
 * Se lee en las formulas con la variable empleado.motivo_egreso_clase, ej.:
 *   si(empleado.motivo_egreso_clase == "despido_sc", ...)
 */
class MotivoEgresoClase
{
    /** @var array<string, string> */
    public const CLASES = [
        '' => 'Sin clasificar',
        'renuncia' => 'Renuncia (art. 240)',
        'despido_sc' => 'Despido sin causa (art. 245)',
        'despido_cc' => 'Despido con causa',
        'mutuo_acuerdo' => 'Mutuo acuerdo (art. 241)',
        'periodo_prueba' => 'Fin período de prueba (art. 92 bis)',
        'fin_contrato' => 'Fin de contrato / obra',
        'abandono' => 'Abandono de trabajo (art. 244)',
        'jubilacion' => 'Jubilación',
        'fallecimiento' => 'Fallecimiento (art. 248)',
        'otro' => 'Otro',
    ];

    public const DEFAULT = '';

    public static function etiqueta(?string $clase): string
    {
        return self::CLASES[(string) $clase] ?? (string) $clase;
    }

    public static function normalizar(?string $clase): string
    {
        $c = trim((string) $clase);

        return array_key_exists($c, self::CLASES) ? $c : self::DEFAULT;
    }

    /** @return list<string> */
    public static function permitidas(): array
    {
        return array_keys(self::CLASES);
    }
}
