<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use Carbon\Carbon;

/**
 * Decide si la consulta del mayor plano debe ir a cola (período largo) o salir en pantalla.
 */
final class MayorPlanoCuentaConsultaAsyncSupport
{
    /**
     * Un mes calendario (modo mes) o un rango ≤ umbral de días → sincrónico.
     * Rangos más largos (ej. ene–ago) → cola + mail.
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function debeEncolar(array $filtros): bool
    {
        if (! (bool) config('contable.mayor_plano_cuenta.async_habilitado', true)) {
            return false;
        }

        if (($filtros['modo_periodo'] ?? 'mes') === 'mes') {
            return false;
        }

        [$desde, $hasta] = MayorPlanoCuentaListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($desde === '' || $hasta === '') {
            return false;
        }

        try {
            $d = Carbon::parse($desde)->startOfDay();
            $h = Carbon::parse($hasta)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        $dias = (int) $d->diffInDays($h) + 1;
        $umbral = max(1, (int) config('contable.mayor_plano_cuenta.async_dias_minimos', 32));

        return $dias > $umbral;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function diasPeriodo(array $filtros): int
    {
        if (($filtros['modo_periodo'] ?? 'mes') === 'mes') {
            $mes = (int) ($filtros['mes'] ?? 0);
            $anio = (int) ($filtros['anio'] ?? 0);
            if ($mes <= 0 || $anio <= 0) {
                return 0;
            }

            return (int) Carbon::createFromDate($anio, $mes, 1)->daysInMonth;
        }

        [$desde, $hasta] = MayorPlanoCuentaListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($desde === '' || $hasta === '') {
            return 0;
        }

        try {
            return (int) Carbon::parse($desde)->startOfDay()->diffInDays(Carbon::parse($hasta)->startOfDay()) + 1;
        } catch (\Throwable) {
            return 0;
        }
    }
}
