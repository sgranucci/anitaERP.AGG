<?php

namespace App\Support\Sueldos;

use Illuminate\Http\Request;

/**
 * Filtros del reporte de planificación / compra sugerida de indumentaria.
 */
class PlanificacionIndumentariaFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?int $empresaDefault = null): array
    {
        $empresaScope = 'una';
        $empresaId = null;
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            $empresaScope = 'todas';
        } elseif ($request->filled('empresa_id')) {
            $empresaId = (int) $request->input('empresa_id');
        } elseif ($empresaDefault !== null && $empresaDefault > 0) {
            $empresaId = $empresaDefault;
        }

        $agrupamientoId = $request->input('agrupamiento_id');
        $agrupamientoId = ($agrupamientoId !== null && $agrupamientoId !== '' && (int) $agrupamientoId > 0) ? (int) $agrupamientoId : null;

        $prendaId = $request->input('prenda_id');
        $prendaId = ($prendaId !== null && $prendaId !== '' && (int) $prendaId > 0) ? (int) $prendaId : null;

        $sexo = strtoupper(trim((string) $request->input('sexo', '')));
        $sexo = in_array($sexo, ['M', 'F'], true) ? $sexo : null;

        return [
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'agrupamiento_id' => $agrupamientoId,
            'prenda_id' => $prendaId,
            'sexo' => $sexo,
            'solo_epp' => $request->boolean('solo_epp'),
            'solo_sugerido' => $request->boolean('solo_sugerido'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            $params['empresa_todas'] = 1;
        } elseif (! empty($filtros['empresa_id'])) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (! empty($filtros['agrupamiento_id'])) {
            $params['agrupamiento_id'] = (int) $filtros['agrupamiento_id'];
        }
        if (! empty($filtros['prenda_id'])) {
            $params['prenda_id'] = (int) $filtros['prenda_id'];
        }
        if (! empty($filtros['sexo'])) {
            $params['sexo'] = $filtros['sexo'];
        }
        if (! empty($filtros['solo_epp'])) {
            $params['solo_epp'] = 1;
        }
        if (! empty($filtros['solo_sugerido'])) {
            $params['solo_sugerido'] = 1;
        }

        return $params;
    }
}
