<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Ventana de tickets / prestación según el período de servicio del remito.
 *
 * Mes vencido: se controla el mes calendario anterior a la fecha del remito
 * (el remito de agosto se emite cuando agosto ya cerró).
 * Dentro del mismo mes: del 1 hasta la fecha del remito inclusive.
 */
final class ContratoPeriodoServicioSupport
{
    public const MES_VENCIDO = 'mes_vencido';

    public const MISMO_MES = 'mismo_mes';

    /** @return list<string> */
    public static function modalidades(): array
    {
        return [self::MES_VENCIDO, self::MISMO_MES];
    }

    public static function normalizar(mixed $valor): string
    {
        $v = strtolower(trim((string) $valor));
        if (in_array($v, self::modalidades(), true)) {
            return $v;
        }

        return self::MES_VENCIDO;
    }

    public static function etiqueta(string $modalidad): string
    {
        return match (self::normalizar($modalidad)) {
            self::MISMO_MES => 'Dentro del mismo mes',
            default => 'Mes vencido',
        };
    }

    /**
     * @return array{
     *     modalidad: string,
     *     desde: string,
     *     hasta: string,
     *     etiqueta: string,
     *     etiqueta_corta: string
     * }
     */
    public static function ventana(mixed $modalidad, mixed $fechaRemito): array
    {
        $modo = self::normalizar($modalidad);
        $fecha = self::aCarbon($fechaRemito) ?? Carbon::today();

        if ($modo === self::MISMO_MES) {
            $desde = $fecha->copy()->startOfMonth();
            $hasta = $fecha->copy()->startOfDay();
        } else {
            $cubierto = $fecha->copy()->startOfMonth()->subMonth();
            $desde = $cubierto->copy()->startOfMonth();
            $hasta = $cubierto->copy()->endOfMonth()->startOfDay();
        }

        $etiquetaCorta = self::nombreMes($desde).' '.$desde->year;
        if ($modo === self::MISMO_MES && (int) $hasta->day !== (int) $desde->copy()->endOfMonth()->day) {
            $etiquetaCorta = $desde->format('d/m/Y').' a '.$hasta->format('d/m/Y');
        }

        return [
            'modalidad' => $modo,
            'desde' => $desde->format('Y-m-d'),
            'hasta' => $hasta->format('Y-m-d'),
            'etiqueta' => self::etiqueta($modo).': '.$desde->format('d/m/Y').' a '.$hasta->format('d/m/Y'),
            'etiqueta_corta' => $etiquetaCorta,
        ];
    }

    private static function aCarbon(mixed $valor): ?Carbon
    {
        if ($valor instanceof Carbon) {
            return $valor->copy()->startOfDay();
        }
        if ($valor instanceof DateTimeInterface) {
            return Carbon::instance($valor)->startOfDay();
        }
        $txt = trim((string) $valor);
        if ($txt === '') {
            return null;
        }
        try {
            return Carbon::parse($txt)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function nombreMes(Carbon $fecha): string
    {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
        ];

        return $meses[(int) $fecha->month] ?? $fecha->format('m');
    }
}
