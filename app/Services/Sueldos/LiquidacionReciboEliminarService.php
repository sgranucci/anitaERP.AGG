<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Plan_Cuota_Movimiento_Sueldos;
use App\Models\Sueldos\Liquidacion_Acumulador_Sueldos;
use App\Models\Sueldos\Liquidacion_Detalle_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Sueldos\LiquidacionDetalleTotalesSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quita un recibo de la corrida sin recalcular al resto de la nómina.
 * Deja el legajo fuera del filtro de empleados para que un Recalcular posterior
 * no lo vuelva a incluir.
 */
class LiquidacionReciboEliminarService
{
    /**
     * @param  list<int>  $empleadoIdsRestantes
     * @return array<string, mixed>
     */
    public static function armarFiltrosTrasQuitar(?array $filtrosActuales, array $empleadoIdsRestantes): array
    {
        $filtros = is_array($filtrosActuales) ? $filtrosActuales : [];
        $filtros['empleado_ids'] = array_values(array_unique(array_filter(
            array_map('intval', $empleadoIdsRestantes),
            static fn (int $id) => $id > 0
        )));

        return $filtros;
    }

    public function eliminar(Liquidacion_Sueldos $liquidacion, Liquidacion_Recibo_Sueldos $recibo): array
    {
        if ((int) $recibo->liquidacion_id !== (int) $liquidacion->id) {
            throw new \InvalidArgumentException('El recibo no pertenece a esta corrida.');
        }
        if (! $liquidacion->esEditable()) {
            throw new \RuntimeException(
                'Solo se puede quitar un empleado en corridas borrador, calculadas o revisadas.'
            );
        }

        $empleadoId = (int) $recibo->empleado_id;
        $legajo = (int) ($recibo->legajo ?? 0);
        $nombre = (string) ($recibo->apellido_nombre ?? '');

        return DB::transaction(function () use ($liquidacion, $recibo, $empleadoId, $legajo, $nombre) {
            EloquentAuditDeleteSupport::each(
                Liquidacion_Detalle_Sueldos::query()->where('recibo_id', $recibo->id)
            );
            EloquentAuditDeleteSupport::each(
                Liquidacion_Recibo_Sueldos::query()->where('id', $recibo->id)
            );

            Liquidacion_Acumulador_Sueldos::query()
                ->where('liquidacion_id', $liquidacion->id)
                ->where('empleado_id', $empleadoId)
                ->delete();

            if (Schema::hasTable('empleado_plan_cuota_movimiento_sueldos')) {
                Empleado_Plan_Cuota_Movimiento_Sueldos::query()
                    ->where('liquidacion_id', $liquidacion->id)
                    ->where('empleado_id', $empleadoId)
                    ->where('estado', Empleado_Plan_Cuota_Movimiento_Sueldos::ESTADO_PENDIENTE)
                    ->delete();
            }

            $restantes = Liquidacion_Recibo_Sueldos::query()
                ->where('liquidacion_id', $liquidacion->id)
                ->orderBy('empleado_id')
                ->pluck('empleado_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $filtros = self::armarFiltrosTrasQuitar(
                is_array($liquidacion->filtros_json) ? $liquidacion->filtros_json : null,
                $restantes
            );
            $liquidacion->update(['filtros_json' => $filtros]);

            LiquidacionDetalleTotalesSupport::renumerarRecibos((int) $liquidacion->id);
            LiquidacionDetalleTotalesSupport::recalcularCabecera($liquidacion->fresh());

            return [
                'empleado_id' => $empleadoId,
                'legajo' => $legajo,
                'nombre' => $nombre,
                'recibos_restantes' => count($restantes),
            ];
        });
    }
}
