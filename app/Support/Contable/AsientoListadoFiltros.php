<?php

namespace App\Support\Contable;

use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Http\Request;

/**
 * Filtros del listado de asientos contables (index).
 * Incluye filtro externo de empresa; la búsqueda de texto sigue siendo legacy (`busqueda`).
 */
class AsientoListadoFiltros
{
    /**
     * @return array{busqueda: string, empresa_id: ?int, empresa_scope: string}
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        // Compat: el index legacy usa name="busqueda".
        if ($valor === '' && $request->has('busqueda')) {
            $valor = trim((string) $request->input('busqueda', ''));
        }
        if ($valor === '' && is_string($busquedaRuta) && $busquedaRuta !== '') {
            $valor = trim($busquedaRuta);
        }

        return [
            'busqueda' => $valor,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
        ];
    }

    /**
     * Filtro externo del index: empresa (default primera asignada) o todas (`empresa_todas=1`).
     *
     * @return array{0:?int,1:string}  [empresa_id, empresa_scope]
     */
    public static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
    {
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->filled('empresa_id')) {
            return [(int) $request->input('empresa_id'), 'una'];
        }
        if ($empresaDefault !== null && $empresaDefault > 0) {
            return [$empresaDefault, 'una'];
        }

        return [null, 'todas'];
    }

    /**
     * Normaliza filtros guardados en sesión (formato legacy o actual).
     *
     * @param  array<string, mixed>  $session
     * @return array{busqueda: string, empresa_id: ?int, empresa_scope: string}
     */
    public static function desdeSesion(array $session, ?int $empresaDefault = null): array
    {
        $busqueda = trim((string) ($session['busqueda'] ?? ''));

        if (isset($session['empresa_scope'])) {
            $scope = (string) $session['empresa_scope'];
            if ($scope === 'todas') {
                return [
                    'busqueda' => $busqueda,
                    'empresa_id' => null,
                    'empresa_scope' => 'todas',
                ];
            }
            $empresaId = (int) ($session['empresa_id'] ?? 0);

            return [
                'busqueda' => $busqueda,
                'empresa_id' => $empresaId > 0 ? $empresaId : $empresaDefault,
                'empresa_scope' => 'una',
            ];
        }

        // Legacy: empresa_id vacío = todas las empresas asignadas.
        $empresaId = (int) ($session['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            return [
                'busqueda' => $busqueda,
                'empresa_id' => $empresaId,
                'empresa_scope' => 'una',
            ];
        }

        return [
            'busqueda' => $busqueda,
            'empresa_id' => null,
            'empresa_scope' => 'todas',
        ];
    }

    /**
     * @return array{busqueda: string, empresa_id: ?int, empresa_scope: string}
     */
    public static function filtrosVacios(?int $empresaDefault = null): array
    {
        if ($empresaDefault !== null && $empresaDefault > 0) {
            return [
                'busqueda' => '',
                'empresa_id' => $empresaDefault,
                'empresa_scope' => 'una',
            ];
        }

        return [
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'todas',
        ];
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);
        $busqueda = trim((string) ($filtros['busqueda'] ?? ''));
        if ($busqueda !== '') {
            $params['busqueda'] = $busqueda;
        }

        return $params;
    }

    /**
     * Solo el filtro externo de empresa.
     *
     * @return array<string, int>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return ['empresa_todas' => 1];
        }
        if (! empty($filtros['empresa_id'])) {
            return ['empresa_id' => (int) $filtros['empresa_id']];
        }

        return [];
    }
}
