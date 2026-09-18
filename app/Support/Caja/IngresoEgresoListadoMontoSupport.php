<?php

declare(strict_types=1);

namespace App\Support\Caja;

/**
 * Monto y leyendas del index/export IE: cuentas de caja + cheques.
 * Sin cheques el listado quedaba en 0 cuando el pago era solo CHP.
 */
final class IngresoEgresoListadoMontoSupport
{
    /**
     * @return array{ingreso: float, egreso: float, monto: float, lineas: list<string>}
     */
    public static function resumen(object $movimiento): array
    {
        $ingreso = 0.0;
        $egreso = 0.0;
        $lineas = [];

        foreach ($movimiento->caja_movimiento_cuentacajas ?? [] as $linea) {
            $coef = self::coeficiente($linea->moneda_id ?? 1, $linea->cotizacion ?? 1);
            $monto = (float) ($linea->monto ?? 0);
            if ($monto > 0) {
                $ingreso += $monto * $coef;
            } elseif ($monto < 0) {
                $egreso += abs($monto) * $coef;
            }
            $nombre = trim((string) ($linea->cuentacajas->nombre ?? ''));
            $lineas[] = trim($nombre.' '.self::fmt($monto));
        }

        foreach ($movimiento->cheques ?? [] as $cheque) {
            if (! empty($cheque->cheque_reemplaza_id)) {
                continue;
            }
            $origen = strtoupper(trim((string) ($cheque->origen ?? '')));
            $coef = self::coeficiente($cheque->moneda_id ?? 1, $cheque->cotizacion ?? 1);
            $montoAbs = abs((float) ($cheque->monto ?? 0));
            if ($montoAbs < 0.000001) {
                continue;
            }
            $nro = trim((string) ($cheque->numerocheque ?? ''));
            $cuenta = trim((string) ($cheque->cuentacajas->nombre ?? ''));
            if ($origen === 'E') {
                $egreso += $montoAbs * $coef;
                $lineas[] = trim('CHP '.$nro.' '.$cuenta.' '.self::fmt(-$montoAbs));
            } elseif ($origen === 'R') {
                $ingreso += $montoAbs * $coef;
                $lineas[] = trim('CHT '.$nro.' '.$cuenta.' '.self::fmt($montoAbs));
            }
        }

        $ingreso = round($ingreso, 2);
        $egreso = round($egreso, 2);

        return [
            'ingreso' => $ingreso,
            'egreso' => $egreso,
            'monto' => $ingreso != 0.0 ? $ingreso : $egreso,
            'lineas' => array_values(array_filter($lineas, static fn ($t) => $t !== '')),
        ];
    }

    private static function coeficiente(mixed $monedaId, mixed $cotizacion): float
    {
        if ((int) $monedaId > 1) {
            $cot = (float) $cotizacion;

            return $cot > 0 ? $cot : 1.0;
        }

        return 1.0;
    }

    private static function fmt(float $monto): string
    {
        return number_format($monto, 2, ',', '.');
    }
}
