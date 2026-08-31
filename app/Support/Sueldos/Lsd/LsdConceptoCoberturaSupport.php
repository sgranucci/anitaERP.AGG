<?php

namespace App\Support\Sueldos\Lsd;

use App\Models\Sueldos\Concepto_Sueldos;
use App\Support\Sueldos\ConceptoTipo;
use Illuminate\Support\Facades\Schema;

class LsdConceptoCoberturaSupport
{
    /**
     * @return array{activos: int, exportables: int, mapeados: int, sin_mapeo: int, porcentaje: float, con_bases_04: int}
     */
    public static function resumen(): array
    {
        $q = Concepto_Sueldos::query()->where('activo', true);
        $activos = (int) $q->count();
        $exportables = (int) (clone $q)->whereNotIn('tipo', ConceptoTipo::TIPOS_SIN_IMPACTO_TOTALES)->count();
        $mapeados = (int) (clone $q)
            ->whereNotIn('tipo', ConceptoTipo::TIPOS_SIN_IMPACTO_TOTALES)
            ->whereNotNull('concepto_afip')
            ->where('concepto_afip', '!=', '')
            ->count();
        $sin = max(0, $exportables - $mapeados);
        $pct = $exportables > 0 ? round(($mapeados / $exportables) * 100, 1) : 0.0;
        $conBases = 0;
        if (Schema::hasColumn('concepto_sueldos', 'lsd_bases')) {
            $conBases = Concepto_Sueldos::query()
                ->whereNotNull('lsd_bases')
                ->get(['lsd_bases'])
                ->filter(fn (Concepto_Sueldos $c) => LsdBases04Support::tieneMapeo($c->lsd_bases))
                ->count();
        }

        return [
            'activos' => $activos,
            'exportables' => $exportables,
            'mapeados' => $mapeados,
            'sin_mapeo' => $sin,
            'porcentaje' => $pct,
            'con_bases_04' => $conBases,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Concepto_Sueldos> */
    public static function sinMapeo()
    {
        return Concepto_Sueldos::query()
            ->where('activo', true)
            ->whereNotIn('tipo', ConceptoTipo::TIPOS_SIN_IMPACTO_TOTALES)
            ->where(function ($q) {
                $q->whereNull('concepto_afip')->orWhere('concepto_afip', '');
            })
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descripcion', 'tipo']);
    }
}
