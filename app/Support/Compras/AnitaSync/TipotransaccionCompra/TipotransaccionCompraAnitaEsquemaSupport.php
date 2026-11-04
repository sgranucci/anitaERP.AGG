<?php

namespace App\Support\Compras\AnitaSync\TipotransaccionCompra;

use App\Support\Compras\ConceptoIvaAnitaEsquemaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Canal Anita del ABM tipo de comprobante de compra (`t_comp` + hijas).
 *
 * | Instalación | Tablas Anita al grabar |
 * |---|---|
 * | AGG / resto | `t_comp`, `ccostcomp`, `cont_comp` |
 * | Ferli | `t_comp`, `cont_comp` (sin `ccostcomp`) |
 * | Crown (`/usr2/casino`, verificado 4/nov/2026) | `t_comp`, `cont_comp` (sin `ccostcomp`) |
 *
 * Sin `ccostcomp` los centros de costo del tipo quedan solo en el ERP
 * (`tipotransaccion_compra_centrocosto`). Conceptos siguen en `cont_comp`.
 */
final class TipotransaccionCompraAnitaEsquemaSupport
{
    public static function escribeCcostcomp(): bool
    {
        return ConceptoIvaAnitaEsquemaSupport::leeCcostcomp();
    }

    public static function escribeContComp(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public static function tablasEscritura(): array
    {
        $tablas = ['t_comp'];
        if (self::escribeCcostcomp()) {
            $tablas[] = 'ccostcomp';
        }
        if (self::escribeContComp()) {
            $tablas[] = 'cont_comp';
        }

        return $tablas;
    }

    public static function esCanalReducidoSinCcostcomp(): bool
    {
        return EntornoEmpresaSupport::esFerli() || EntornoEmpresaSupport::esCrown();
    }
}
