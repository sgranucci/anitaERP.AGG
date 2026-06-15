<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;

/**
 * Numeración de ventas acotada por empresa (PV comparte sucursal Anita entre empresas).
 */
final class VentaNumeracionEmpresaSupport
{
    public static function maxNumerocomprobanteErp(
        int $puntoventaId,
        int $tipotransaccionId,
        ?int $empresaId = null,
    ): int {
        if ($puntoventaId <= 0 || $tipotransaccionId <= 0) {
            return 0;
        }

        $query = Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('tipotransaccion_id', $tipotransaccionId);

        if ($empresaId !== null && $empresaId > 0) {
            $query->whereHas('puntoventas', static function ($q) use ($empresaId): void {
                $q->where('empresa_id', $empresaId);
            });
        }

        return (int) ($query->max('numerocomprobante') ?? 0);
    }

    public static function numeroDesdeCodigoVenta(?string $codigo): int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return 0;
        }

        $partes = explode('-', $codigo);
        $ultimo = trim((string) end($partes));
        if ($ultimo === '' || ! ctype_digit($ultimo)) {
            return 0;
        }

        return (int) $ultimo;
    }

    public static function formatearCodigoVenta(
        string $tipoAnita,
        string $letra,
        string $sucursal,
        int $numero,
    ): string {
        $digitosSucursal = (int) config('facturacion.DIGITOS_SUCURSAL', 5);
        $digitosComprobante = (int) config('facturacion.DIGITOS_COMPROBANTE', 8);

        return trim($tipoAnita).' '.trim($letra).'-'
            .str_pad(trim($sucursal), $digitosSucursal, '0', STR_PAD_LEFT).'-'
            .str_pad((string) $numero, $digitosComprobante, '0', STR_PAD_LEFT);
    }

    public static function etiquetaFactura(
        string $tipoAnita,
        string $letra,
        string $sucursal,
        int $numero,
    ): string {
        return trim($tipoAnita).' '.trim($letra).' '.trim($sucursal).'-'.$numero;
    }
}
