<?php

namespace App\Traits\Compras;

trait PropuestaPagoEstadoTrait
{
    public static $enumEstado = [
        ['id' => '1', 'valor' => 'BORRADOR', 'nombre' => 'Borrador'],
        ['id' => '2', 'valor' => 'EN_APROBACION', 'nombre' => 'En aprobación'],
        ['id' => '3', 'valor' => 'AUTORIZADA', 'nombre' => 'Autorizada'],
        ['id' => '4', 'valor' => 'EJECUTADA', 'nombre' => 'Ejecutada'],
        ['id' => '5', 'valor' => 'EJECUTADA_PARCIAL', 'nombre' => 'Ejecutada parcial'],
        ['id' => '6', 'valor' => 'RECHAZADA', 'nombre' => 'Rechazada'],
        ['id' => '7', 'valor' => 'ANULADA', 'nombre' => 'Anulada'],
    ];

    public static function estadosEditables(): array
    {
        return ['BORRADOR', 'RECHAZADA'];
    }
}
