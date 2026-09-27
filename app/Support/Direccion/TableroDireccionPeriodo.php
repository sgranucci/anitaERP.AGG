<?php

namespace App\Support\Direccion;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Período compartido del tablero de dirección y el tramo anterior de igual largo.
 */
class TableroDireccionPeriodo
{
    /**
     * @return array{
     *   preset: string,
     *   desde: string,
     *   hasta: string,
     *   anterior_desde: string,
     *   anterior_hasta: string,
     *   etiqueta: string,
     *   etiqueta_anterior: string
     * }
     */
    public static function resolver(Request $request): array
    {
        $preset = (string) $request->query('preset', 'mes');
        $hoy = Carbon::today();
        $desdePedido = self::fecha($request->query('desde'));
        $hastaPedido = self::fecha($request->query('hasta'));

        if (in_array($preset, ['rango', 'dia'], true) && $desdePedido && $hastaPedido) {
            $desde = $desdePedido->copy();
            $hasta = $hastaPedido->copy();
            if ($hasta->lt($desde)) {
                [$desde, $hasta] = [$hasta->copy(), $desde->copy()];
            }
            if ($desde->toDateString() === $hasta->toDateString()) {
                $preset = 'dia';
            }
        } elseif ($preset === 'hoy') {
            $desde = $hoy->copy();
            $hasta = $hoy->copy();
        } elseif ($preset === 'ayer') {
            $desde = $hoy->copy()->subDay();
            $hasta = $desde->copy();
        } elseif ($preset === 'dia') {
            $desde = self::fecha($request->query('dia')) ?? $hoy->copy();
            $hasta = $desde->copy();
        } elseif ($preset === 'rango') {
            $desde = self::fecha($request->query('desde')) ?? $hoy->copy()->startOfMonth();
            $hasta = self::fecha($request->query('hasta')) ?? $hoy->copy();
            if ($hasta->lt($desde)) {
                [$desde, $hasta] = [$hasta->copy(), $desde->copy()];
            }
        } elseif ($preset === 'mes_anterior') {
            $desde = $hoy->copy()->subMonthNoOverflow()->startOfMonth();
            $hasta = $desde->copy()->endOfMonth();
        } elseif ($preset === 'anio') {
            $desde = $hoy->copy()->startOfYear();
            $hasta = $hoy->copy();
        } else {
            $preset = 'mes';
            $desde = $hoy->copy()->startOfMonth();
            $hasta = $hoy->copy();
        }

        $dias = $desde->diffInDays($hasta) + 1;
        $anteriorHasta = $desde->copy()->subDay();
        $anteriorDesde = $anteriorHasta->copy()->subDays($dias - 1);

        return [
            'preset' => $preset,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'anterior_desde' => $anteriorDesde->toDateString(),
            'anterior_hasta' => $anteriorHasta->toDateString(),
            'etiqueta' => self::etiqueta($desde, $hasta),
            'etiqueta_anterior' => self::etiqueta($anteriorDesde, $anteriorHasta),
        ];
    }

    private static function etiqueta(Carbon $desde, Carbon $hasta): string
    {
        if ($desde->toDateString() === $hasta->toDateString()) {
            return $desde->format('d/m/Y');
        }

        return $desde->format('d/m/Y').' — '.$hasta->format('d/m/Y');
    }

    private static function fecha(mixed $valor): ?Carbon
    {
        $texto = trim((string) $valor);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $texto)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
