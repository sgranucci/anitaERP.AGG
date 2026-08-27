<?php

namespace App\Support\Compras\AnitaSync\Requisicion;

/**
 * Mapeo estado ERP (nombre) → reqm_estado char Anita (reqmae.def).
 */
final class RequisicionAnitaEstadoMapper
{
    public static function erpNombreToAnitaChar(?string $estadoErp): string
    {
        $nombre = trim((string) $estadoErp);

        return match ($nombre) {
            'PENDIENTE' => '0',
            'GENERO ORDEN COMPRA' => '1',
            'PARCIAL' => '2',
            'CUMPLIDA' => '3',
            'SUSPENDIDA' => '4',
            'EN COMPRAS' => '5',
            'A AUTORIZAR' => '6',
            'TRANSFERIDA' => 'T',
            'AUT ESPECIAL' => 'E',
            'EN ARBOL APROBACION' => 'A',
            'APROBADA' => '6',
            default => '0',
        };
    }

    public static function anitaCharToErpNombre(?string $estadoAnita): string
    {
        return match (trim((string) $estadoAnita)) {
            '1' => 'GENERO ORDEN COMPRA',
            '2' => 'PARCIAL',
            '3' => 'CUMPLIDA',
            '4' => 'SUSPENDIDA',
            '5' => 'EN COMPRAS',
            '6' => 'A AUTORIZAR',
            'T' => 'TRANSFERIDA',
            'E' => 'AUT ESPECIAL',
            'A' => 'EN ARBOL APROBACION',
            default => 'PENDIENTE',
        };
    }
}
