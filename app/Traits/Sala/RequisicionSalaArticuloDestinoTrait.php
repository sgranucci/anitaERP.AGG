<?php

namespace App\Traits\Sala;

trait RequisicionSalaArticuloDestinoTrait
{
    public static $enumDestino = [
        ['id' => '1', 'valor' => 'R', 'nombre' => 'REPARACION'],
        ['id' => '2', 'valor' => 'S', 'nombre' => 'STOCK'],
        ['id' => '3', 'valor' => 'D', 'nombre' => 'DEVOLUCION'],
    ];

    public static function destinoNombrePorValor(string $valor): string
    {
        foreach (self::$enumDestino as $row) {
            if ($row['valor'] === $valor) {
                return $row['nombre'];
            }
        }

        return '';
    }
}
