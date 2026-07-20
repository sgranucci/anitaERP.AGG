<?php

namespace App\Support\Sueldos;

/**
 * Estados de un empleado de sueldos (columna char(1) empleado_sueldos.estado).
 *
 * Semántica ERP y mapeo con el flag de estado de Anita (emp_flag_estado):
 *   P = provisorio (alta provisoria pendiente de autorización)  -> Anita 'A'
 *   A = activo (dado de alta definitiva / vigente)              -> Anita ' '
 *   B = baja                                                     -> Anita 'B'
 */
class EmpleadoEstados
{
    public const PROVISORIO = 'P';

    public const ACTIVO = 'A';

    public const BAJA = 'B';

    /** @var array<string, string> */
    public const LABELS = [
        self::PROVISORIO => 'Alta provisoria',
        self::ACTIVO => 'Activo',
        self::BAJA => 'Baja',
    ];

    /** @var array<string, string> */
    public const BADGES = [
        self::PROVISORIO => 'badge-warning',
        self::ACTIVO => 'badge-success',
        self::BAJA => 'badge-danger',
    ];

    public static function normalizar(?string $valor): string
    {
        $v = strtoupper(trim((string) $valor));
        if (in_array($v, [self::PROVISORIO, self::ACTIVO, self::BAJA], true)) {
            return $v;
        }

        return self::PROVISORIO;
    }

    public static function label(?string $valor): string
    {
        return self::LABELS[self::normalizar($valor)] ?? self::normalizar($valor);
    }

    public static function badge(?string $valor): string
    {
        return self::BADGES[self::normalizar($valor)] ?? 'badge-secondary';
    }

    public static function esProvisorio(?string $valor): bool
    {
        return self::normalizar($valor) === self::PROVISORIO;
    }

    public static function esActivo(?string $valor): bool
    {
        return self::normalizar($valor) === self::ACTIVO;
    }

    public static function esBaja(?string $valor): bool
    {
        return self::normalizar($valor) === self::BAJA;
    }

    /**
     * Flag de estado de Anita para el estado del ERP.
     */
    public static function flagAnita(?string $valor): string
    {
        return match (self::normalizar($valor)) {
            self::PROVISORIO => 'A',
            self::BAJA => 'B',
            default => ' ',
        };
    }

    /**
     * Estado del ERP para un flag de estado de Anita.
     */
    public static function desdeFlagAnita(?string $flag): string
    {
        return match (strtoupper(trim((string) $flag))) {
            'A' => self::PROVISORIO,
            'B' => self::BAJA,
            default => self::ACTIVO,
        };
    }
}
