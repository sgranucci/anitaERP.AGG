<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Ausencia_Sueldos;
use App\Models\Sueldos\Empleado_Cuota_Movimiento_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Vacacion_Periodo_Sueldos;
use App\Support\Sueldos\VacacionEscalaAntiguedad;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Motor de cuota de vacaciones (ledger de dias).
 *
 *  - Devengamiento automatico por antiguedad (LCT) por cada periodo trabajado.
 *  - Consumo desde los eventos reales (empleado_ausencia_sueldos) que afectan saldo.
 *  - Saldo por periodo = devengado + ajustes - consumido (suma firmada del mayor).
 *
 * Reemplaza el circuito vacempl (teorico) + vacreal + vacliq (real) de Anita:
 * vacliq (lo liquidado) se representa como eventos en estado 'liquidada'.
 */
class DevengamientoVacacionesService
{
    /**
     * Recalcula todo el ledger de cuota del empleado (devengado + consumos) de forma idempotente.
     *
     * @return array{devengado: float, consumido: float, saldo: float, periodos: array<int, array<string, float>>}
     */
    public function recalcularEmpleado(Empleado_Sueldos $empleado, ?int $usuarioId = null): array
    {
        return DB::transaction(function () use ($empleado, $usuarioId) {
            $this->devengar($empleado, $usuarioId);
            $this->regenerarConsumos($empleado, $usuarioId);

            return $this->resumen($empleado);
        });
    }

    /**
     * Genera / actualiza los creditos de devengamiento por antiguedad, un movimiento por periodo.
     */
    public function devengar(Empleado_Sueldos $empleado, ?int $usuarioId = null): void
    {
        if (empty($empleado->fecha_ingreso)) {
            return;
        }

        $fechaIngreso = Carbon::parse($empleado->fecha_ingreso)->startOfDay();
        $fechaEgreso = ! empty($empleado->fecha_egreso) ? Carbon::parse($empleado->fecha_egreso)->endOfDay() : null;
        $antiguedadAnterior = (string) ($empleado->antiguedad_anterior ?? '');
        $escala = $this->escalaDeTablaEmpleado($empleado);

        $anioDesde = (int) $fechaIngreso->year;
        $anioHasta = (int) ($fechaEgreso !== null ? $fechaEgreso->year : Carbon::now()->year);

        Empleado_Cuota_Movimiento_Sueldos::withoutAuditing(function () use (
            $empleado, $usuarioId, $fechaIngreso, $fechaEgreso, $antiguedadAnterior, $escala, $anioDesde, $anioHasta
        ) {
            // Idempotente: borra devengamientos previos y regenera.
            Empleado_Cuota_Movimiento_Sueldos::query()
                ->where('empleado_id', $empleado->id)
                ->where('origen', 'devengamiento')
                ->delete();

            for ($anio = $anioDesde; $anio <= $anioHasta; $anio++) {
                $calc = VacacionEscalaAntiguedad::devengadoPeriodo($fechaIngreso, $anio, $antiguedadAnterior, $fechaEgreso, $escala);
                if ($calc['dias'] <= 0) {
                    continue;
                }

                $detalle = $calc['proporcional']
                    ? sprintf('Devengado proporcional %d (%d días trabajados)', $anio, $calc['dias_trabajados'])
                    : sprintf('Devengado %d por antigüedad (%d días de escala)', $anio, $calc['dias_escala']);

                Empleado_Cuota_Movimiento_Sueldos::create([
                    'empleado_id' => $empleado->id,
                    'anio_periodo' => $anio,
                    'origen' => 'devengamiento',
                    'fecha' => Carbon::create($anio, 12, 31)->toDateString(),
                    'dias' => $calc['dias'],
                    'ausencia_id' => null,
                    'descripcion' => $detalle,
                    'usuario_id' => $usuarioId,
                ]);
            }
        });
    }

    /**
     * Escala de dias de la tabla de vacaciones asignada al empleado (`vacacion_id`).
     * Null cuando no tiene tabla o la tabla no esta completa (sin rangos de antiguedad
     * o sin dias): en ese caso rige la escala de la ley (LCT).
     *
     * @return list<array{anios_desde:int, dias:int}>|null
     */
    public function escalaDeTablaEmpleado(Empleado_Sueldos $empleado): ?array
    {
        if (empty($empleado->vacacion_id)) {
            return null;
        }
        static $tieneColumna = null;
        $tieneColumna ??= Schema::hasColumn('vacacion_periodo_sueldos', 'anios_desde');
        if (! $tieneColumna) {
            return null;
        }

        $lineas = Vacacion_Periodo_Sueldos::query()
            ->where('vacacion_id', $empleado->vacacion_id)
            ->orderBy('nro_linea')
            ->get(['nro_linea', 'anios_desde', 'tipo_dia', 'cantidad_dias']);

        $escala = [];
        foreach ($lineas as $linea) {
            if ((int) $linea->cantidad_dias <= 0) {
                continue;
            }
            if ($linea->anios_desde === null) {
                // Tabla a medias (migrada sin rangos): no se puede confiar en ella.
                return null;
            }
            $escala[] = ['anios_desde' => (int) $linea->anios_desde, 'dias' => (int) $linea->cantidad_dias];
        }

        return $escala === [] ? null : $escala;
    }

