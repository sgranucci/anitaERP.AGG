<?php

declare(strict_types=1);

namespace App\Support\Contable\CanonMunicipal;

use Carbon\Carbon;

/**
 * Rangos de liquidación: semanas lun→dom (BSA/KSA) o quincenas fijas (RSA).
 */
final class CanonMunicipalCalendarioSupport
{
    public const TOLERANCIA = 0.05;

    /**
     * Semanas del mes cuyo lunes cae en el mes (lun→dom, sin excepciones).
     *
     * @return list<array{indice: int, etiqueta: string, desde: string, hasta: string}>
     */
    public static function semanasDelMes(int $anio, int $mes): array
    {
        if ($anio < 2000 || $mes < 1 || $mes > 12) {
            $anio = (int) date('Y');
            $mes = (int) date('m');
        }

        $out = [];
        $cursor = Carbon::create($anio, $mes, 1)->startOfDay();
        // Primer lunes del mes (o el 1 si ya es lunes).
        if ($cursor->dayOfWeekIso !== 1) {
            $cursor = $cursor->copy()->next(Carbon::MONDAY);
        }
        if ((int) $cursor->month !== $mes) {
            return [];
        }

        $indice = 1;
        while ((int) $cursor->month === $mes) {
            $hasta = $cursor->copy()->addDays(6);
            $out[] = [
                'indice' => $indice,
                'etiqueta' => sprintf(
                    'Semana %d (%s → %s)',
                    $indice,
                    $cursor->format('d/m'),
                    $hasta->format('d/m'),
                ),
                'desde' => $cursor->format('Y-m-d'),
                'hasta' => $hasta->format('Y-m-d'),
            ];
            $indice++;
            $cursor = $cursor->copy()->addWeek();
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function rangoQuincena(string $periodoYm, int $liquidacion): array
    {
        $anio = (int) substr($periodoYm, 0, 4);
        $mes = (int) substr($periodoYm, 4, 2);
        if ($anio < 2000 || $mes < 1 || $mes > 12) {
            $anio = (int) date('Y');
            $mes = (int) date('m');
        }
        $ultimo = (int) date('t', mktime(0, 0, 0, $mes, 1, $anio));
        if ($liquidacion === 2) {
            return [
                sprintf('%04d-%02d-16', $anio, $mes),
                sprintf('%04d-%02d-%02d', $anio, $mes, $ultimo),
            ];
        }

        return [
            sprintf('%04d-%02d-01', $anio, $mes),
            sprintf('%04d-%02d-15', $anio, $mes),
        ];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function rangoSemana(string $periodoYm, int $indiceSemana): ?array
    {
        $anio = (int) substr($periodoYm, 0, 4);
        $mes = (int) substr($periodoYm, 4, 2);
        foreach (self::semanasDelMes($anio, $mes) as $semana) {
            if ((int) $semana['indice'] === $indiceSemana) {
                return [$semana['desde'], $semana['hasta']];
            }
        }

        return null;
    }

    /**
     * Opciones de liquidación según periodicidad y período YYYYMM.
     *
     * @return array<int, string>
     */
    public static function opcionesLiquidacion(string $periodicidad, string $periodoYm): array
    {
        $anio = (int) substr($periodoYm, 0, 4);
        $mes = (int) substr($periodoYm, 4, 2);
        if ($periodicidad === 'quincenal') {
            return [
                1 => '1ra quincena (01–15)',
                2 => '2da quincena (16–fin)',
            ];
        }

        $ops = [];
        foreach (self::semanasDelMes($anio, $mes) as $semana) {
            $ops[(int) $semana['indice']] = $semana['etiqueta'];
        }

        return $ops;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function resolverRango(string $periodicidad, string $periodoYm, int $liquidacion): array
    {
        if ($periodicidad === 'quincenal') {
            return self::rangoQuincena($periodoYm, $liquidacion <= 0 ? 1 : $liquidacion);
        }

        $rango = self::rangoSemana($periodoYm, $liquidacion <= 0 ? 1 : $liquidacion);
        if ($rango !== null) {
            return $rango;
        }

        $semanas = self::semanasDelMes((int) substr($periodoYm, 0, 4), (int) substr($periodoYm, 4, 2));
        if ($semanas !== []) {
            return [$semanas[0]['desde'], $semanas[0]['hasta']];
        }

        return self::rangoQuincena($periodoYm, 1);
    }

    /**
     * Etiqueta de quincena según rango real (corrige el error del modelo Rebisco).
     */
    public static function etiquetaQuincena(string $desde, string $hasta): string
    {
        $diaDesde = (int) date('j', strtotime($desde));
        if ($diaDesde <= 1) {
            return 'primera';
        }
        if ($diaDesde >= 16) {
            return 'segunda';
        }

        // Fallback por día de fin.
        $diaHasta = (int) date('j', strtotime($hasta));

        return $diaHasta <= 15 ? 'primera' : 'segunda';
    }
}
