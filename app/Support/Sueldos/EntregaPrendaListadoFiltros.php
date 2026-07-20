<?php

namespace App\Support\Sueldos;

use Illuminate\Http\Request;

/**
 * Filtros del reporte de entregas de indumentaria.
 */
final class EntregaPrendaListadoFiltros
{
    public const CAMPOS = [
        'legajo' => 'Legajo',
        'empleado' => 'Empleado',
        'prenda' => 'Prenda',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busqueda = null): array
    {
        return [
            'anio' => (int) ($request->input('anio') ?: date('Y')),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'agrupamiento_id' => (int) $request->input('agrupamiento_id', 0),
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'texto' => trim((string) ($request->input('filtro_valor') ?? $busqueda ?? '')),
            'consultar' => $request->boolean('consultar') || $request->has('anio') || $busqueda !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $q = [];
        foreach (['anio', 'fecha_desde', 'fecha_hasta', 'agrupamiento_id', 'empresa_id'] as $k) {
            if (! empty($filtros[$k])) {
                $q[$k] = $filtros[$k];
            }
        }
        if (! empty($filtros['texto'])) {
            $q['filtro_valor'] = $filtros['texto'];
        }
        $q['consultar'] = 1;

        return $q;
    }
}
