<?php

namespace App\Support\Stock;

final class MovimientosArticuloDepositoSupport
{
    public static function puedeConsultar(): bool
    {
        return can('listar-articulos', false)
            || can('editar-articulos', false)
            || can('listar-recuento', false)
            || can('crear-recuento', false)
            || can('editar-recuento', false)
            || can('ver-recuento', false);
    }
}
