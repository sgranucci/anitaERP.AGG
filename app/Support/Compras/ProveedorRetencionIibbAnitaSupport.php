<?php

namespace App\Support\Compras;

/**
 * prom_ret_ibr (promae) ↔ condicionIIBB.
 *
 * Crown (a-promae.c, help_7): el carácter I se muestra como «No inscripto»
 * (en el .def genérico es exento). N es «No retener». E es exterior.
 * Los ids 1–4 son el catálogo que ya usan los importadores.
 */
final class ProveedorRetencionIibbAnitaSupport
{
    public const CONVENIO = 'S';

    public const LOCAL = 'L';

    /** Crown: «No inscripto». .def genérico: exento. */
    public const NO_INSCRIPTO = 'I';

    public const CONVENIO_SIN_BSAS = 'C';

    public const CABA = 'B';

    public const EXTERIOR = 'E';

    public const NO_RETIENE = 'N';

    public const CONDICION_CONVENIO = 1;

    public const CONDICION_LOCAL = 2;

    public const CONDICION_NO_INSCRIPTO = 3;

    /** formacalculo N: no retiene. */
    public const CONDICION_NO_RETIENE = 4;

    public static function condicionIibbIdDesdePromRetIbr(?string $codigo): int
    {
        $codigo = strtoupper(trim((string) $codigo));

        return match ($codigo) {
            self::CONVENIO, self::CONVENIO_SIN_BSAS => self::CONDICION_CONVENIO,
            self::LOCAL, self::CABA => self::CONDICION_LOCAL,
            self::NO_INSCRIPTO => self::CONDICION_NO_INSCRIPTO,
            self::EXTERIOR, self::NO_RETIENE, '' => self::CONDICION_NO_RETIENE,
            default => self::CONDICION_CONVENIO,
        };
    }

    /**
     * Carácter a grabar en Anita. C y B se leen como convenio y local;
     * al volver se graban S y L. Exterior vuelve como N (no retiene).
     */
    public static function promRetIbrDesdeCondicionIibbId(int|string|null $condicionId): string
    {
        return match ((int) $condicionId) {
            self::CONDICION_CONVENIO => self::CONVENIO,
            self::CONDICION_LOCAL => self::LOCAL,
            self::CONDICION_NO_INSCRIPTO => self::NO_INSCRIPTO,
            default => self::NO_RETIENE,
        };
    }
}
