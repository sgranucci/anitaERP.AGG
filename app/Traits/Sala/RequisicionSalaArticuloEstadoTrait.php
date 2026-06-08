<?php

namespace App\Traits\Sala;

trait RequisicionSalaArticuloEstadoTrait
{
    public static $enumEstado = [
        ['id' => '1', 'valor' => ' ', 'nombre' => 'PENDIENTE'],
        ['id' => '2', 'valor' => 'E', 'nombre' => 'ENTREGADO'],
        ['id' => '3', 'valor' => 'R', 'nombre' => 'PARA RETIRAR'],
        ['id' => '4', 'valor' => 'P', 'nombre' => 'PENDIENTE REP'],
        ['id' => '5', 'valor' => 'A', 'nombre' => 'ENTREGADO PARCIAL'],
        ['id' => '6', 'valor' => 'C', 'nombre' => 'CERRADO'],
    ];

    public static function estadoLineaNombrePorValor(string $valor): string
    {
        foreach (self::$enumEstado as $row) {
            if ($row['valor'] === $valor) {
                return $row['nombre'];
            }
        }

        return 'PENDIENTE';
    }
}
