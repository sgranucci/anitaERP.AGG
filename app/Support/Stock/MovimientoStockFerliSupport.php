<?php

namespace App\Support\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Movimientos de stock: grilla calzado (combinación/módulo/medidas) vs grilla legacy a-stkmov.c.
 */
final class MovimientoStockFerliSupport
{
    public static function esCalzadosFerli(): bool
    {
        return EntornoEmpresaSupport::esFerli();
    }
}
