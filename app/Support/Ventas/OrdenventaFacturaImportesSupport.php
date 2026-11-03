<?php

namespace App\Support\Ventas;

/**
 * La orden de venta muestra bruto + percepciones + IVA.
 * La factura usa el bruto como neto y reemplaza el IVA y las percepciones
 * calculados por los importes cargados en la orden (prorrateados por la cuota).
 */
class OrdenventaFacturaImportesSupport
{
    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @return list<array<string, mixed>>
     */
    public static function aplicar(
        array $conceptos,
        float $iva,
        float $percepcion,
        ?int $provinciaId = null,
        ?int $impuestoId = null
    ): array {
        $iva = round($iva, 2);
        $percepcion = round($percepcion, 2);

        $idxIva = [];
        $idxPercepcion = [];
        $idxTotal = null;

        foreach ($conceptos as $i => $fila) {
            $nombre = (string) ($fila['concepto'] ?? '');
            if ($nombre === 'Total') {
                $idxTotal = $i;
                continue;
            }
            if (self::esPercepcion($nombre)) {
                $idxPercepcion[] = $i;
                continue;
            }
            if (self::esIva($nombre)) {
                $idxIva[] = $i;
            }
        }

        if ($idxIva === [] && abs($iva) >= 0.01) {
            $filaIva = [
                'concepto' => 'Iva',
                'tasa' => 0,
                'importe' => $iva,
                'baseimponible' => 0,
                'impuesto_id' => $impuestoId,
            ];
            if ($idxTotal === null) {
                $conceptos[] = $filaIva;
                $idxIva[] = count($conceptos) - 1;
            } else {
                array_splice($conceptos, $idxTotal, 0, [$filaIva]);
                $idxIva[] = $idxTotal;
                $idxTotal++;
                foreach ($idxPercepcion as $k => $pos) {
                    if ($pos >= $idxIva[0]) {
                        $idxPercepcion[$k] = $pos + 1;
                    }
                }
            }
        }

        self::repartir($conceptos, $idxIva, $iva);

        if ($idxPercepcion === [] && abs($percepcion) >= 0.01) {
            $filaPerc = [
                'concepto' => 'Perc.',
                'tasa' => 0,
                'importe' => $percepcion,
                'baseimponible' => 0,
                'provincia_id' => $provinciaId,
            ];
            if ($idxTotal === null) {
                $conceptos[] = $filaPerc;
                $idxPercepcion[] = count($conceptos) - 1;
            } else {
                array_splice($conceptos, $idxTotal, 0, [$filaPerc]);
                $idxPercepcion[] = $idxTotal;
                $idxTotal++;
            }
        }

        self::repartir($conceptos, $idxPercepcion, $percepcion);
        self::recalcularTotal($conceptos);

        return $conceptos;
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     * @param  list<int>  $indices
     */
    private static function repartir(array &$conceptos, array $indices, float $objetivo): void
    {
        if ($indices === []) {
            return;
        }

        $objetivo = round($objetivo, 2);
        $actual = 0.0;
        foreach ($indices as $i) {
            $actual += (float) ($conceptos[$i]['importe'] ?? 0);
        }
        $actual = round($actual, 2);

        if (abs($actual - $objetivo) < 0.001) {
            return;
        }

        if (count($indices) === 1 || abs($actual) < 0.001) {
            $conceptos[$indices[0]]['importe'] = $objetivo;
            foreach (array_slice($indices, 1) as $i) {
                $conceptos[$i]['importe'] = 0;
            }

            return;
        }

        $acum = 0.0;
        $ultimo = count($indices) - 1;
        foreach ($indices as $n => $i) {
            if ($n === $ultimo) {
                $conceptos[$i]['importe'] = round($objetivo - $acum, 2);
                continue;
            }
            $parte = round((float) $conceptos[$i]['importe'] / $actual * $objetivo, 2);
            $conceptos[$i]['importe'] = $parte;
            $acum += $parte;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     */
    private static function recalcularTotal(array &$conceptos): void
    {
        $total = 0.0;
        $descuentoImporte = 0.0;
        $idxTotal = null;

        foreach ($conceptos as $i => $fila) {
            $nombre = (string) ($fila['concepto'] ?? '');
            if ($nombre === 'Total') {
                $idxTotal = $i;
                continue;
            }
            if ($nombre === 'Subtotal') {
                continue;
            }
            if (str_starts_with($nombre, 'Descuento')) {
                if ($nombre === 'Descuento por importe') {
                    $descuentoImporte += abs((float) ($fila['importe'] ?? 0));
                }
                continue;
            }
            $total += (float) ($fila['importe'] ?? 0);
        }

        $total = round($total - $descuentoImporte, 2);
        if ($idxTotal === null) {
            $conceptos[] = ['concepto' => 'Total', 'tasa' => 0, 'importe' => $total];

            return;
        }

        $conceptos[$idxTotal]['importe'] = $total;
    }

    private static function esIva(string $concepto): bool
    {
        return str_starts_with($concepto, 'Iva');
    }

    private static function esPercepcion(string $concepto): bool
    {
        return str_contains($concepto, 'Perc') || str_contains($concepto, 'Percep');
    }
}
