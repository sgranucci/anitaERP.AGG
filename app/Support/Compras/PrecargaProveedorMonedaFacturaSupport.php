<?php

namespace App\Support\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;

/**
 * La moneda de la factura se elige al asignar el PDF (viene predefinida con la de la orden).
 * Cuentas a pagar puede cambiarla al cargar el comprobante. No se inventa otra si la precarga no trae ninguna.
 */
final class PrecargaProveedorMonedaFacturaSupport
{
    public static function monedaLocalId(): int
    {
        $id = (int) config('cotizacion.ID_MONEDA_DEFAULT', 1);

        return $id > 0 ? $id : 1;
    }

    /**
     * Moneda con la que se abre el comprobante: la que quedó en la precarga al asignar el PDF.
     */
    public static function monedaIdParaComprobante(?Precarga_Comprobante_Proveedor $precarga): int
    {
        $id = (int) ($precarga->moneda_id ?? 0);
        if ($id > 0) {
            return $id;
        }

        return self::monedaLocalId();
    }

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
