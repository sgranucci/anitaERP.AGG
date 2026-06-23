<?php

namespace App\Traits\Sala;

trait RequisicionSalaArticuloEstadoParcialTrait
{
    /** Motivos de entrega parcial (reqsmov.estado_parcial en Anita C). */
    public static $enumEstadoParcial = [
        ['id' => '1', 'valor' => '1', 'nombre' => 'NO AUTORIZA LABORATORIO'],
        ['id' => '2', 'valor' => '2', 'nombre' => 'FALTA DE STOCK'],
        ['id' => '3', 'valor' => '3', 'nombre' => 'STOCK INSUFICIENTE'],
        ['id' => '4', 'valor' => '4', 'nombre' => 'ARTICULO DISCONTINUO'],
        ['id' => '5', 'valor' => '5', 'nombre' => 'EN REPARACION'],
        ['id' => '6', 'valor' => '6', 'nombre' => 'CIERRA ITEM'],
    ];

    public static function estadoParcialNombrePorValor(?string $valor): string
    {
        if ($valor === null || trim($valor) === '') {
            return '';
        }
        foreach (self::$enumEstadoParcial as $row) {
            if ($row['valor'] === $valor) {
                return $row['nombre'];
            }
        }

        return '';
    }

    public static function esCierraItem(?string $valor): bool
    {
        return $valor === '6';
    }
}
