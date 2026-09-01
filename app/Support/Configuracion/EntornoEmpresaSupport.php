<?php

namespace App\Support\Configuracion;

/**
 * Código de instalación (`EMPRESA` en .env → config('app.empresa')).
 * No confundir con la tabla `empresa` (Biyemas/Kandiko/Rebisco dentro de AGG).
 *
 * Usar en migraciones de menú, roles, permisos y usuarios para no aplicar
 * cambios de un cliente en otro.
 */
final class EntornoEmpresaSupport
{
    public const AGG = 'AGG';

    public const INTERFORMING = 'INTERFORMING';

    public const EL_BIERZO = 'EL BIERZO';

    public const FRASLE = 'FRASLE';

    public const FERLI = 'CALZADOS FERLI';

    public const IGUASSU = 'IGUASSU TRAVEL';

    /** Laboratorio PostgreSQL aislado (`EMPRESA=LAB_PG`). No es un cliente productivo. */
    public const LAB_PG = 'LAB_PG';

    public static function codigo(): string
    {
        return strtoupper(trim((string) config('app.empresa')));
    }

    public static function es(string ...$codigos): bool
    {
        $actual = self::codigo();
        foreach ($codigos as $codigo) {
            if ($actual === strtoupper(trim($codigo))) {
                return true;
            }
        }

        return false;
    }

    public static function esAgg(): bool
    {
        return self::es(self::AGG);
    }

    public static function esInterforming(): bool
    {
        return self::es(self::INTERFORMING);
    }

    public static function esElBierzo(): bool
    {
        return self::es(self::EL_BIERZO);
    }

    public static function esLabPostgres(): bool
    {
        return self::es(self::LAB_PG);
    }

    public static function esFerli(): bool
    {
        return self::es(self::FERLI);
    }
}
