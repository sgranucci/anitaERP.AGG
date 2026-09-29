<?php

namespace App\Support\Compras\Retencion;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Diferencias de esquema Anita (módulo compras / retenciones) por instalación.
 *
 * Calzados Ferli:
 * - `retencion` y `retiva` no tienen base / cant_per / valor_unit.
 * - `retmov`, `retimov`, `retsmov` y `retibrmov` no tienen columna `*_empresa`.
 * - `provibr` usa `proib_porc_noins` (sin la "c" final de otras instalaciones).
 */
final class AnitaRetencionEsquemaSupport
{
    public static function retencionIncluyeBasePeriodoValor(): bool
    {
        return ! EntornoEmpresaSupport::esFerli();
    }

    public static function retivaIncluyeBasePeriodoValor(): bool
    {
        return ! EntornoEmpresaSupport::esFerli();
    }

    public static function iibbCampoPorcNoInscripto(): string
    {
        return EntornoEmpresaSupport::esFerli() ? 'proib_porc_noins' : 'proib_porc_noinsc';
    }

    /**
     * Ferli no tiene `retv_empresa` / `retiv_empresa` (ni el resto de `*_empresa` en retmov, retimov, retsmov, retibrmov).
     */
    public static function movimientosTienenColumnaEmpresa(): bool
    {
        return ! EntornoEmpresaSupport::esFerli();
    }
}
