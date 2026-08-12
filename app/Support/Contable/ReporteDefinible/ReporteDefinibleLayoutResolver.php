<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContableLayout;
use Carbon\Carbon;

/**
 * Resuelve columnas de un layout persistido respecto a la ventana de ejecución.
 */
class ReporteDefinibleLayoutResolver
{
    public const TIPO_ACTUAL = 'actual';

    public const TIPO_YTD = 'ytd';

    public const TIPO_ANIO_ANT = 'anio_ant';

    public const TIPO_PLAN = 'plan';

    public const TIPO_VAR = 'var';

    public const TIPO_VAR_PCT = 'var_pct';

    public const TIPO_PERIODO_OFFSET = 'periodo_offset';

    public const TIPO_PCT_SOBRE = 'pct_sobre';

    public const TIPO_FORMULA_COL = 'formula_col';

    /** Misma ventana que «actual», pero con otra valuación (histórico / ajustado / moneda). */
    public const TIPO_VALUACION = 'valuacion';

    public const VALUACION_HISTORICO = 'historico';

    public const VALUACION_AJUSTADO = 'ajustado';

    public const VALUACION_MONEDA = 'moneda';

    /**
     * @return array<string, string>
     */
    public static function tiposColumna(): array
    {
        return [
            self::TIPO_ACTUAL => 'Actual (período)',
            self::TIPO_YTD => 'YTD ejercicio',
            self::TIPO_ANIO_ANT => 'Año anterior',
            self::TIPO_PERIODO_OFFSET => 'Período ±N meses',
            self::TIPO_VALUACION => 'Valuación (histórico / ajustado / moneda)',
            self::TIPO_PLAN => 'Plan (partidas)',
            self::TIPO_VAR => 'Variación Actual−Plan',
            self::TIPO_VAR_PCT => 'Variación %',
            self::TIPO_PCT_SOBRE => '% sobre columna',
            self::TIPO_FORMULA_COL => 'Fórmula de columnas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function valuaciones(): array
    {
        return [
            self::VALUACION_HISTORICO => 'Histórico (sin ajuste por inflación)',
            self::VALUACION_AJUSTADO => 'Ajustado por inflación (incluye asientos de ajuste)',
            self::VALUACION_MONEDA => 'Convertido a moneda',
        ];
    }

    /**
     * Tipos que cargan datos (asientos / plan), no derivados.
     *
     * @return list<string>
     */
    public static function tiposDatos(): array
    {
        return [
            self::TIPO_ACTUAL,
            self::TIPO_YTD,
            self::TIPO_ANIO_ANT,
            self::TIPO_PERIODO_OFFSET,
            self::TIPO_VALUACION,
            self::TIPO_PLAN,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function armarColumnas(ReporteContableLayout $layout, string $fechaDesde, string $fechaHasta): array
    {
        $layout->loadMissing('columnas');
        $out = [];
        foreach ($layout->columnas as $col) {
            $tipo = (string) $col->tipo;
            $meta = is_array($col->meta) ? $col->meta : [];
            [$fd, $fh] = $this->ventanaParaTipo($tipo, $fechaDesde, $fechaHasta, $meta);
            $out[] = [
                'key' => (string) $col->key,
                'label' => (string) $col->label,
                'tipo' => $tipo,
                'fecha_desde' => $fd,
                'fecha_hasta' => $fh,
                'meta' => $meta,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{0: string, 1: string}
     */
    public function ventanaParaTipo(string $tipo, string $fechaDesde, string $fechaHasta, array $meta = []): array
    {
        if ($tipo === self::TIPO_YTD) {
            $anio = (int) substr($fechaHasta, 0, 4);

            return [sprintf('%04d-01-01', $anio), $fechaHasta];
        }
        if ($tipo === self::TIPO_ANIO_ANT) {
            $d = Carbon::createFromFormat('Y-m-d', $fechaDesde)->subYear();
            $h = Carbon::createFromFormat('Y-m-d', $fechaHasta)->subYear();

            return [$d->format('Y-m-d'), $h->format('Y-m-d')];
        }
        if ($tipo === self::TIPO_PERIODO_OFFSET) {
            $meses = (int) ($meta['offset_meses'] ?? 0);
            $d = Carbon::createFromFormat('Y-m-d', $fechaDesde)->addMonthsNoOverflow($meses);
            $h = Carbon::createFromFormat('Y-m-d', $fechaHasta)->addMonthsNoOverflow($meses);

            return [$d->format('Y-m-d'), $h->format('Y-m-d')];
        }

        // actual, plan, var*, pct_sobre, formula_col, default
        return [$fechaDesde, $fechaHasta];
    }

    public function layoutUsaPlan(ReporteContableLayout $layout): bool
    {
        $layout->loadMissing('columnas');
        foreach ($layout->columnas as $col) {
            if (in_array((string) $col->tipo, [self::TIPO_PLAN, self::TIPO_VAR, self::TIPO_VAR_PCT], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<ReporteContableLayout>
     */
    public function listarParaEjecucion(?int $reporteId): array
    {
        $q = ReporteContableLayout::query()
            ->activos()
            ->with('columnas')
            ->orderBy('orden')
            ->orderBy('codigo');

        if ($reporteId && $reporteId > 0) {
            $q->where(function ($w) use ($reporteId) {
                $w->whereNull('reporte_contable_id')
                    ->orWhere('reporte_contable_id', $reporteId);
            });
        } else {
            $q->sistema();
        }

        return $q->get()->all();
    }

    public function find(int $layoutId): ?ReporteContableLayout
    {
        return ReporteContableLayout::query()
            ->with('columnas')
            ->where('activo', true)
            ->find($layoutId);
    }
}
