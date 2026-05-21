<?php

namespace App\Traits\Stock;

trait Tipotransaccion_StockTrait
{
    public static $enumOperacion = [
        'E' => 'Entradas de stock',
        'S' => 'Salidas de stock',
        'T' => 'Transferencia de stock',
    ];

    public static $enumSigno = [
        'S' => 'Suma',
        'R' => 'Resta',
    ];

    public static $enumEstado = [
        'A' => 'Activa',
        'S' => 'Suspendida',
    ];
}