    /**
     * Regenera los debitos de consumo desde los eventos reales que afectan saldo de vacaciones.
     */
    public function regenerarConsumos(Empleado_Sueldos $empleado, ?int $usuarioId = null): void
    {
        Empleado_Cuota_Movimiento_Sueldos::withoutAuditing(function () use ($empleado, $usuarioId) {
            Empleado_Cuota_Movimiento_Sueldos::query()
                ->where('empleado_id', $empleado->id)
                ->where('origen', 'consumo')
                ->delete();

            $ausencias = Empleado_Ausencia_Sueldos::query()
                ->with('tipo')
                ->where('empleado_id', $empleado->id)
                ->whereIn('estado', Empleado_Ausencia_Sueldos::ESTADOS_CONSUMEN)
                ->orderBy('fecha_desde')
                ->get();

            foreach ($ausencias as $ausencia) {
                $tipo = $ausencia->tipo;
                if ($tipo === null || ! $tipo->esVacaciones()) {
                    continue;
                }
                $dias = (float) $ausencia->dias;
                if ($dias <= 0) {
                    continue;
                }

                $anio = (int) ($ausencia->anio_imputacion ?: Carbon::parse($ausencia->fecha_desde)->year);

                Empleado_Cuota_Movimiento_Sueldos::create([
                    'empleado_id' => $empleado->id,
                    'anio_periodo' => $anio,
                    'origen' => 'consumo',
                    'fecha' => Carbon::parse($ausencia->fecha_desde)->toDateString(),
                    'dias' => -1 * abs($dias),
                    'ausencia_id' => $ausencia->id,
                    'descripcion' => sprintf(
                        '%s %s a %s (%s)',
                        $tipo->nombre,
                        Carbon::parse($ausencia->fecha_desde)->format('d/m/Y'),
                        Carbon::parse($ausencia->fecha_hasta)->format('d/m/Y'),
                        Empleado_Ausencia_Sueldos::ESTADOS[$ausencia->estado] ?? $ausencia->estado
                    ),
                    'usuario_id' => $usuarioId,
                ]);
            }
        });
    }

    /**
     * Saldos por periodo y totales.
     *
     * @return array{devengado: float, consumido: float, saldo: float, periodos: array<int, array<string, float>>}
     */
    public function resumen(Empleado_Sueldos $empleado): array
    {
        $movimientos = Empleado_Cuota_Movimiento_Sueldos::query()
            ->where('empleado_id', $empleado->id)
            ->get();

        $periodos = [];
        $devengado = 0.0;
        $consumido = 0.0;

        foreach ($movimientos as $mov) {
            $anio = (int) $mov->anio_periodo;
            $dias = (float) $mov->dias;
            if (! isset($periodos[$anio])) {
                $periodos[$anio] = ['anio' => $anio, 'devengado' => 0.0, 'consumido' => 0.0, 'saldo' => 0.0];
            }
            if ($dias >= 0) {
                $periodos[$anio]['devengado'] += $dias;
                $devengado += $dias;
            } else {
                $periodos[$anio]['consumido'] += abs($dias);
                $consumido += abs($dias);
            }
            $periodos[$anio]['saldo'] += $dias;
        }

        ksort($periodos);

        return [
            'devengado' => round($devengado, 2),
            'consumido' => round($consumido, 2),
            'saldo' => round($devengado - $consumido, 2),
            'periodos' => array_values($periodos),
        ];
    }

    /**
     * Cuenta dias de un rango segun modo (corridos o habiles lunes-viernes).
     */
    public static function contarDias(Carbon $desde, Carbon $hasta, string $tipoDias = 'corridos'): int
    {
        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();
        if ($hasta->lessThan($desde)) {
            return 0;
        }

        if ($tipoDias !== 'habiles') {
            return $desde->diffInDays($hasta) + 1;
        }

        $dias = 0;
        $cursor = $desde->copy();
        while ($cursor->lessThanOrEqualTo($hasta)) {
            if (! $cursor->isWeekend()) {
                $dias++;
            }
            $cursor->addDay();
        }

        return $dias;
    }
}
