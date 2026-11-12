<?php

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * prom_ret_ibr (promae) ↔ condicionIIBB.
 *
 * Crown: catálogo operativo Convenio / Local / No alcanzado. La letra Anita I
 * («No inscripto» en help) se mapea a Convenio; la tasa 5% sin inscripción RN
 * (domicilio ni CM05) sale de retencionIIBB.porcentajeretencion_fuera.
 *
 * Resto de clientes: I → id 3 No inscripto (legado).
 */
final class ProveedorRetencionIibbAnitaSupport
{
    public const CONVENIO = 'S';

    public const LOCAL = 'L';

    /** Crown help: «No inscripto». .def genérico: exento. */
    public const NO_INSCRIPTO = 'I';

    public const CONVENIO_SIN_BSAS = 'C';

    public const CABA = 'B';

    public const EXTERIOR = 'E';

    public const NO_RETIENE = 'N';

    public const CONDICION_CONVENIO = 1;

    public const CONDICION_LOCAL = 2;

    /** Legado / no Crown. En Crown queda inactiva tras remapeo. */
    public const CONDICION_NO_INSCRIPTO = 3;

    /** formacalculo N: no retiene. */
    public const CONDICION_NO_RETIENE = 4;

    public static function condicionIibbIdDesdePromRetIbr(?string $codigo): int
    {
        $codigo = strtoupper(trim((string) $codigo));

        return match ($codigo) {
            self::CONVENIO, self::CONVENIO_SIN_BSAS => self::CONDICION_CONVENIO,
            self::LOCAL, self::CABA => self::CONDICION_LOCAL,
            self::NO_INSCRIPTO => EntornoEmpresaSupport::esCrown()
                ? self::CONDICION_CONVENIO
                : self::CONDICION_NO_INSCRIPTO,
            self::EXTERIOR, self::NO_RETIENE, '' => self::CONDICION_NO_RETIENE,
            default => self::CONDICION_CONVENIO,
        };
    }

    /**
     * Carácter a grabar en Anita. C y B se leen como convenio y local;
     * al volver se graban S y L. Exterior vuelve como N (no retiene).
     * Crown ya no escribe I (No inscripto).
     */
    public static function promRetIbrDesdeCondicionIibbId(int|string|null $condicionId): string
    {
        $id = (int) $condicionId;
        if (EntornoEmpresaSupport::esCrown() && $id === self::CONDICION_NO_INSCRIPTO) {
            return self::CONVENIO;
        }

        return match ($id) {
            self::CONDICION_CONVENIO => self::CONVENIO,
            self::CONDICION_LOCAL => self::LOCAL,
            self::CONDICION_NO_INSCRIPTO => self::NO_INSCRIPTO,
            default => self::NO_RETIENE,
        };
    }
}
