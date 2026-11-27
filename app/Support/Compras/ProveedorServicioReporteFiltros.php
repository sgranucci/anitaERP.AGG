<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use Illuminate\Http\Request;

/**
 * Filtros del reporte de proveedores con clientes de servicios cargados
 * (tabla proveedor_servicio: nro. de cliente / medidor de luz, gas, agua, etc.).
 */
final class ProveedorServicioReporteFiltros
{
    /** @var list<string> */
    public const ESTADOS = [
        Proveedor::ESTADO_ACTIVO,
        Proveedor::ESTADO_SUSPENDIDO,
        Proveedor::ESTADO_ALTA_PENDIENTE,
        Proveedor::ESTADO_REGULARIZADO,
    ];

    /**
     * @return array{empresa_ids: list<int>, proveedores: string, cliente: string, detalle: string, estado: string}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $estado = trim((string) $request->input('estado', ''));
        if (! in_array($estado, self::ESTADOS, true)) {
            $estado = '';
        }

        return [
            'empresa_ids' => $empresaIds,
            'proveedores' => trim((string) $request->input('proveedores', '')),
            'cliente' => trim((string) $request->input('cliente', '')),
            'detalle' => trim((string) $request->input('detalle', '')),
            'estado' => $estado,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $query = array_filter([
            'proveedores' => ($filtros['proveedores'] ?? '') !== '' ? $filtros['proveedores'] : null,
            'cliente' => ($filtros['cliente'] ?? '') !== '' ? $filtros['cliente'] : null,
            'detalle' => ($filtros['detalle'] ?? '') !== '' ? $filtros['detalle'] : null,
            'estado' => ($filtros['estado'] ?? '') !== '' ? $filtros['estado'] : null,
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = array_values(array_map('intval', $filtros['empresa_ids']));
        }

        return $query;
    }

    /**
     * Sin empresas tildadas no hay consulta (las empresas son obligatorias en reportes multiempresa).
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['empresa_ids'] ?? []) !== [];
    }
}
