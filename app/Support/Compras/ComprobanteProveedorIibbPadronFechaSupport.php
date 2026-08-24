<?php

namespace App\Support\Compras;

/**
 * El cotejo IIBB usa la fecha del comprobante. Si esa fecha es anterior al
 * padrón que todavía está descargado (los meses viejos se purgan), no hay
 * alícuota vigente que consultar: no se controla.
 */
final class ComprobanteProveedorIibbPadronFechaSupport
{
    public static function omitirPorFacturaAnterior(?string $fechaFactura, ?string $minDesdefechaPadron): bool
    {
        $fecha = substr((string) $fechaFactura, 0, 10);
        $min = substr((string) $minDesdefechaPadron, 0, 10);

        if ($fecha === '' || $min === '') {
            return false;
        }

        return $fecha < $min;
    }
}
