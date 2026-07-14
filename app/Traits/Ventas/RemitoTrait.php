<?php

namespace App\Traits\Ventas;

trait RemitoTrait
{
    public static $enumEstado = [
        'P' => 'Pendiente',
        'E' => 'Entregado',
        'F' => 'Facturado',
        'A' => 'Anulado',
        'C' => 'Cerrado',
    ];
}
