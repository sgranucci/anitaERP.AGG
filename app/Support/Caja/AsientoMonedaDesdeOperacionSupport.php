<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Numerico\NumeroDecimalLocalSupport;

/**
 * El asiento de un ingreso/egreso o de una orden de pago se graba en la moneda
 * de la operación. Si el renglón quedó en pesos pero el importe y la cotización
 * son los del dólar (242 con TC 1.515, no 242 × 1.515), el mayor en pesos muestra
 * el nominal y el mayor en dólares lo divide por la cotización.
 */
final class AsientoMonedaDesdeOperacionSupport
{
    /**
     * Medios de caja y cheques, antes de pisar moneda_ids con la moneda del asiento.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{moneda_id: int, monto: float, cotizacion: float}>
     */
    public static function origenesDesdeMedios(array $data): array
    {
        $origenes = [];
        $origenes = array_merge($origenes, self::origenesDe(
            $data['moneda_ids'] ?? [],
            $data['montos'] ?? [],
            $data['cotizaciones'] ?? [],
        ));
        $origenes = array_merge($origenes, self::origenesDe(
            $data['moneda_emitido_ids'] ?? [],
            $data['montocheque_emitidos'] ?? [],
            $data['cotizacioncheque_emitidos'] ?? [],
        ));
        $origenes = array_merge($origenes, self::origenesDe(
            $data['monedacheque_recibido_ids'] ?? [],
            $data['montocheque_recibidos'] ?? [],
            $data['cotizacioncheque_recibidos'] ?? [],
        ));

        return $origenes;
    }

    /**
     * @param  list<mixed>  $monedasLinea
     * @param  list<mixed>  $debes
     * @param  list<mixed>  $haberes
     * @param  list<mixed>  $cotizaciones
     * @param  list<array{moneda_id: int, monto: float, cotizacion: float}>  $origenes
     * @return list<mixed>
     */
    public static function alinear(
        array $monedasLinea,
        array $debes,
        array $haberes,
        array $cotizaciones,
        array $origenes,
    ): array {
        $origenesMe = [];
        foreach ($origenes as $origen) {
            $moneda = (int) ($origen['moneda_id'] ?? 1);
            $cotizacion = (float) ($origen['cotizacion'] ?? 0);
            $monto = abs((float) ($origen['monto'] ?? 0));
            if ($moneda <= 1 || $cotizacion <= 1.0001 || $monto < 0.0001) {
                continue;
            }
            $origenesMe[] = [
                'moneda_id' => $moneda,
                'cotizacion' => $cotizacion,
                'monto' => $monto,
            ];
        }
        if ($origenesMe === []) {
            return $monedasLinea;
        }

        foreach ($monedasLinea as $i => $monedaLin) {
            if ((int) $monedaLin > 1) {
                continue;
            }
            $cotizacionLinea = NumeroDecimalLocalSupport::aFloat($cotizaciones[$i] ?? 0);
            if ($cotizacionLinea <= 1.0001) {
                continue;
            }
            $importe = max(
                abs(NumeroDecimalLocalSupport::aFloat($debes[$i] ?? 0)),
                abs(NumeroDecimalLocalSupport::aFloat($haberes[$i] ?? 0)),
            );
            if ($importe < 0.0001) {
                continue;
            }
            foreach ($origenesMe as $origen) {
                if (abs($cotizacionLinea - $origen['cotizacion']) > 0.02) {
                    continue;
                }
                $enPesos = $origen['monto'] * $origen['cotizacion'];
                if (self::cerca($importe, $enPesos)) {
                    continue;
                }
                if (self::cerca($importe, $origen['monto'])) {
                    $monedasLinea[$i] = $origen['moneda_id'];
                    break;
                }
            }
        }

        return $monedasLinea;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{moneda_id: int, monto: float, cotizacion: float}>  $origenes
     * @return array<string, mixed>
     */
    public static function aplicarEnPayload(array $data, array $origenes): array
    {
        $monedas = $data['moneda_ids'] ?? [];
        if (! is_array($monedas) || $monedas === []) {
            return $data;
        }

        $data['moneda_ids'] = self::alinear(
            $monedas,
            is_array($data['debes'] ?? null) ? $data['debes'] : [],
            is_array($data['haberes'] ?? null) ? $data['haberes'] : [],
            is_array($data['cotizaciones'] ?? null) ? $data['cotizaciones'] : [],
            $origenes,
        );

        return $data;
    }

    /**
     * @param  mixed  $monedas
     * @param  mixed  $montos
     * @param  mixed  $cotizaciones
     * @return list<array{moneda_id: int, monto: float, cotizacion: float}>
     */
    private static function origenesDe(mixed $monedas, mixed $montos, mixed $cotizaciones): array
    {
        if (! is_array($monedas) || ! is_array($montos)) {
            return [];
        }
        $cotizaciones = is_array($cotizaciones) ? $cotizaciones : [];
        $origenes = [];
        foreach ($montos as $i => $montoRaw) {
            $origenes[] = [
                'moneda_id' => (int) ($monedas[$i] ?? 1),
                'monto' => abs(NumeroDecimalLocalSupport::aFloat($montoRaw)),
                'cotizacion' => NumeroDecimalLocalSupport::aFloat($cotizaciones[$i] ?? 0),
            ];
        }

        return $origenes;
    }

    private static function cerca(float $a, float $b): bool
    {
        return abs($a - $b) <= max(0.05, abs($b) * 0.001);
    }
}
