<?php

namespace App\Support\Caja;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Vigencia de tipos/subtipos de bóveda por fecha de jornada.
 * Corte catálogo unificado: 2026-11-01.
 */
final class TesoreriaCrownBovedaVigenciaSupport
{
    public const CORTE_NUEVO_CATALOGO = '2026-11-01';

    public const VIGENCIA_LEGADO_DESDE = '2025-01-01';

    public const VIGENCIA_LEGADO_HASTA = '2026-10-31';

    /** @var list<string> */
    public const ABREV_SISTEMA = ['APER', 'HAB', 'REN'];

    public static function normalizarFecha(null|string|CarbonInterface $fecha): string
    {
        if ($fecha instanceof CarbonInterface) {
            return $fecha->format('Y-m-d');
        }
        $texto = trim((string) $fecha);
        if ($texto === '') {
            return Carbon::today()->format('Y-m-d');
        }
        try {
            return Carbon::parse($texto)->format('Y-m-d');
        } catch (\Throwable) {
            return Carbon::today()->format('Y-m-d');
        }
    }

    public static function esCatalogoNuevo(null|string|CarbonInterface $fecha): bool
    {
        return self::normalizarFecha($fecha) >= self::CORTE_NUEVO_CATALOGO;
    }

    /**
     * (desde IS NULL OR desde <= fecha) AND (hasta IS NULL OR hasta >= fecha)
     */
    public static function aplicarVigenteEn(Builder $query, string $fechaYmd, string $prefijo = ''): Builder
    {
        $colDesde = $prefijo === '' ? 'vigente_desde' : $prefijo.'.vigente_desde';
        $colHasta = $prefijo === '' ? 'vigente_hasta' : $prefijo.'.vigente_hasta';

        return $query
            ->where(function (Builder $q) use ($colDesde, $fechaYmd) {
                $q->whereNull($colDesde)->orWhereDate($colDesde, '<=', $fechaYmd);
            })
            ->where(function (Builder $q) use ($colHasta, $fechaYmd) {
                $q->whereNull($colHasta)->orWhereDate($colHasta, '>=', $fechaYmd);
            });
    }

    public static function registroVigenteEn(mixed $desde, mixed $hasta, null|string|CarbonInterface $fecha): bool
    {
        $fechaYmd = self::normalizarFecha($fecha);
        $desdeYmd = $desde ? self::normalizarFecha((string) $desde) : null;
        $hastaYmd = $hasta ? self::normalizarFecha((string) $hasta) : null;

        if ($desdeYmd !== null && $desdeYmd > $fechaYmd) {
            return false;
        }
        if ($hastaYmd !== null && $hastaYmd < $fechaYmd) {
            return false;
        }

        return true;
    }

    /**
     * Filtro ABM: todos | vigentes | vencidos | futuros (respecto a $fecha).
     */
    public static function aplicarFiltroAbm(Builder $query, string $filtro, string $fechaYmd, string $prefijo = ''): Builder
    {
        $colDesde = $prefijo === '' ? 'vigente_desde' : $prefijo.'.vigente_desde';
        $colHasta = $prefijo === '' ? 'vigente_hasta' : $prefijo.'.vigente_hasta';

        return match ($filtro) {
            'vencidos' => $query->whereNotNull($colHasta)->whereDate($colHasta, '<', $fechaYmd),
            'futuros' => $query->whereNotNull($colDesde)->whereDate($colDesde, '>', $fechaYmd),
            'vigentes' => self::aplicarVigenteEn($query, $fechaYmd, $prefijo),
            default => $query,
        };
    }
}
