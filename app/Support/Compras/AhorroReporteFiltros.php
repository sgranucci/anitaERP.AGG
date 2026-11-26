<?php

namespace App\Support\Compras;

use Illuminate\Http\Request;

/**
 * Filtros del reporte de ahorros (OC no suspendidas con ahorro cargado).
 *
 * Empresas: solo las asignadas al usuario; sin selección = todas las asignadas.
 */
final class AhorroReporteFiltros
{
    /**
     * @return array{desde: string, hasta: string, empresa_ids: array<int, int>, comprador_id: int, proveedor_id: int, centrocosto_id: int, usoarticulo_id: int, tipoahorro_id: int, consultar: bool}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $desde = self::fecha($request->input('desde')) ?? date('Y-m-01');
        $hasta = self::fecha($request->input('hasta')) ?? date('Y-m-d');
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'empresa_ids' => collect((array) $request->input('empresa_ids', []))
                ->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all(),
            'comprador_id' => (int) $request->input('comprador_id', 0),
            'proveedor_id' => (int) $request->input('proveedor_id', 0),
            'centrocosto_id' => (int) $request->input('centrocosto_id', 0),
            'usoarticulo_id' => (int) $request->input('usoarticulo_id', 0),
            'tipoahorro_id' => (int) $request->input('tipoahorro_id', 0),
            'consultar' => $request->boolean('consultar'),
        ];
    }

    /** @return array<string, mixed> */
    public static function paraQueryString(array $filtros): array
    {
        return [
            'desde' => $filtros['desde'],
            'hasta' => $filtros['hasta'],
            'empresa_ids' => $filtros['empresa_ids'] ?: null,
            'comprador_id' => $filtros['comprador_id'] ?: null,
            'proveedor_id' => $filtros['proveedor_id'] ?: null,
            'centrocosto_id' => $filtros['centrocosto_id'] ?: null,
            'usoarticulo_id' => $filtros['usoarticulo_id'] ?: null,
            'tipoahorro_id' => $filtros['tipoahorro_id'] ?: null,
            'consultar' => 1,
        ];
    }

    private static function fecha(mixed $valor): ?string
    {
        $valor = trim((string) $valor);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1 ? $valor : null;
    }
}
