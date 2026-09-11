<?php

namespace App\Support\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;

/**
 * La moneda de la factura manda. La OC solo elige cuenta MN/ME; no se usa para pisar
 * una precarga que ya trajo moneda/cotización del PDF, API o portal.
 */
final class PrecargaProveedorMonedaFacturaSupport
{
    /**
     * True si la precarga ya declara la moneda de la factura (no un stub de scan/legajo).
     */
    public static function conservarMonedaCotizacion(?Precarga_Comprobante_Proveedor $precarga): bool
    {
        if ($precarga === null) {
            return false;
        }

        $monedaId = (int) ($precarga->moneda_id ?? 0);
        if ($monedaId <= 0) {
            return false;
        }

        if (abs((float) ($precarga->total ?? 0)) > 0.005
            || abs((float) ($precarga->subtotal ?? 0)) > 0.005) {
            return true;
        }

        return PrecargaComprobanteOrigenEntrada::conservarOrigenAlAdjuntarPdf(
            (string) ($precarga->origen_entrada ?? '')
        );
    }

    /**
     * Quita moneda/cotización del payload de update cuando la precarga ya las tiene de la factura.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function payloadSinPisarMoneda(array $payload, Precarga_Comprobante_Proveedor $existente): array
    {
        if (! self::conservarMonedaCotizacion($existente)) {
            return $payload;
        }

        unset($payload['moneda_id'], $payload['moneda'], $payload['cotizacion']);

        return $payload;
    }

    /**
     * No cambiar API/PDF/portal a LEGAJO/SCAN: ese origen identifica de dónde salieron los importes.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function payloadSinPisarOrigenFactura(array $payload, Precarga_Comprobante_Proveedor $existente): array
    {
        if (! PrecargaComprobanteOrigenEntrada::conservarOrigenAlAdjuntarPdf(
            (string) ($existente->origen_entrada ?? '')
        )) {
            return $payload;
        }

        unset($payload['origen_entrada']);

        return $payload;
    }
}
