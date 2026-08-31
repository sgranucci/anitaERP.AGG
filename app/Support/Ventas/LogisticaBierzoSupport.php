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
 *
 * Impresión: línea "Total Logistica" antes del gravado (sin mostrar tasa);
 * el IVA queda consolidado con el gravado de la misma alícuota.
 */
final class LogisticaBierzoSupport
{
    public const CONCEPTO = 'Total Logistica';

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

            if (self::esConceptoLogistica($fila['concepto'] ?? '')) {
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

    public static function esConceptoLogistica(mixed $concepto): bool
    {
        return strcasecmp(trim((string) $concepto), self::CONCEPTO) === 0;
    }

    /**
     * Tras calcular IVA/IIBB: tasa 0 (no muestra 21 en pie) y orden antes del gravado.
     *
     * @param  list<array<string, mixed>>  $netos
     * @return list<array<string, mixed>>
     */
    public static function prepararNetosParaImpresion(array $netos): array
    {
        $logistica = null;
        $resto = [];

        foreach ($netos as $fila) {
            if (self::esConceptoLogistica($fila['concepto'] ?? '')) {
                $fila['tasa'] = 0;
                $logistica = $fila;
                continue;
            }
            $resto[] = $fila;
        }

        if ($logistica === null) {
            return $netos;
        }

        $antes = [];
        $desdeGravado = [];
        $vistoGravado = false;
        foreach ($resto as $fila) {
            $concepto = trim((string) ($fila['concepto'] ?? ''));
            if (! $vistoGravado && preg_match('/^Gravado/i', $concepto)) {
                $vistoGravado = true;
            }
            if ($vistoGravado) {
                $desdeGravado[] = $fila;
            } else {
                $antes[] = $fila;
            }
        }

        return array_merge($antes, [$logistica], $desdeGravado);
    }
}
