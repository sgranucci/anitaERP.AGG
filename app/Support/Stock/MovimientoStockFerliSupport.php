<?php

namespace App\Support\Stock;

/**
 * Movimientos de stock: grilla calzado (combinación/módulo/medidas) vs grilla legacy a-stkmov.c.
 */
final class MovimientoStockFerliSupport
{
    public static function esCalzadosFerli(): bool
    {
        return config('app.empresa') === 'Calzados Ferli';
    }
}
