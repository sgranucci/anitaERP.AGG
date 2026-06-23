<?php

namespace App\Traits\Sala;

trait RequisicionSalaEstadoTrait
{
    public static $enumEstado = [
        ['id' => '1', 'valor' => '0', 'nombre' => 'PENDIENTE'],
        ['id' => '2', 'valor' => '1', 'nombre' => 'GENERO ORDEN COMPRA'],
        ['id' => '3', 'valor' => '2', 'nombre' => 'PARCIAL'],
        ['id' => '4', 'valor' => '3', 'nombre' => 'CUMPLIDO'],
        ['id' => '5', 'valor' => '4', 'nombre' => 'SUSPENDIDO'],
        ['id' => '6', 'valor' => '5', 'nombre' => 'A COMPRAS'],
        ['id' => '7', 'valor' => '6', 'nombre' => 'A AUTORIZAR'],
        ['id' => '8', 'valor' => 'E', 'nombre' => 'AUTORIZACION ESPECIAL'],
        ['id' => '9', 'valor' => 'R', 'nombre' => 'EN ARBOL APROBACION'],
        ['id' => '10', 'valor' => 'A', 'nombre' => 'APROBADA'],
        ['id' => '11', 'valor' => 'Z', 'nombre' => 'RECHAZADA'],
    ];

    public static function estadosArbolConfigurables(): array
    {
        $valores = ['0', '5', '6', 'E', 'R', 'A', 'Z'];
        $salida = [];
        foreach (self::$enumEstado as $row) {
            if (in_array($row['valor'], $valores, true)) {
                $salida[] = $row;
            }
        }

        return $salida;
    }

    public static function esNombreEstadoValido(string $nombre): bool
    {
        foreach (self::$enumEstado as $row) {
            if ($row['nombre'] === $nombre) {
                return true;
            }
        }

        return false;
    }

    public static function nombrePorValor(string $valor): ?string
    {
        foreach (self::$enumEstado as $row) {
            if ($row['valor'] === $valor) {
                return $row['nombre'];
            }
        }

        return null;
    }

    public static function valorPorNombre(string $nombre): ?string
    {
        foreach (self::$enumEstado as $row) {
            if ($row['nombre'] === $nombre) {
                return $row['valor'];
            }
        }

        return null;
    }
}
