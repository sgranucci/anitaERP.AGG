<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Grupo_Concepto_Sueldos;
use App\Models\Sueldos\Grupo_Liq_Final_Sueldos;
use App\Models\Sueldos\Liquidacion_Sueldos;

/**
 * Resuelve el grupo de conceptos a unir en liquidación final
 * según empresa + sindicato del legajo + motivo de egreso.
 */
class GrupoLiqFinalResolver
{
    /**
     * @return array{id: int, codigo: int, descripcion: ?string, orden: int, origen: string, pivot_id: int}|null
     */
    public function resolverParaEmpleado(Empleado_Sueldos $empleado, ?Liquidacion_Sueldos $liquidacion = null): ?array
    {
        if ($liquidacion === null || (string) $liquidacion->tipo !== 'final') {
            return null;
        }

        $empresaId = (int) ($empleado->empresa_id ?? $liquidacion->empresa_id ?? 0);
        $sindicatoId = (int) ($empleado->sindicato_id ?? 0);
        if ($empresaId <= 0 || $sindicatoId <= 0) {
            return null;
        }

        $motivoId = (int) ($empleado->motivoegreso_id ?? 0);
        if ($motivoId <= 0 && $liquidacion->motivoegreso_id) {
            $motivoId = (int) $liquidacion->motivoegreso_id;
        }

        $regla = $this->buscarRegla($empresaId, $sindicatoId, $motivoId > 0 ? $motivoId : null);
        if ($regla === null) {
            return null;
        }

        $grupo = $regla->relationLoaded('grupoConcepto')
            ? $regla->grupoConcepto
            : Grupo_Concepto_Sueldos::query()->find($regla->grupo_concepto_id);

        if ($grupo === null || ! $grupo->activo) {
            return null;
        }

        return [
            'id' => (int) $grupo->id,
            'codigo' => (int) $grupo->codigo,
            'descripcion' => $grupo->descripcion,
            'orden' => 900,
            'origen' => 'liq_final',
            'pivot_id' => 0,
        ];
    }

    private function buscarRegla(int $empresaId, int $sindicatoId, ?int $motivoId): ?Grupo_Liq_Final_Sueldos
    {
        $base = Grupo_Liq_Final_Sueldos::query()
            ->with('grupoConcepto')
            ->where('activo', true)
            ->where('empresa_id', $empresaId)
            ->where('sindicato_id', $sindicatoId);

        if ($motivoId !== null && $motivoId > 0) {
            $especifica = (clone $base)->where('motivoegreso_id', $motivoId)->first();
            if ($especifica) {
                return $especifica;
            }
        }

        return (clone $base)->whereNull('motivoegreso_id')->first();
    }
}
