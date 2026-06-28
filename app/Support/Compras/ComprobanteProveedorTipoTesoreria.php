<?php

namespace App\Support\Compras;

/** Subtipo de comprobante IVA compra cargado desde ingresos y egresos. */
final class ComprobanteProveedorTipoTesoreria
{
    /** Rendición / fondo fijo (comprobantes de caja chica). */
    public const FONDO_FIJO = 'FONDO_FIJO';

    /** Gastos bancarios (comisiones, mantenimiento cuenta, etc.). */
    public const GASTO_BANCO = 'GASTO_BANCO';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::FONDO_FIJO,
            self::GASTO_BANCO,
        ];
    }

    public static function etiqueta(?string $tipo): string
    {
        return match ($tipo) {
            self::FONDO_FIJO => 'Fondo fijo / caja chica',
            self::GASTO_BANCO => 'Gasto bancario',
            default => $tipo ?? '',
        };
    }
}
