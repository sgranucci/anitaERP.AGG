<?php

namespace App\Support\Compras;

/**
 * Quién puede regularizar (estado Regularizado) en el ABM proveedor.
 * Con editar/actualizar alcanza; no exige un permiso aparte de suspensión.
 */
final class ProveedorRegularizacionAbmSupport
{
    public static function usuarioPuedeRegularizar(): bool
    {
        return can('editar-proveedor', false)
            || can('actualizar-proveedor', false);
    }
}
