<?php

namespace App\Support\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;
use RuntimeException;

/**
 * Candado: si la factura nace de una precarga con total usable, no puede desviarse.
 * Sin precarga, o precarga sin total (Scan Anita manual / PDF sin OCR), no se aplica.
 */
final class ComprobanteProveedorPrecargaTotalSupport
{
    public const TOLERANCIA = ComprobanteProveedorCuotasTotalSupport::TOLERANCIA;

    public static function assertCuadraConPrecarga(?int $precargaId, float $totalFactura): void
    {
        $precargaId = (int) ($precargaId ?? 0);
        if ($precargaId <= 0) {
            return;
        }

        $precarga = Precarga_Comprobante_Proveedor::query()
            ->whereKey($precargaId)
            ->first(['id', 'total', 'subtotal', 'origen_entrada']);

        if (! $precarga) {
            throw new RuntimeException('La precarga #'.$precargaId.' vinculada a la factura no existe.');
        }

        // Scan Anita / legajo sin OCR: total queda en 0 — no hay referencia externa confiable.
        if (! self::precargaTieneTotalUsable($precarga)) {
            return;
        }

        $totalFacturaAbs = round(abs($totalFactura), 2);
        $totalPrecargaAbs = round(abs((float) $precarga->total), 2);
        $diferencia = round(abs($totalFacturaAbs - $totalPrecargaAbs), 2);

        if ($diferencia <= self::TOLERANCIA + 0.000001) {
            return;
        }

        throw new RuntimeException(
            'El total de la factura ('.number_format($totalFacturaAbs, 2, ',', '.')
            .') difiere del total de la precarga #'.$precargaId.' ('
            .number_format($totalPrecargaAbs, 2, ',', '.')
            .'). Diferencia: '.number_format($diferencia, 2, ',', '.')
            .'. Si el PDF/precarga está mal, corregí la precarga; no se puede grabar un total distinto.'
        );
    }

    public static function precargaTieneTotalUsable(?Precarga_Comprobante_Proveedor $precarga): bool
    {
        if (! $precarga) {
            return false;
        }

        return abs((float) ($precarga->total ?? 0)) > self::TOLERANCIA
            || abs((float) ($precarga->subtotal ?? 0)) > self::TOLERANCIA;
    }
}
