<?php

namespace App\Support\Compras;

/** Subtipo de comprobante IVA compra cargado desde ingresos y egresos. */
final class ComprobanteProveedorTipoTesoreria
{
    /** Rendición / fondo fijo (comprobantes de caja chica). */
    public const FONDO_FIJO = 'FONDO_FIJO';

    /** Gastos bancarios (comisiones, mantenimiento cuenta, etc.). */
    public const GASTO_BANCO = 'GASTO_BANCO';

    /** Comprobantes que no son fondo fijo ni gasto bancario. */
    public const OTROS_VARIOS = 'OTROS_VARIOS';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::FONDO_FIJO,
            self::GASTO_BANCO,
            self::OTROS_VARIOS,
        ];
    }

    public static function etiqueta(?string $tipo): string
    {
        return match ($tipo) {
            self::FONDO_FIJO => 'Fondo fijo / caja chica',
            self::GASTO_BANCO => 'Gasto bancario',
            self::OTROS_VARIOS => 'Otros / Varios',
            default => $tipo ?? '',
        };
    }
}
