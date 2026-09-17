<?php

namespace App\Support\Compras;

use Illuminate\Http\Request;

final class ProgramaPagoListadoFiltros
{
    /**
     * @return array{empresa_id: int, estado: string, valor: string}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $valor = trim((string) ($request->input('filtro_valor') ?? $request->input('busqueda') ?? ''));

        return [
            'empresa_id' => (int) ($request->input('empresa_id') ?? 0),
            'estado' => trim((string) ($request->input('estado') ?? '')),
            'valor' => $valor,
        ];
    }

    /**
     * @param  array{empresa_id?: int, estado?: string, valor?: string}  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if (! empty($filtros['empresa_id'])) {
            $out['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (($filtros['estado'] ?? '') !== '') {
            $out['estado'] = (string) $filtros['estado'];
        }
        if (($filtros['valor'] ?? '') !== '') {
            $out['filtro_valor'] = (string) $filtros['valor'];
        }

        return $out;
    }

    /**
     * @param  array{empresa_id?: int, estado?: string, valor?: string}  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ! empty($filtros['empresa_id'])
            || ($filtros['estado'] ?? '') !== ''
            || ($filtros['valor'] ?? '') !== '';
    }
}
