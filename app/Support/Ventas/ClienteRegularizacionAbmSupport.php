<?php

namespace App\Support\Ventas;

/**
 * Quién puede regularizar (estado R) en el ABM cliente.
 * No exige suspender-clientes: con editar/actualizar alcanza (p. ej. usuario oscar).
 */
final class ClienteRegularizacionAbmSupport
{
    public static function usuarioPuedeRegularizar(): bool
    {
        return can('suspender-clientes', false)
            || can('editar-clientes', false)
            || can('actualizar-clientes', false);
    }
}
