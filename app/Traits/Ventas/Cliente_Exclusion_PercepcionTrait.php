<?php

namespace App\Traits\Ventas;

trait Cliente_Exclusion_PercepcionTrait
{
    public static $enumTipo = [
        'IVA' => 'Percepción IVA',
        'IIBB' => 'Percepción IIBB',
    ];
}
