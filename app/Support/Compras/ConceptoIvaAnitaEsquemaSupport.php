<?php

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Esquema Anita `conccomp` / `concciva` por instalación.
 *
 * Calzados Ferli (verificado 11/sep/2026 contra syscolumns /usr2/ferli):
 * - conccomp no tiene concc_tipo_conc, concc_alicuota_iva, concc_retiene_ibr.
 * - no existe la tabla concciva.
 */
final class ConceptoIvaAnitaEsquemaSupport
{
    /**
     * @return list<string>
     */
    public static function camposCabecera(): array
    {
        $campos = [
            'concc_concepto',
            'concc_desc',
            'concc_formula',
            'concc_columna_sub',
            'concc_contenido',
            'concc_cta_debe',
            'concc_cta_haber',
            'concc_ctapte_debe',
            'concc_ctapte_haber',
        ];

        if (self::incluyeTipoAlicuotaRetiene()) {
            $campos[] = 'concc_tipo_conc';
            $campos[] = 'concc_alicuota_iva';
            $campos[] = 'concc_retiene_ibr';
        }

        return $campos;
    }

    public static function sqlCamposCabecera(): string
    {
        return implode(",\n                ", self::camposCabecera());
    }

    public static function incluyeTipoAlicuotaRetiene(): bool
    {
        return ! EntornoEmpresaSupport::esFerli();
    }

    public static function leeConcciva(): bool
    {
        return ! EntornoEmpresaSupport::esFerli();
    }
}
