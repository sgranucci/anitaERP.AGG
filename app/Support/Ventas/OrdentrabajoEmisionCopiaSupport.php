<?php

namespace App\Support\Ventas;

/**
 * Títulos de copia de emisión OT (equivalente Anita tabla copiaot).
 * Fuente canónica Ferli: copi_codigo 11 (COMPLETA) / 14 (STOCK).
 */
final class OrdentrabajoEmisionCopiaSupport
{
    /** @var list<string> */
    public const TITULOS_COMPLETA = [
        'FACTURACION',
        'TAREA APARADA',
        'GRABADO',
        'PLANTILLA DE VISTA',
        'APARADOR Y FORRO',
        'PL.ARMADO',
        'PUNTERA Y CONTRAFUERTE',
        'FONDO',
        'CAJAS Y ETIQUETAS',
        'FORRADO DE BASE',
    ];

    /** @var list<string> */
    public const TITULOS_STOCK = [
        'STOCK',
    ];

    /**
     * @return list<string>
     */
    public static function titulos(string $tipoemision): array
    {
        $tipo = strtoupper(trim($tipoemision));

        return match ($tipo) {
            'STOCK', 'CAJA' => self::TITULOS_STOCK,
            default => self::TITULOS_COMPLETA,
        };
    }

    public static function cantidadCopias(string $tipoemision): int
    {
        $tipo = strtoupper(trim($tipoemision));

        return match ($tipo) {
            'STOCK', 'CAJA' => 1,
            // Emisión histórica COMPLETA: 8 copias (primeros 8 títulos).
            default => 8,
        };
    }
}
