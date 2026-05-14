<?php

namespace App\Traits\Stock;

trait Formula_Articulo_EstadoTrait
{
    public static $enumEstado = [
        ['id' => '1', 'valor' => 'B', 'nombre' => 'BORRADOR'],
        ['id' => '2', 'valor' => 'A', 'nombre' => 'ACTIVA'],
        ['id' => '3', 'valor' => 'I', 'nombre' => 'INACTIVA'],
    ];
}
