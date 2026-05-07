<?php

namespace App\Traits\Compras;

trait Listaprecio_Proveedor_EstadoTrait
{
    public static $enumEstado = [
        ['id' => '1', 'valor' => 'A', 'nombre' => 'ACTIVA'],
        ['id' => '2', 'valor' => 'I', 'nombre' => 'INACTIVA'],
    ];

    public static function esNombreEstadoValido(string $nombre): bool
    {
        foreach (self::$enumEstado as $row) {
            if ($row['nombre'] === $nombre) {
                return true;
            }
        }

        return false;
    }

    public static function otroEstado(string $nombreActual): ?string
    {
        foreach (self::$enumEstado as $row) {
            if ($row['nombre'] !== $nombreActual) {
                return $row['nombre'];
            }
        }

        return null;
    }
}
