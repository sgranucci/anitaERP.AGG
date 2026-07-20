<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Plan_Cuota_Movimiento_Sueldos;
use App\Models\Sueldos\Empleado_Plan_Cuota_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\Formula\ContextoLiquidacion;
use App\Support\Sueldos\Formula\EvaluadorFormula;
use App\Support\Sueldos\Formula\FormulaException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Planes de cuotas (préstamos/anticipos/embargos): un concepto que se liquida
 * N veces y cae automáticamente al completarse.
 *
 * - lineasPlan(): cuotas a liquidar para un empleado en la corrida (no persiste).
 * - reemplazarPendientes(): registra las cuotas de la corrida como pendientes.
 * - confirmar(): al cerrar la corrida, avanza el contador y finaliza planes.
 * - revertir(): al reabrir/anular, retrocede el contador y limpia movimientos.
 */
class PlanCuotaLiquidacionService
{
    private EvaluadorFormula $motor;

    public function __construct()
    {
        $this->motor = new EvaluadorFormula;
    }

    /**
     * Cuotas a liquidar para el empleado en esta corrida. No persiste.
     *
     * @return list<array{
     *   plan_id: int, numero_cuota: int, cuotas_totales: int, periodo: int,
     *   concepto_id: int, codigo: int, descripcion: string, tipo: string,
     *   importe: float, va_recibo: bool, concepto_afip: ?string, leyenda: string
     * }>
     */
    public function lineasPlan(Empleado_Sueldos $emp, Liquidacion_Sueldos $liq, ContextoLiquidacion $ctx): array
    {
        $anio = (int) ($liq->periodo_anio ?: now()->year);
        $mes = (int) ($liq->periodo_mes ?: now()->month);
        $periodo = $anio * 100 + $mes;
        $tipoCorrida = (string) ($liq->tipo ?: 'mensual');

        $planes = Empleado_Plan_Cuota_Sueldos::query()
            ->with('concepto')
            ->where('empleado_id', $emp->id)
            ->where('estado', Empleado_Plan_Cuota_Sueldos::ESTADO_ACTIVA)
            ->orderBy('id')
            ->get();

        $lineas = [];
        foreach ($planes as $plan) {
            if (! $plan->aplicaEn($periodo, $tipoCorrida)) {
                continue;
            }
            $concepto = $plan->concepto;
            if ($concepto === null) {
                continue;
            }

            $importe = $this->importeCuota($plan, $ctx);
            $importe = round($importe, 2);
            if ($importe == 0.0) {
                continue;
            }

            $numero = (int) $plan->cuotas_liquidadas + 1;
            $total = (int) $plan->cuotas_totales;

            $lineas[] = [
                'plan_id' => (int) $plan->id,
                'numero_cuota' => $numero,
                'cuotas_totales' => $total,
                'periodo' => $periodo,
                'concepto_id' => (int) $concepto->id,
                'codigo' => (int) $concepto->codigo,
                'descripcion' => (string) $concepto->descripcion,
                'tipo' => (string) $concepto->tipo,
                'importe' => $importe,
                'va_recibo' => (bool) $concepto->va_recibo,
                'concepto_afip' => $concepto->concepto_afip,
                'leyenda' => trim(($plan->descripcion ? $plan->descripcion.' — ' : '')."Cuota {$numero}/{$total}"),
            ];
        }

        return $lineas;
    }

    private function importeCuota(Empleado_Plan_Cuota_Sueldos $plan, ContextoLiquidacion $ctx): float
    {
        if ($plan->tipo_valor === Empleado_Plan_Cuota_Sueldos::TIPO_FORMULA && trim((string) $plan->cuota_formula) !== '') {
            try {
                return (float) $this->motor->evaluar((string) $plan->cuota_formula, $ctx);
            } catch (FormulaException $e) {
                throw FormulaException::evaluacion("Plan de cuotas #{$plan->id} ({$plan->descripcion}): ".$e->getMessage());
            }
        }

        return (float) $plan->cuota_valor;
    }

