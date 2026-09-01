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
 * el Gravado mostrado = mercadería + logística (base del IVA); el IVA queda
 * consolidado en una sola línea por alícuota.
 *
 * En venta_impuesto el Gravado sigue siendo solo mercadería y la logística
 * va aparte (así ven_gravado Anita = Gravado + Logistica sin doblar).
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
     * No altera el importe del Gravado (sigue = mercadería) para grabar venta_impuesto.
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

    /**
     * Pie PDF/reimpresión: orden/tasa de logística, IVA fusionado y Gravado =
     * mercadería + logística (base imponible real del IVA).
     *
     * @param  list<array<string, mixed>>  $conceptos
     * @return list<array<string, mixed>>
     */
    public static function prepararConceptosTotalesParaImpresion(array $conceptos): array
    {
        return self::sumarLogisticaAlGravadoParaImpresion(
            self::consolidarIvaPorTasa(self::prepararNetosParaImpresion($conceptos))
        );
    }

    /**
     * Suma el importe de "Total Logistica" al Gravado de la misma alícuota (21 %)
     * para que el pie no repita el Subtotal como Gravado.
     *
     * @param  list<array<string, mixed>>  $conceptos
     * @return list<array<string, mixed>>
     */
    public static function sumarLogisticaAlGravadoParaImpresion(array $conceptos): array
    {
        $importeLogistica = 0.0;
        foreach ($conceptos as $fila) {
            if (self::esConceptoLogistica($fila['concepto'] ?? '')) {
                $importeLogistica = (float) ($fila['importe'] ?? 0);
                break;
            }
        }

        if (abs($importeLogistica) < 0.00001) {
            return $conceptos;
        }

        $idxGravado21 = null;
        $idxPrimerGravado = null;
        foreach ($conceptos as $i => $fila) {
            $concepto = trim((string) ($fila['concepto'] ?? ''));
            if (! preg_match('/^Gravado/i', $concepto)) {
                continue;
            }
            if ($idxPrimerGravado === null) {
                $idxPrimerGravado = $i;
            }
            if ((float) ($fila['tasa'] ?? 0) == 21.0) {
                $idxGravado21 = $i;
                break;
            }
        }

        $idx = $idxGravado21 ?? $idxPrimerGravado;
        if ($idx === null) {
            return $conceptos;
        }

        $conceptos[$idx]['importe'] = VentaImporteDosDecimalesSupport::redondear(
            (float) ($conceptos[$idx]['importe'] ?? 0) + $importeLogistica
        );

        return $conceptos;
    }

    /**
     * Une varias líneas "Iva X%" / "Iva X.000%" de la misma alícuota en una sola.
     *
     * @param  list<array<string, mixed>>  $conceptos
     * @return list<array<string, mixed>>
     */
    public static function consolidarIvaPorTasa(array $conceptos): array
    {
        $salida = [];
        $indicePorTasa = [];

        foreach ($conceptos as $fila) {
            $concepto = trim((string) ($fila['concepto'] ?? ''));
            if (! preg_match('/^Iva\s+/i', $concepto)) {
                $salida[] = $fila;
                continue;
            }

            $tasaKey = (string) (float) ($fila['tasa'] ?? 0);
            if (! isset($indicePorTasa[$tasaKey])) {
                $indicePorTasa[$tasaKey] = count($salida);
                $salida[] = $fila;
                continue;
            }

            $idx = $indicePorTasa[$tasaKey];
            $salida[$idx]['importe'] = VentaImporteDosDecimalesSupport::redondear(
                (float) ($salida[$idx]['importe'] ?? 0) + (float) ($fila['importe'] ?? 0)
            );
            $salida[$idx]['baseimponible'] = VentaImporteDosDecimalesSupport::redondear(
                (float) ($salida[$idx]['baseimponible'] ?? 0) + (float) ($fila['baseimponible'] ?? 0)
            );
        }

        return $salida;
    }
}
