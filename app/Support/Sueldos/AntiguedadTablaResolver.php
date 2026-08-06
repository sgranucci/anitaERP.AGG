<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Antiguedad_Tabla_Sueldos;
use Illuminate\Support\Facades\Cache;

/**
 * Resolución de ANT(tabla): suma de porcentajes de tramos con anio <= años.
 */
class AntiguedadTablaResolver
{
    /**
     * % acumulado de antigüedad para la tabla y años dados (espejo Anita ANT).
     */
    public static function porcentaje(int $codigoTabla, float $anios, ?int $empresaId = null): float
    {
        if ($codigoTabla <= 0 || $anios < 0) {
            return 0.0;
        }

        $aniosEnteros = (int) floor($anios);
        $cacheKey = 'sueldos.antiguedad_tabla.'.$codigoTabla.'.'.($empresaId ?? 0);

        $tramos = Cache::remember($cacheKey, 300, function () use ($codigoTabla, $empresaId) {
            $q = Antiguedad_Tabla_Sueldos::query()
                ->where('codigo', $codigoTabla)
                ->where('activo', true)
                ->with(['tramos' => fn ($t) => $t->orderBy('anio')]);

            if ($empresaId) {
                $q->where(function ($w) use ($empresaId) {
                    $w->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
                })->orderByRaw('empresa_id is null'); // preferir específica de empresa
            } else {
                $q->whereNull('empresa_id');
            }

            $tabla = $q->first();
            if ($tabla === null && $empresaId) {
                $tabla = Antiguedad_Tabla_Sueldos::query()
                    ->where('codigo', $codigoTabla)
                    ->where('activo', true)
                    ->whereNull('empresa_id')
                    ->with(['tramos' => fn ($t) => $t->orderBy('anio')])
                    ->first();
            }

            if ($tabla === null) {
                return [];
            }

            return $tabla->tramos->map(fn ($t) => [
                'anio' => (int) $t->anio,
                'porcentaje' => (float) $t->porcentaje,
                'cantidad' => (float) $t->cantidad,
            ])->all();
        });

        $suma = 0.0;
        foreach ($tramos as $t) {
            if ($t['anio'] <= $aniosEnteros) {
                $suma += $t['porcentaje'];
            }
        }

        return $suma;
    }

    public static function forgetCache(?int $codigoTabla = null): void
    {
        if ($codigoTabla !== null) {
            Cache::forget('sueldos.antiguedad_tabla.'.$codigoTabla.'.0');

            return;
        }
        // Sin flush global de tags: los TTL de 5 min bastan tras ABM.
    }
}
