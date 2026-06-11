<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;

/**
 * Visibilidad del botón «Generar nota de crédito» en Facturas del día.
 */
final class GastronomiaNotaCreditoUiSupport
{
    /**
     * @param  array<int, true>  $jornadasAbiertasPorEmpresa
     */
    public static function puedeGenerarNotaCredito(
        VentaGastronomiaEmision $emision,
        ?int $ncVentaId,
        array $jornadasAbiertasPorEmpresa,
    ): bool {
        if (! can('generar-nota-credito-gastronomia-facturas-dia', false)) {
            return false;
        }

        if (! self::esFacturaElegibleParaNc($emision, $ncVentaId)) {
            return false;
        }

        $empresaId = self::empresaIdDesdeEmision($emision);

        return $empresaId > 0 && ! empty($jornadasAbiertasPorEmpresa[$empresaId]);
    }

    public static function esFacturaElegibleParaNc(VentaGastronomiaEmision $emision, ?int $ncVentaId = null): bool
    {
        if ($emision->venta_factura_origen_id !== null) {
            return false;
        }

        if ($ncVentaId !== null) {
            return false;
        }

        $venta = $emision->venta;
        if (! $venta instanceof Venta) {
            return false;
        }

        if ((float) ($venta->total ?? 0) < 0.01) {
            return false;
        }

        $tipoFactura = $venta->tipotransacciones;
        if ($tipoFactura !== null && $tipoFactura->signo !== 'S') {
            return false;
        }

        return true;
    }

    public static function empresaIdDesdeEmision(VentaGastronomiaEmision $emision): int
    {
        $emision->loadMissing(['configuracionPuntoventa', 'venta.puntoventas']);

        $desdePv = (int) ($emision->configuracionPuntoventa?->empresa_id ?? 0);
        if ($desdePv > 0) {
            return $desdePv;
        }

        return (int) ($emision->venta?->puntoventas?->empresa_id ?? 0);
    }
}