    /**
     * Reemplaza los movimientos PENDIENTES de la corrida por los recién calculados.
     *
     * @param  list<array{plan_id:int,numero_cuota:int,periodo:int,importe:float,empleado_id:int}>  $pendientes
     */
    public function reemplazarPendientes(int $liquidacionId, array $pendientes): void
    {
        // Solo se tocan los pendientes; los confirmados (de corridas cerradas) no.
        Empleado_Plan_Cuota_Movimiento_Sueldos::query()
            ->where('liquidacion_id', $liquidacionId)
            ->where('estado', Empleado_Plan_Cuota_Movimiento_Sueldos::ESTADO_PENDIENTE)
            ->delete();

        if ($pendientes === []) {
            return;
        }

        $ahora = Carbon::now();
        $filas = [];
        foreach ($pendientes as $p) {
            $filas[] = [
                'plan_id' => $p['plan_id'],
                'liquidacion_id' => $liquidacionId,
                'empleado_id' => $p['empleado_id'],
                'periodo' => $p['periodo'],
                'numero_cuota' => $p['numero_cuota'],
                'importe' => round((float) $p['importe'], 2),
                'fecha' => $ahora->toDateString(),
                'estado' => Empleado_Plan_Cuota_Movimiento_Sueldos::ESTADO_PENDIENTE,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }
        foreach (array_chunk($filas, 500) as $lote) {
            Empleado_Plan_Cuota_Movimiento_Sueldos::insert($lote);
        }
    }

    /**
     * Al cerrar la corrida: confirma las cuotas y avanza el contador de cada plan.
     * Finaliza el plan cuando llega al total.
     */
    public function confirmar(Liquidacion_Sueldos $liq): void
    {
        DB::transaction(function () use ($liq) {
            $movs = Empleado_Plan_Cuota_Movimiento_Sueldos::query()
                ->where('liquidacion_id', $liq->id)
                ->where('estado', Empleado_Plan_Cuota_Movimiento_Sueldos::ESTADO_PENDIENTE)
                ->get();

            foreach ($movs as $mov) {
                $plan = Empleado_Plan_Cuota_Sueldos::find($mov->plan_id);
                if ($plan === null) {
                    continue;
                }
                $plan->cuotas_liquidadas = (int) $plan->cuotas_liquidadas + 1;
                if ($plan->cuotas_liquidadas >= (int) $plan->cuotas_totales) {
                    $plan->cuotas_liquidadas = (int) $plan->cuotas_totales;
                    $plan->estado = Empleado_Plan_Cuota_Sueldos::ESTADO_FINALIZADA;
                }
                $plan->save();

                $mov->estado = Empleado_Plan_Cuota_Movimiento_Sueldos::ESTADO_CONFIRMADO;
                $mov->save();
            }
        });
    }

    /**
     * Al reabrir/anular la corrida: retrocede el contador de las cuotas confirmadas
     * y elimina los movimientos (pendientes y confirmados) de la corrida.
     */
    public function revertir(Liquidacion_Sueldos $liq): void
    {
        DB::transaction(function () use ($liq) {
            $movs = Empleado_Plan_Cuota_Movimiento_Sueldos::query()
                ->where('liquidacion_id', $liq->id)
                ->get();

            foreach ($movs as $mov) {
                if ($mov->estado === Empleado_Plan_Cuota_Movimiento_Sueldos::ESTADO_CONFIRMADO) {
                    $plan = Empleado_Plan_Cuota_Sueldos::find($mov->plan_id);
                    if ($plan !== null) {
                        $plan->cuotas_liquidadas = max(0, (int) $plan->cuotas_liquidadas - 1);
                        if ($plan->estado === Empleado_Plan_Cuota_Sueldos::ESTADO_FINALIZADA
                            && $plan->cuotas_liquidadas < (int) $plan->cuotas_totales) {
                            $plan->estado = Empleado_Plan_Cuota_Sueldos::ESTADO_ACTIVA;
                        }
                        $plan->save();
                    }
                }
            }

            Empleado_Plan_Cuota_Movimiento_Sueldos::query()
                ->where('liquidacion_id', $liq->id)
                ->delete();
        });
    }
}
