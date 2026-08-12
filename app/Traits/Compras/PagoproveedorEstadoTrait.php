<?php

namespace App\Traits\Compras;

trait PagoproveedorEstadoTrait
{
    public static $enumEstado = [
        ['id' => '1', 'valor' => 'PRE CARGA', 'nombre' => 'Pre carga'],
        ['id' => '2', 'valor' => 'CONFIRMADA', 'nombre' => 'Confirmada'],
        ['id' => '3', 'valor' => 'REVERTIDA', 'nombre' => 'Revertida'],
        ['id' => '4', 'valor' => 'BAJA', 'nombre' => 'Baja'],
        ['id' => '5', 'valor' => 'PAGADA', 'nombre' => 'Pagada'],
        ['id' => '6', 'valor' => 'CONCILIADA', 'nombre' => 'Conciliada'],
    ];

    /** Estados en los que no se puede editar la OP. */
    public static function estadosFinalesBloqueados(): array
    {
        return ['REVERTIDA', 'BAJA', 'PAGADA', 'CONCILIADA'];
    }

    public static $enumModoCotizacion = [
        ['id' => '1', 'valor' => 'factura', 'nombre' => 'Cotización de la factura'],
        ['id' => '2', 'valor' => 'dia', 'nombre' => 'Cotización del día (manual)'],
    ];
}
