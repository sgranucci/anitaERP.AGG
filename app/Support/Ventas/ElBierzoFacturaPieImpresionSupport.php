<?php

namespace App\Support\Ventas;

/**
 * Pie PDF El Bierzo: el Total ítem ya viene neto de bonificación de línea.
 * El Subtotal es la suma de esos ítems; el Descuento Gral. es solo el % de pie.
 */
final class ElBierzoFacturaPieImpresionSupport
{
    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @param  list<array<string, mixed>>  $tblItem
     * @return list<array<string, mixed>>
     */
    public static function ajustarConceptosParaImpresion(
        array $conceptos,
        array $tblItem,
        float $descuentoPiePct
    ): array {
        $subtotal = self::subtotalDesdeItems($tblItem);
        $descuentoPiePct = abs($descuentoPiePct);
        $importePie = $descuentoPiePct > 0.00001
            ? round($subtotal * $descuentoPiePct / 100, 2)
            : 0.0;

        $salida = [];
        $descuentoEscrito = false;

        foreach ($conceptos as $fila) {
            $concepto = (string) ($fila['concepto'] ?? '');

            if ($concepto === 'Subtotal') {
                $fila['importe'] = $subtotal;
                $salida[] = $fila;
                continue;
            }

            if (str_starts_with($concepto, 'Descuento')) {
                if ($importePie < 0.01) {
                    continue;
                }
                if ($descuentoEscrito) {
                    continue;
                }
                $fila['concepto'] = 'Descuento Gral. '.self::etiquetaPorcentaje($descuentoPiePct).'%';
                $fila['tasa'] = $descuentoPiePct;
                $fila['importe'] = -1 * $importePie;
                $salida[] = $fila;
                $descuentoEscrito = true;
                continue;
            }

            $salida[] = $fila;
        }

        if ($importePie >= 0.01 && ! $descuentoEscrito) {
            $insertados = [];
            $puesto = false;
            foreach ($salida as $fila) {
                $insertados[] = $fila;
                if (! $puesto && ($fila['concepto'] ?? '') === 'Subtotal') {
                    $insertados[] = [
                        'concepto' => 'Descuento Gral. '.self::etiquetaPorcentaje($descuentoPiePct).'%',
                        'tasa' => $descuentoPiePct,
                        'importe' => -1 * $importePie,
                        'baseimponible' => 0,
                    ];
                    $puesto = true;
                }
            }
            if (! $puesto) {
                array_unshift($insertados, [
                    'concepto' => 'Descuento Gral. '.self::etiquetaPorcentaje($descuentoPiePct).'%',
                    'tasa' => $descuentoPiePct,
                    'importe' => -1 * $importePie,
                    'baseimponible' => 0,
                ]);
            }
            $salida = $insertados;
        }

        return array_values($salida);
    }

    /**
     * @param  list<array<string, mixed>>  $tblItem
     */
    public static function subtotalDesdeItems(array $tblItem): float
    {
        $subtotal = 0.0;
        foreach ($tblItem as $item) {
            $subtotal += round((float) ($item['preciosindescuento'] ?? 0), 2)
                * round((float) ($item['cantidad'] ?? 0), 2);
        }

        return round($subtotal, 2);
    }

    private static function etiquetaPorcentaje(float $pct): string
    {
        if (abs($pct - round($pct)) < 0.00001) {
            return (string) (int) round($pct);
        }

        return rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.');
    }
}
