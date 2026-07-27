<?php

namespace App\Support\Compras;

use App\Models\Compras\Pagoproveedor;
use Illuminate\Http\Request;

/**
 * Filtros del listado de pagos / retenciones del portal de proveedores.
 */
final class PortalProveedorPagosListadoFiltros
{
    /**
     * Estados visibles para el proveedor (sin borradores internos).
     *
     * @return list<string>
     */
    public static function estadosVisiblesPortal(): array
    {
        return ['CONFIRMADA', 'REVERTIDA', 'BAJA'];
    }

    /**
     * @return array{
     *   proveedor_id: int,
     *   empresa_id: int|null,
     *   estado: string,
     *   numero: string,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   tiporetencion: string,
     *   consultar: bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $estado = trim((string) $request->input('estado', ''));
        $estadosOk = array_column(Pagoproveedor::$enumEstado, 'valor');
        if ($estado !== '' && ! in_array($estado, $estadosOk, true)) {
            $estado = '';
        }
        if ($estado === 'PRE CARGA') {
            $estado = '';
        }

        $tipoRet = strtoupper(trim((string) $request->input('tiporetencion', '')));
        if ($tipoRet !== '' && ! in_array($tipoRet, ['G', 'I', 'S', 'B'], true)) {
            $tipoRet = '';
        }

        $empresaId = (int) $request->input('empresa_id', 0);

        return [
            'proveedor_id' => (int) $request->input('proveedor_id', 0),
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'estado' => $estado,
            'numero' => trim((string) $request->input('numero', '')),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'tiporetencion' => $tipoRet,
            'consultar' => $request->boolean('consultar') || $request->has('proveedor_id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach (['proveedor_id', 'empresa_id', 'estado', 'numero', 'fecha_desde', 'fecha_hasta', 'tiporetencion'] as $k) {
            $v = $filtros[$k] ?? null;
            if ($v !== null && $v !== '' && $v !== 0) {
                $out[$k] = $v;
            }
        }
        if (! empty($filtros['consultar'])) {
            $out['consultar'] = 1;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        foreach (['empresa_id', 'estado', 'numero', 'fecha_desde', 'fecha_hasta', 'tiporetencion'] as $k) {
            $v = $filtros[$k] ?? null;
            if ($v !== null && $v !== '' && $v !== 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Subtítulo legible para PDF/Excel.
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function subtituloFiltros(array $filtros): string
    {
        $partes = [];
        if (! empty($filtros['fecha_desde'])) {
            $partes[] = 'Desde '.$filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $partes[] = 'Hasta '.$filtros['fecha_hasta'];
        }
        if (! empty($filtros['estado'])) {
            $partes[] = 'Estado '.$filtros['estado'];
        }
        if (! empty($filtros['numero'])) {
            $partes[] = 'Nº '.$filtros['numero'];
        }
        if (! empty($filtros['tiporetencion'])) {
            $etiquetas = ['G' => 'Ganancias', 'I' => 'IVA', 'S' => 'SUSS', 'B' => 'IIBB'];
            $partes[] = 'Retención '.($etiquetas[$filtros['tiporetencion']] ?? $filtros['tiporetencion']);
        }

        return $partes === [] ? 'Sin filtros adicionales' : implode(' · ', $partes);
    }
}
