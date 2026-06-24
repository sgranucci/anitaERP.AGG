<?php

declare(strict_types=1);

namespace App\Support\Ventas\IvaVentas;

/**
 * Modo de contabilización de una venta para conciliación IVA.
 *
 * - vinculado: asiento con venta_id (imputación por factura — administración, pedidos, etc.).
 * - cierre_agrupado: sin asiento individual (gastronomía / estacionamiento agrupados en cierre de jornada).
 */
final class IvaVentasConciliacionModoSupport
{
    public const VINCULADO = 'vinculado';

    public const CIERRE_AGRUPADO = 'cierre_agrupado';

    /**
     * @param  array<string, mixed>  $fila  Fila del reporte IVA ventas
     */
    public static function modoDesdeFila(array $fila, bool $tieneAsientoVinculado): string
    {
        if ($tieneAsientoVinculado) {
            return self::VINCULADO;
        }

        $seccion = (string) ($fila['seccion'] ?? '');

        return $seccion === 'operacion' ? self::CIERRE_AGRUPADO : self::CIERRE_AGRUPADO;
    }

    public static function etiquetaModo(string $modo): string
    {
        return match ($modo) {
            self::VINCULADO => 'Imputación por factura',
            default => 'Cierre agrupado (jornada)',
        };
    }

    public static function conciliaPorFactura(string $modo): bool
    {
        return $modo === self::VINCULADO;
    }
}
