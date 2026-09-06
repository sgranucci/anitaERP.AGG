<?php

namespace App\Support\Compras;

use App\Models\Presupuesto\Presupuesto;
use App\Models\Presupuesto\Presupuesto_Escenario;
use Illuminate\Support\Facades\DB;

/**
 * Lee el presupuesto aprobado del plan de partidas para contrastarlo contra el gasto
 * recurrente ya comprometido.
 *
 * Una suscripción no se autoriza sola: consume una porción del presupuesto anual de una
 * cuenta y un centro de costo. Esa porción es el dato que le falta al gerente cuando
 * aprueba, y el que muestra si un área ya tiene el año hipotecado en licencias.
 *
 * Sigue la misma convención que el comparativo contable (ReporteDefiniblePartidaPlanReader):
 * presupuesto del año más reciente, primer escenario, partidas ACTIVA y moneda de la partida.
 */
final class SuscripcionPresupuestoSupport
{
    /** Cache por año para no repetir la resolución de presupuesto y escenario. */
    private static array $contextoPorAnio = [];

    /** Cache de montos por clave empresa|cc|cuenta|año. */
    private static array $montosPorClave = [];

    /**
     * Presupuesto del año para una cuenta y centro de costo concretos.
     *
     * @return array{anual: float, mensual: float, presupuesto: string, moneda_id: int|null}|null
     *                                                                                            null cuando no hay presupuesto cargado para ese año o esa combinación
     */
    public static function delCentrocostoYCuenta(
        int $empresaId,
        int $centrocostoId,
        int $cuentacontableId,
        ?int $anio = null
    ): ?array {
        $anio = $anio ?: (int) date('Y');
        if ($empresaId <= 0 || $centrocostoId <= 0 || $cuentacontableId <= 0) {
            return null;
        }

        $contexto = self::contexto($anio);
        if ($contexto === null) {
            return null;
        }

        $clave = $empresaId.'|'.$centrocostoId.'|'.$cuentacontableId.'|'.$anio;
        if (array_key_exists($clave, self::$montosPorClave)) {
            return self::$montosPorClave[$clave];
        }

        $fila = DB::table('partidagasto as p')
            ->join('partidagasto_monto as m', 'm.partidagasto_id', '=', 'p.id')
            ->where('p.empresa_id', $empresaId)
            ->where('p.centrocosto_id', $centrocostoId)
            ->where('p.cuentacontable_id', $cuentacontableId)
            ->where('p.presupuesto_id', $contexto['presupuesto_id'])
            ->where('p.presupuesto_escenario_id', $contexto['escenario_id'])
            ->where('p.estado', 'ACTIVA')
            ->where('m.periodo', 'like', $anio.'-%')
            ->selectRaw('SUM(m.monto) AS anual, MIN(p.moneda_id) AS moneda_id, COUNT(DISTINCT m.periodo) AS periodos')
            ->first();

        $anual = (float) ($fila->anual ?? 0);
        if ($anual == 0.0) {
            return self::$montosPorClave[$clave] = null;
        }

        // El presupuesto puede estar cargado en menos de doce períodos; el mensual se
        // promedia sobre los meses efectivamente presupuestados, no sobre doce fijos.
        $periodos = max(1, (int) ($fila->periodos ?? 12));

        return self::$montosPorClave[$clave] = [
            'anual' => round($anual, 2),
            'mensual' => round($anual / $periodos, 2),
            'presupuesto' => $contexto['nombre'],
            'moneda_id' => $fila->moneda_id !== null ? (int) $fila->moneda_id : null,
        ];
    }

    /**
     * Cuánto del presupuesto de esa cuenta se lleva un gasto mensual dado.
     *
     * @return array{
     *     presupuesto_anual: float, presupuesto_mensual: float, comprometido_mensual: float,
     *     comprometido_anual: float, pct: float, disponible_mensual: float,
     *     nombre: string, moneda_coincide: bool
     * }|null
     */
    public static function impacto(
        int $empresaId,
        int $centrocostoId,
        int $cuentacontableId,
        float $comprometidoMensual,
        ?int $monedaId = null,
        ?int $anio = null
    ): ?array {
        $presupuesto = self::delCentrocostoYCuenta($empresaId, $centrocostoId, $cuentacontableId, $anio);
        if ($presupuesto === null) {
            return null;
        }

        $comprometidoAnual = $comprometidoMensual * 12;

        return [
            'presupuesto_anual' => $presupuesto['anual'],
            'presupuesto_mensual' => $presupuesto['mensual'],
            'comprometido_mensual' => round($comprometidoMensual, 2),
            'comprometido_anual' => round($comprometidoAnual, 2),
            'pct' => $presupuesto['anual'] > 0
                ? round(($comprometidoAnual / $presupuesto['anual']) * 100, 1)
                : 0.0,
            'disponible_mensual' => round($presupuesto['mensual'] - $comprometidoMensual, 2),
            'nombre' => $presupuesto['presupuesto'],
            // Comparar pesos contra dólares daría un porcentaje sin sentido: se avisa.
            'moneda_coincide' => $monedaId === null
                || $presupuesto['moneda_id'] === null
                || $presupuesto['moneda_id'] === $monedaId,
        ];
    }

    /**
     * Presupuesto y escenario vigentes del año, o null si no hay nada cargado.
     *
     * @return array{presupuesto_id: int, escenario_id: int, nombre: string}|null
     */
    private static function contexto(int $anio): ?array
    {
        if (array_key_exists($anio, self::$contextoPorAnio)) {
            return self::$contextoPorAnio[$anio];
        }

        $presupuesto = Presupuesto::query()
            ->where('anio', $anio)
            ->orderByDesc('id')
            ->first();

        if (! $presupuesto) {
            return self::$contextoPorAnio[$anio] = null;
        }

        $escenarioId = (int) (Presupuesto_Escenario::query()
            ->where('presupuesto_id', (int) $presupuesto->id)
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($escenarioId <= 0) {
            return self::$contextoPorAnio[$anio] = null;
        }

        return self::$contextoPorAnio[$anio] = [
            'presupuesto_id' => (int) $presupuesto->id,
            'escenario_id' => $escenarioId,
            'nombre' => (string) ($presupuesto->nombre ?: 'Presupuesto '.$anio),
        ];
    }

    /** Clase de color según cuánto del presupuesto se está comiendo el recurrente. */
    public static function clasePct(float $pct): string
    {
        return match (true) {
            $pct >= 100 => 'text-danger font-weight-bold',
            $pct >= 80 => 'text-danger',
            $pct >= 50 => 'text-warning',
            default => 'text-muted',
        };
    }
}
