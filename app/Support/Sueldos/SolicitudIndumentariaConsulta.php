<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Solicitud_Prenda_Sueldos;
use Illuminate\Http\Request;

/**
 * Filtros + consulta del reporte de solicitudes de indumentaria.
 */
class SolicitudIndumentariaConsulta
{
    /**
     * @return array{empresa_id:?int, agrupamiento_id:?int, estado:?string, desde:?string, hasta:?string}
     */
    public static function resolverFiltros(Request $request, ?int $empresaDefault = null): array
    {
        return [
            'empresa_id' => $request->filled('empresa_id') ? (int) $request->input('empresa_id') : $empresaDefault,
            'agrupamiento_id' => $request->filled('agrupamiento_id') ? (int) $request->input('agrupamiento_id') : null,
            'estado' => $request->filled('estado') ? (string) $request->input('estado') : null,
            'desde' => $request->filled('desde') ? (string) $request->input('desde') : null,
            'hasta' => $request->filled('hasta') ? (string) $request->input('hasta') : null,
        ];
    }

    /**
     * @param  array{empresa_id:?int, agrupamiento_id:?int, estado:?string, desde:?string, hasta:?string}  $filtros
     */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'empresa_id' => $filtros['empresa_id'] ?? null,
            'agrupamiento_id' => $filtros['agrupamiento_id'] ?? null,
            'estado' => $filtros['estado'] ?? null,
            'desde' => $filtros['desde'] ?? null,
            'hasta' => $filtros['hasta'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array{empresa_id:?int, agrupamiento_id:?int, estado:?string, desde:?string, hasta:?string}  $filtros
     */
    public static function query(array $filtros)
    {
        $q = Solicitud_Prenda_Sueldos::query()
            ->with([
                'empleado:id,legajo,nombre,empresa_id,agrupamiento_id',
                'articulos.prenda:id,codigo,descripcion',
                'articulos.color:id,nombre',
                'articulos.talle:id,nombre',
                'solicitante:id,nombre',
            ])
            ->orderByDesc('fecha')->orderByDesc('id');

        if (! empty($filtros['empresa_id'])) {
            $q->where('empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['agrupamiento_id'])) {
            $q->where('agrupamiento_id', (int) $filtros['agrupamiento_id']);
        }
        if (! empty($filtros['estado'])) {
            $q->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['desde'])) {
            $q->whereDate('fecha', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $q->whereDate('fecha', '<=', $filtros['hasta']);
        }

        return $q;
    }

    /**
     * @param  array{empresa_id:?int, agrupamiento_id:?int, estado:?string, desde:?string, hasta:?string}  $filtros
     */
    public static function coleccion(array $filtros)
    {
        return self::query($filtros)->get();
    }
}
