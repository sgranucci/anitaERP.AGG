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
 * | Ferli | `t_comp`, `cont_comp` (sin centros de costo por tipo) |
 * | Crown (`/usr2/casino`) | `t_comp`, `cont_comp`. Los centros de costo del tipo
 *   viven en `ccosvtcomp` (`a-tcomp.c`, campo «Valida C.Costos»), no en `ccostcomp`.
 *
 * La escritura hacia Anita sigue usando `ccostcomp` solo donde esa tabla existe.
 * En Crown el import lee `ccosvtcomp` hacia `tipotransaccion_compra_centrocosto`.
 * Conceptos siguen en `cont_comp`.
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

    /**
     * Centros de costo habilitados por tipo de comprobante.
     *
     * AGG: `compras.ccostcomp` (`ccostc_tipo`, `ccostc_ccosto`).
     * Crown: `compras.ccosvtcomp` (`ccosvt_tipo`, `ccosvt_ccosto`), la tabla
     * que graba `a-tcomp.c`. Ferli no tiene ninguna de las dos.
     *
     * @return array{tabla: string, campo_tipo: string, campo_ccosto: string}|null
     */
    public static function origenCentrosCostoTipo(): ?array
    {
        if (ConceptoIvaAnitaEsquemaSupport::leeCcostcomp()) {
            return [
                'tabla' => 'ccostcomp',
                'campo_tipo' => 'ccostc_tipo',
                'campo_ccosto' => 'ccostc_ccosto',
            ];
        }

        if (EntornoEmpresaSupport::esCrown()) {
            return [
                'tabla' => 'ccosvtcomp',
                'campo_tipo' => 'ccosvt_tipo',
                'campo_ccosto' => 'ccosvt_ccosto',
            ];
        }

        return null;
    }
}
