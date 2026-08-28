<?php

namespace App\Support\Ventas;

/**
 * Logística El Bierzo: mismo criterio que a-comprob.c calcula() (~4220).
 *
 * tot_logistica = (tot_grav + _tot_grav_otasa) * clim_logistica / 100
 *
 * Base = gravado 21 % + gravado otra tasa (10,5 %). No incluye exento.
 * El % es el del cliente (clim_logistica / porcentajelogistica), p. ej. 1.5.
 * El IVA de la logística va a la alícuota del impuesto (21 %), no a este %.
 */
final class LogisticaBierzoSupport
{
    /**
     * @param  list<array{concepto?: string, tasa?: float|int|string, importe?: float|int|string}>  $netos
     */
    public static function gravadoDesdeNetos(array $netos): float
    {
        $gravado = 0.0;

        foreach ($netos as $fila) {
            $tasa = (float) ($fila['tasa'] ?? 0);
            if ($tasa <= 0) {
                continue;
            }

            $concepto = trim((string) ($fila['concepto'] ?? ''));
            if (strcasecmp($concepto, 'Total Logistica') === 0) {
                continue;
            }

            $gravado += (float) ($fila['importe'] ?? 0);
        }

        return VentaImporteDosDecimalesSupport::redondear($gravado);
    }

    public static function importe(float $gravado, float $porcentaje): float
    {
        if ($gravado <= 0 || $porcentaje <= 0) {
            return 0.0;
        }

        return VentaImporteDosDecimalesSupport::redondear($gravado * $porcentaje / 100.0);
    }
}
