<?php

namespace App\Support\Caja\Flash;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Helpers de período e índices diarios para parámetros del flash.
 */
final class FlashParametroPeriodoSupport
{
    /**
     * @return list<array{
     *   fecha: string,
     *   customer: int,
     *   season_index: float,
     *   sindex_bingo: float,
     *   sindex_slot: float,
     *   sindex_rul: float,
     *   sindex_poker: float,
     *   sindex_estac: float,
     *   vehiculos: int
     * }>
     */
    public static function diasVaciosParaPeriodo(string $periodoYyyymm): array
    {
        if (! preg_match('/^\d{6}$/', $periodoYyyymm)) {
            return [];
        }

        $inicio = Carbon::createFromFormat('Ym', $periodoYyyymm)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();
        $dias = [];

        foreach (CarbonPeriod::create($inicio, $fin) as $dia) {
            $dias[] = self::filaVacia($dia->format('Y-m-d'));
        }

        return $dias;
    }

    /**
     * @param  list<array<string, mixed>>  $indicesExistentes  keyed or list with 'fecha'
     * @return list<array<string, mixed>>
     */
    public static function fusionarConDiasDelPeriodo(string $periodoYyyymm, array $indicesExistentes): array
    {
        $porFecha = [];
        foreach ($indicesExistentes as $fila) {
            $fecha = self::normalizarFecha((string) ($fila['fecha'] ?? ''));
            if ($fecha === null) {
                continue;
            }
            $porFecha[$fecha] = self::normalizarFila($fila, $fecha);
        }

        $dias = [];
        foreach (self::diasVaciosParaPeriodo($periodoYyyymm) as $vacio) {
            $fecha = $vacio['fecha'];
            $dias[] = $porFecha[$fecha] ?? $vacio;
        }

        return $dias;
    }

    /**
     * @param  list<array<string, mixed>>  $indices
     * @return array{
     *   total_season: float,
     *   total_sbingo: float,
     *   total_sslot: float,
     *   total_srul: float,
     *   total_spoker: float,
     *   total_s_estac: float
     * }
     */
    public static function totalesSeasonDesdeIndices(array $indices): array
    {
        $totales = [
            'total_season' => 0.0,
            'total_sbingo' => 0.0,
            'total_sslot' => 0.0,
            'total_srul' => 0.0,
            'total_spoker' => 0.0,
            'total_s_estac' => 0.0,
        ];

        foreach ($indices as $fila) {
            $totales['total_season'] += (float) ($fila['season_index'] ?? 0);
            $totales['total_sbingo'] += (float) ($fila['sindex_bingo'] ?? 0);
            $totales['total_sslot'] += (float) ($fila['sindex_slot'] ?? 0);
            $totales['total_srul'] += (float) ($fila['sindex_rul'] ?? 0);
            $totales['total_spoker'] += (float) ($fila['sindex_poker'] ?? 0);
            $totales['total_s_estac'] += (float) ($fila['sindex_estac'] ?? 0);
        }

        foreach ($totales as $k => $v) {
            $totales[$k] = round($v, 6);
        }

        return $totales;
    }

    /** @return array<string, mixed> */
    public static function filaVacia(string $fecha): array
    {
        return [
            'fecha' => $fecha,
            'customer' => 0,
            'season_index' => 0.0,
            'sindex_bingo' => 0.0,
            'sindex_slot' => 0.0,
            'sindex_rul' => 0.0,
            'sindex_poker' => 0.0,
            'sindex_estac' => 0.0,
            'vehiculos' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    public static function normalizarFila(array $fila, ?string $fecha = null): array
    {
        $fecha = $fecha ?? self::normalizarFecha((string) ($fila['fecha'] ?? ''));

        return [
            'fecha' => $fecha ?? '',
            'customer' => (int) ($fila['customer'] ?? 0),
            'season_index' => round((float) ($fila['season_index'] ?? 0), 6),
            'sindex_bingo' => round((float) ($fila['sindex_bingo'] ?? 0), 6),
            'sindex_slot' => round((float) ($fila['sindex_slot'] ?? 0), 6),
            'sindex_rul' => round((float) ($fila['sindex_rul'] ?? 0), 6),
            'sindex_poker' => round((float) ($fila['sindex_poker'] ?? 0), 6),
            'sindex_estac' => round((float) ($fila['sindex_estac'] ?? 0), 6),
            'vehiculos' => (int) ($fila['vehiculos'] ?? 0),
        ];
    }

    public static function normalizarFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }
        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function labelPeriodo(string $periodoYyyymm): string
    {
        if (! preg_match('/^\d{6}$/', $periodoYyyymm)) {
            return $periodoYyyymm;
        }
        try {
            return Carbon::createFromFormat('Ym', $periodoYyyymm)->locale('es')->isoFormat('MMMM YYYY');
        } catch (\Throwable) {
            return substr($periodoYyyymm, 4, 2).'/'.substr($periodoYyyymm, 0, 4);
        }
    }
}
