<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Contable\CuentaCentrocostoAsignadosSupport;

/**
 * Líneas de contrapartida de un ingreso/egreso (caja, cheques, tarjeta) cuya cuenta
 * maneja centro de costo y no tienen un selector propio en pantalla.
 *
 * Toman el centro del gasto del comprobante (líneas del rango indicado): un solo centro
 * lo heredan; varios lo reparten en la misma proporción. Así no hace falta ir al asiento
 * completo a cargarlo. Si el gasto no tiene centro, la línea queda en 0 y el control de
 * grabación la rechaza.
 */
final class IngresoEgresoCentroCajaHerenciaSupport
{
    /**
     * @param  list<array<string, mixed>>  $asiento
     * @return list<array<string, mixed>>
     */
    public static function aplicar(array $asiento, int $desdeGasto, int $hastaGasto): array
    {
        $pesos = [];
        for ($i = $desdeGasto; $i < $hastaGasto && $i < count($asiento); $i++) {
            $cc = (int) ($asiento[$i]['centrocosto_id'] ?? 0);
            $importe = self::importe($asiento[$i]);
            if ($cc <= 0 || $importe < 0.0001) {
                continue;
            }
            $pesos[$cc] = round(($pesos[$cc] ?? 0.0) + $importe, 2);
        }
        if ($pesos === []) {
            return $asiento;
        }

        $out = [];
        foreach ($asiento as $i => $linea) {
            $cuentaId = (int) ($linea['cuentacontable_id'] ?? 0);
            $importe = self::importe($linea);
            $enRangoGasto = $i >= $desdeGasto && $i < $hastaGasto;
            // Cupones de tarjeta se agregan dentro del bloque de comprobantes, pero son contrapartida.
            $esCupon = str_starts_with((string) ($linea['observacion'] ?? ''), 'Cupones ');
            $esContrapartida = $i < $desdeGasto || $esCupon;

            if (
                ($enRangoGasto && ! $esCupon)
                || ! $esContrapartida
                || (int) ($linea['centrocosto_id'] ?? 0) > 0
                || $cuentaId <= 0
                || $importe < 0.0001
                || ! CuentaCentrocostoAsignadosSupport::maneja($cuentaId)
            ) {
                $out[] = $linea;

                continue;
            }

            $lado = (float) ($linea['debe'] ?? 0) > 0 ? 'debe' : 'haber';
            $otro = $lado === 'debe' ? 'haber' : 'debe';
            $totalPeso = array_sum($pesos);
            $restante = $importe;
            $ultimo = array_key_last($pesos);

            foreach ($pesos as $cc => $peso) {
                $parte = $cc === $ultimo
                    ? round($restante, 2)
                    : round($importe * ($totalPeso > 0 ? $peso / $totalPeso : 0), 2);
                $restante = round($restante - $parte, 2);
                $centro = CuentaCentrocostoAsignadosSupport::resolver($cuentaId, (int) $cc, 0);
                $out[] = array_merge($linea, [
                    $lado => $parte,
                    $otro => '',
                    'centrocosto_id' => $centro,
                ]);
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $linea */
    private static function importe(array $linea): float
    {
        return round(abs((float) ($linea['debe'] ?? 0)) + abs((float) ($linea['haber'] ?? 0)), 2);
    }
}
