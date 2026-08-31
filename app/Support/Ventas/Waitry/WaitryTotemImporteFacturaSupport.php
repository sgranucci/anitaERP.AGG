<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Ventas\CuentaGastronomia;

/**
 * La factura fiscal de una comanda ya cobrada en tótem debe cuadrar con lo que Waitry cobró.
 */
final class WaitryTotemImporteFacturaSupport
{
    public const TOLERANCIA_PESOS = 0.02;

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function montoCobradoEnOrden(array $orden): float
    {
        $cobro = WaitryOrdenCobroSupport::montoCobro($orden);
        if ($cobro > 0.0001) {
            return round($cobro, 2);
        }

        return WaitryOrdenEstadoSupport::montoBrutoWaitry($orden);
    }

    public static function totalLineasCuenta(CuentaGastronomia $cuenta): float
    {
        $cuenta->loadMissing('lineas');
        $suma = 0.0;
        foreach ($cuenta->lineas as $linea) {
            $cantidad = (float) ($linea->cantidad ?? 0);
            $precio = (float) ($linea->precio_unitario ?? 0);
            $desc = (float) ($linea->descuento_linea_pct ?? 0);
            $suma += $cantidad * $precio * (1.0 - ($desc / 100.0));
        }

        return round($suma, 2);
    }

    public static function hayDesfasaje(float $totalFactura, float $montoWaitry): bool
    {
        if ($montoWaitry <= 0.0001) {
            return false;
        }

        return abs(round($totalFactura, 2) - round($montoWaitry, 2)) > self::TOLERANCIA_PESOS;
    }

    public static function mensajeDesfasaje(float $totalFactura, float $montoWaitry, ?string $displayId = null): string
    {
        $papelito = $displayId !== null && trim($displayId) !== ''
            ? ' ('.$displayId.')'
            : '';

        return 'Esta comanda ya se cobró en el tótem Waitry'.$papelito.' por $'
            .number_format($montoWaitry, 2, ',', '.')
            .'. La cuenta a facturar suma $'
            .number_format($totalFactura, 2, ',', '.')
            .'. Complete o corrija los ítems (y precios) hasta que coincidan; no se puede facturar un importe distinto.';
    }

    public static function errorSiDesfasado(CuentaGastronomia $cuenta, float $totalFactura): ?string
    {
        if (! $cuenta->waitry_cobro_totem) {
            return null;
        }

        $montoWaitry = round((float) ($cuenta->waitry_monto_cobro ?? 0), 2);
        if ($montoWaitry <= 0.0001) {
            return 'Esta comanda figura cobrada en el tótem Waitry pero no se pudo verificar el importe cobrado. '
                .'No se emite la factura hasta confirmar el cobro del tótem.';
        }

        if (! self::hayDesfasaje($totalFactura, $montoWaitry)) {
            return null;
        }

        return self::mensajeDesfasaje(
            $totalFactura,
            $montoWaitry,
            $cuenta->waitry_display_id ? (string) $cuenta->waitry_display_id : null,
        );
    }
}
