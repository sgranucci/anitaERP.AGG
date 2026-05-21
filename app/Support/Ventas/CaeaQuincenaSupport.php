<?php

namespace App\Support\Ventas;

use Carbon\Carbon;

/**
 * Cálculo de quincenas CAEA (WSFEv1 / RG 4291).
 */
final class CaeaQuincenaSupport
{
    /**
     * @return array{periodo: int, orden: int}
     */
    public static function periodoOrdenDesdeFecha(Carbon|string $fecha): array
    {
        $f = $fecha instanceof Carbon ? $fecha->copy() : Carbon::parse($fecha);

        return [
            'periodo' => (int) $f->format('Ym'),
            'orden' => $f->day <= 15 ? 1 : 2,
        ];
    }

    /**
     * @return array{desde: Carbon, hasta: Carbon}
     */
    public static function fechasQuincena(int $periodo, int $orden): array
    {
        $anio = (int) floor($periodo / 100);
        $mes = $periodo % 100;
        $base = Carbon::create($anio, $mes, 1)->startOfDay();

        if ($orden === 1) {
            return [
                'desde' => $base->copy(),
                'hasta' => $base->copy()->day(15),
            ];
        }

        return [
            'desde' => $base->copy()->day(16),
            'hasta' => $base->copy()->endOfMonth()->startOfDay(),
        ];
    }

    /**
     * Quincenas que AFIP permite solicitar hoy (desde 5 días antes del inicio hasta el fin de la quincena).
     *
     * @return list<array{periodo: int, orden: int}>
     */
    public static function quincenasEnVentanaSolicitud(?Carbon $hoy = null): array
    {
        $hoy = ($hoy ?? now())->copy()->startOfDay();
        $candidatas = [];

        for ($offsetMes = -1; $offsetMes <= 1; $offsetMes++) {
            $ref = $hoy->copy()->addMonths($offsetMes);
            $periodoBase = (int) $ref->format('Ym');

            foreach ([1, 2] as $orden) {
                $fechas = self::fechasQuincena($periodoBase, $orden);
                $inicioVentana = $fechas['desde']->copy()->subDays(5);

                if ($hoy->between($inicioVentana, $fechas['hasta'])) {
                    $candidatas[] = ['periodo' => $periodoBase, 'orden' => $orden];
                }
            }
        }

        $unicas = [];
        foreach ($candidatas as $c) {
            $key = $c['periodo'].'-'.$c['orden'];
            $unicas[$key] = $c;
        }

        return array_values($unicas);
    }

    public static function etiquetaQuincena(int $periodo, int $orden): string
    {
        $fechas = self::fechasQuincena($periodo, $orden);

        return sprintf(
            '%s — quincena %d (%s al %s)',
            substr((string) $periodo, 0, 4).'/'.substr((string) $periodo, 4, 2),
            $orden,
            $fechas['desde']->format('d/m/Y'),
            $fechas['hasta']->format('d/m/Y')
        );
    }

    /**
     * Periodo y orden a partir de fecha Informix (YYYYMMDD entero).
     *
     * @return array{periodo: int, orden: int}
     */
    public static function periodoOrdenDesdeFechaAnita(int|string $yyyymmdd): array
    {
        $s = preg_replace('/\D+/', '', (string) $yyyymmdd) ?? '';
        if (strlen($s) < 8) {
            throw new \InvalidArgumentException('Fecha Anita inválida: '.$yyyymmdd);
        }

        $day = (int) substr($s, 6, 2);

        return [
            'periodo' => (int) substr($s, 0, 6),
            'orden' => $day <= 15 ? 1 : 2,
        ];
    }

    public static function parseFechaArca(?string $yyyymmdd): ?string
    {
        $s = preg_replace('/\D+/', '', (string) $yyyymmdd) ?? '';
        if (strlen($s) < 8) {
            return null;
        }

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }

    public static function parseFechaHoraArca(?string $yyyymmddhhmmss): ?string
    {
        $s = preg_replace('/\D+/', '', (string) $yyyymmddhhmmss) ?? '';
        if (strlen($s) < 14) {
            return self::parseFechaArca($s);
        }

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2)
            .' '.substr($s, 8, 2).':'.substr($s, 10, 2).':'.substr($s, 12, 2);
    }
}
