<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorPlanoCuentaListadoFiltros;
use Carbon\Carbon;

/**
 * Decide si la consulta del mayor plano debe ir a cola (período largo + cuentas amplias) o salir en pantalla.
 */
final class MayorPlanoCuentaConsultaAsyncSupport
{
    /**
     * Cola + mail solo si:
     * - período largo (rango de fechas > umbral; modo mes nunca), y
     * - selección de cuentas amplia (todas las cuentas, lista grande o rango de códigos grande).
     *
     * Con pocas cuentas / rango chico, aunque sea ene–ago, sigue en pantalla para analizar y exportar.
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function debeEncolar(array $filtros): bool
    {
        if (! (bool) config('contable.mayor_plano_cuenta.async_habilitado', true)) {
            return false;
        }

        if (! self::periodoLargo($filtros)) {
            return false;
        }

        return self::seleccionCuentasAmplia($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function periodoLargo(array $filtros): bool
    {
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
     * Todas las cuentas, lista con muchas cuentas, o rango de códigos “ancho”.
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function seleccionCuentasAmplia(array $filtros): bool
    {
        if (! MayorPlanoCuentaListadoFiltros::tieneSeleccionParticularCuentas($filtros)) {
            return true;
        }

        $cuentas = array_values(array_filter(
            array_map('intval', $filtros['cuentas'] ?? []),
            static fn (int $c) => $c > 0,
        ));
        if ($cuentas !== []) {
            $minLista = max(1, (int) config('contable.mayor_plano_cuenta.async_cuentas_lista_minimas', 20));

            return count($cuentas) >= $minLista;
        }

        $desde = (int) ($filtros['cuenta_desde'] ?? 0);
        $hasta = (int) ($filtros['cuenta_hasta'] ?? 0);

        // Rango abierto (solo desde o solo hasta) ≈ barrido amplio.
        if ($desde <= 0 || $hasta <= 0) {
            return true;
        }

        if ($hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $span = $hasta - $desde;
        $spanMinimo = max(0, (int) config('contable.mayor_plano_cuenta.async_cuentas_rango_span_minimo', 50_000_000));

        return $span >= $spanMinimo;
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
