<?php

namespace App\Support\Contable;

use Illuminate\Http\Request;

final class DiferenciaCambioFacturaListadoFiltros
{
    /**
     * @return array{empresa_id: int, estado: string, fecha_desde: string, fecha_hasta: string, consultar: int}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $estado = trim((string) $request->input('estado', ''));
        if (! in_array($estado, ['', 'pendiente_aprobacion', 'contabilizado', 'rechazada', 'aprobada'], true)) {
            $estado = '';
        }

        return [
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'estado' => $estado,
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'consultar' => $request->boolean('consultar') ? 1 : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'consultar' => 1,
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0) > 0 ? (int) $filtros['empresa_id'] : null,
            'estado' => ($filtros['estado'] ?? '') !== '' ? $filtros['estado'] : null,
            'fecha_desde' => ($filtros['fecha_desde'] ?? '') !== '' ? $filtros['fecha_desde'] : null,
            'fecha_hasta' => ($filtros['fecha_hasta'] ?? '') !== '' ? $filtros['fecha_hasta'] : null,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            || ($filtros['estado'] ?? '') !== ''
            || ($filtros['fecha_desde'] ?? '') !== ''
            || ($filtros['fecha_hasta'] ?? '') !== '';
    }
}
