<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Contrato_Plantilla_Asignacion_Sueldos;
use App\Models\Sueldos\Contrato_Plantilla_Sueldos;

/**
 * Resuelve la plantilla según reglas configurables (tipo + modalidad + sindicato + empresa).
 * Gana la asignación con más criterios coincidentes; empate por prioridad DESC.
 */
final class ContratoPlantillaResolver
{
    public function resolver(
        string $tipo,
        ?string $modalidad = null,
        ?int $sindicatoId = null,
        ?int $empresaId = null,
        ?int $plantillaIdForzada = null,
    ): ?Contrato_Plantilla_Sueldos {
        if ($plantillaIdForzada) {
            return Contrato_Plantilla_Sueldos::query()
                ->whereKey($plantillaIdForzada)
                ->where('activo', true)
                ->first();
        }

        $modalidad = $this->normalizarModalidad($modalidad);

        $candidatas = Contrato_Plantilla_Asignacion_Sueldos::query()
            ->with('plantilla')
            ->where('activo', true)
            ->where('tipo', $tipo)
            ->whereHas('plantilla', fn ($q) => $q->where('activo', true))
            ->orderByDesc('prioridad')
            ->get();

        $mejor = null;
        $mejorScore = -1;
        $mejorPrioridad = -1;

        foreach ($candidatas as $asig) {
            if (! $this->coincideNullable($asig->modalidad, $modalidad, true)) {
                continue;
            }
            if (! $this->coincideNullable($asig->sindicato_id, $sindicatoId)) {
                continue;
            }
            if (! $this->coincideNullable($asig->empresa_id, $empresaId)) {
                continue;
            }

            $score = 0;
            if ($asig->modalidad !== null && trim((string) $asig->modalidad) !== '') {
                $score += 4;
            }
            if ($asig->sindicato_id !== null) {
                $score += 2;
            }
            if ($asig->empresa_id !== null) {
                $score += 1;
            }

            $prio = (int) $asig->prioridad;
            if ($score > $mejorScore || ($score === $mejorScore && $prio > $mejorPrioridad)) {
                $mejor = $asig->plantilla;
                $mejorScore = $score;
                $mejorPrioridad = $prio;
            }
        }

        return $mejor;
    }

    /**
     * null en la regla = comodín. Si la regla tiene valor, debe coincidir.
     */
    private function coincideNullable(mixed $regla, mixed $valor, bool $esModalidad = false): bool
    {
        if ($regla === null || $regla === '') {
            return true;
        }
        if ($valor === null || $valor === '') {
            return false;
        }
        if ($esModalidad) {
            return $this->normalizarModalidad((string) $regla) === $this->normalizarModalidad((string) $valor);
        }

        return (int) $regla === (int) $valor;
    }

    private function normalizarModalidad(?string $modalidad): ?string
    {
        if ($modalidad === null) {
            return null;
        }
        $m = trim($modalidad);
        if ($m === '') {
            return null;
        }
        $norm = ltrim($m, '0');

        return $norm === '' ? '0' : $norm;
    }
}
