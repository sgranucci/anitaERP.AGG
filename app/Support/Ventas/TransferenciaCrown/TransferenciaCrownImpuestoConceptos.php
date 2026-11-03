<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

/**
 * Conceptos de venta_impuesto de una transferencia Crown.
 *
 * El subdiario de IVA lee el importe de «Gravado al …» como neto y el de «Iva …»
 * como impuesto. El neto no va en la base de una sola línea de gravado.
 */
final class TransferenciaCrownImpuestoConceptos
{
    /**
     * @param  array<string, mixed>  $fila
     * @return list<array{concepto: string, tasa: float, base: float, importe: float}>
     */
    public static function filas(array $fila): array
    {
        $total = round(abs((float) ($fila['total'] ?? 0)), 2);
        if ($total <= 0) {
            $total = 0.01;
        }

        if (self::esCortesia($total) && TransferenciaCrownComprobante::iva($fila) <= 0.005) {
            return [
                self::linea('Exento', 0.0, 0.0, 0.01),
                self::linea('Total', 0.0, 0.0, $total),
            ];
        }

        $lineas = [];
        foreach (self::alicotas() as [$tasa, $claveGravado, $claveIva]) {
            $gravado = round(abs((float) ($fila[$claveGravado] ?? 0)), 2);
            $iva = round(abs((float) ($fila[$claveIva] ?? 0)), 2);
            if ($gravado == 0.0 && $iva == 0.0) {
                continue;
            }
            $etiqueta = number_format($tasa, 3, '.', '').'%';
            if ($gravado != 0.0) {
                $lineas[] = self::linea('Gravado al '.$etiqueta, $tasa, $gravado, $gravado);
            }
            if ($iva != 0.0) {
                $lineas[] = self::linea('Iva '.$etiqueta, $tasa, $gravado, $iva);
            }
        }

        $exento = round(abs((float) ($fila['exento'] ?? 0)), 2);
        if ($exento != 0.0) {
            $lineas[] = self::linea('Exento', 0.0, 0.0, $exento);
        }

        $noi = round(abs((float) ($fila['noi'] ?? 0)), 2);
        if ($noi != 0.0) {
            $lineas[] = self::linea('IVA no inscripto', 0.0, $noi, $noi);
        }

        $percepcion = round(abs((float) ($fila['percepcion_iva'] ?? 0)), 2);
        if ($percepcion != 0.0) {
            $lineas[] = self::linea('Percepción IVA', 0.0, $percepcion, $percepcion);
        }

        $otros = round(abs((float) ($fila['otros'] ?? 0)), 2);
        if ($otros != 0.0) {
            $lineas[] = self::linea('Otros tributos', 0.0, $otros, $otros);
        }

        $lineas[] = self::linea('Total', 0.0, 0.0, $total);

        return $lineas;
    }

    /**
     * @return list<array{0: float, 1: string, 2: string}>
     */
    private static function alicotas(): array
    {
        return [
            [21.0, 'gravado21', 'iva21'],
            [10.5, 'gravado105', 'iva105'],
            [27.0, 'gravado27', 'iva27'],
        ];
    }

    private static function esCortesia(float $total): bool
    {
        return abs($total - 0.01) <= 0.005;
    }

    /**
     * @return array{concepto: string, tasa: float, base: float, importe: float}
     */
    private static function linea(string $concepto, float $tasa, float $base, float $importe): array
    {
        return [
            'concepto' => $concepto,
            'tasa' => $tasa,
            'base' => round($base, 2),
            'importe' => round($importe, 2),
        ];
    }
}
