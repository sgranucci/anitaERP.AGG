<?php

namespace App\Support\Sueldos\Ganancias;

use App\Models\Sueldos\Empleado_Familiar_Sueldos;

/**
 * Agrega familiares del empleado a las claves cantidad() del plan de Ganancias.
 */
class FamiliaresGananciasCantidadesSupport
{
    /**
     * @return array<string, float>  CONYUGE|HIJOS|HIJOS_50|HIJO_INCAP => cantidad
     */
    public static function paraMes(int $empleadoId, int $anio, int $mes): array
    {
        $out = [
            'CONYUGE' => 0.0,
            'HIJOS' => 0.0,
            'HIJOS_50' => 0.0,
            'HIJO_INCAP' => 0.0,
        ];

        $familiares = Empleado_Familiar_Sueldos::query()
            ->where('empleado_id', $empleadoId)
            ->where('activo', true)
            ->get();

        foreach ($familiares as $f) {
            if (! $f->vigenteEnMes($anio, $mes)) {
                continue;
            }
            $tipo = strtoupper((string) $f->tipo);
            // Compat: HIJO + 50% → HIJOS_50
            if ($tipo === 'HIJO' || $tipo === 'HIJOS') {
                $tipo = ((int) $f->porcentaje_deduccion === 50) ? 'HIJOS_50' : 'HIJOS';
            }
            if (! array_key_exists($tipo, $out)) {
                continue;
            }
            $out[$tipo] += 1.0;
        }

        return $out;
    }
}
