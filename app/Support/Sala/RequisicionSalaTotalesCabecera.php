<?php

namespace App\Support\Sala;

use App\Models\Sala\RequisicionSala;

class RequisicionSalaTotalesCabecera
{
    public static function desdeModelo(?RequisicionSala $req): array
    {
        if (! $req) {
            return ['monto' => 0.0, 'moneda_id' => 1];
        }
        $req->loadMissing('requisicion_sala_articulos');
        $monto = 0.0;
        foreach ($req->requisicion_sala_articulos as $linea) {
            $monto += (float) $linea->cantidad * (float) $linea->precio;
        }

        return ['monto' => round($monto, 4), 'moneda_id' => 1];
    }
}
