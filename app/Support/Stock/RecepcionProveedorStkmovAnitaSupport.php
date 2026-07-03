<?php

namespace App\Support\Stock;

/**
 * stkmov Anita por línea COM: desactivado por defecto (stock solo en ERP).
 */
final class RecepcionProveedorStkmovAnitaSupport
{
    public static function habilitado(): bool
    {
        return (bool) config('recepcion_proveedor.anita.stkmov_habilitado', false);
    }
}
