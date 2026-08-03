<?php

namespace App\Support\Sueldos;

use Carbon\Carbon;

/**
 * Vigencia de novedades: one-shot (corrida/período) vs recurrente (desde/hasta).
 */
class NovedadSueldosVigencia
{
    /**
     * ¿La novedad aplica a la corrida/período indicado?
     *
     * @param  object|array<string, mixed>  $row  liquidacion_id, periodo, fecha_desde, fecha_hasta
     */
    public static function aplicaACorrida($row, int $liquidacionId, int $periodoYm): bool
    {
        $liqId = (int) (data_get($row, 'liquidacion_id') ?? 0);
        $per = (int) (data_get($row, 'periodo') ?? 0);
        $desde = self::aFecha(data_get($row, 'fecha_desde'));
        $hasta = self::aFecha(data_get($row, 'fecha_hasta'));

        // One-shot amarrado a una corrida concreta.
        if ($liqId > 0) {
            return $liquidacionId > 0 && $liqId === $liquidacionId;
        }

        // Recurrente / rango: vigente si el mes de la corrida cae dentro de [desde, hasta].
        if ($desde !== null) {
            if ($periodoYm <= 0) {
                return false;
            }
            [$ini, $fin] = self::limitesPeriodo($periodoYm);
            if ($desde->gt($fin)) {
                return false;
            }
            if ($hasta !== null && $hasta->lt($ini)) {
                return false;
            }

            return true;
        }

        // One-shot solo por período (sin corrida ni vigencia).
        return $per > 0 && $per === $periodoYm;
    }

    /**
     * ¿La novedad aporta al histórico del período YYYYMM? (VC / IC)
     *
     * @param  object|array<string, mixed>  $row
     */
    public static function aplicaAPeriodoHistorico($row, int $periodoYm): bool
    {
        if ($periodoYm <= 0) {
            return false;
        }

        $per = (int) (data_get($row, 'periodo') ?? 0);
        $desde = self::aFecha(data_get($row, 'fecha_desde'));
        $hasta = self::aFecha(data_get($row, 'fecha_hasta'));

        if ($desde !== null) {
            [$ini, $fin] = self::limitesPeriodo($periodoYm);
            if ($desde->gt($fin)) {
                return false;
            }
            if ($hasta !== null && $hasta->lt($ini)) {
                return false;
            }

            return true;
        }

        return $per === $periodoYm;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function limitesPeriodo(int $periodoYm): array
    {
        $anio = (int) floor($periodoYm / 100);
        $mes = $periodoYm % 100;
        if ($mes < 1 || $mes > 12) {
            $mes = 1;
        }
        $ini = Carbon::create($anio, $mes, 1)->startOfDay();
        $fin = $ini->copy()->endOfMonth()->startOfDay();

        return [$ini, $fin];
    }

    private static function aFecha(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if ($valor instanceof Carbon) {
            return $valor->copy()->startOfDay();
        }
        try {
            return Carbon::parse((string) $valor)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
