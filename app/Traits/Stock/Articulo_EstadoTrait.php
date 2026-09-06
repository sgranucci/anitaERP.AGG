<?php

namespace App\Traits\Stock;

trait Articulo_EstadoTrait
{
    public static $enumEstado = [
        ['id' => '1', 'valor' => 'A', 'nombre' => 'ACTIVO'],
        ['id' => '2', 'valor' => 'I', 'nombre' => 'INACTIVO'],
        ['id' => '3', 'valor' => 'B', 'nombre' => 'BAJA'],
        ['id' => '4', 'valor' => 'P', 'nombre' => 'PENDIENTE'],
        ['id' => '5', 'valor' => 'R', 'nombre' => 'RECHAZADO'],
    ];

    public static $enumNoFactura = [
        ['id' => '0', 'nombre' => 'Facturable'],
        ['id' => '1', 'nombre' => 'No facturable'],
    ];
}
