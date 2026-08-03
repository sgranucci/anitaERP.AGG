<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Ventas\GastronomiaCierresTurnoListadoFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del reporte Contable de cierres de turno gastronomía (solo lectura / conciliación).
 * Contaduría siempre ve todas las terminales.
 */
final class CierreTurnoGastronomiaContableListadoFiltros
{
    public const MODO_TODOS = GastronomiaCierresTurnoListadoFiltros::MODO_TODOS;

    public const MODO_CAMPO = GastronomiaCierresTurnoListadoFiltros::MODO_CAMPO;

    /** @var array<string, array{prop: string, type: string, label: string}> */
    public const CAMPOS = GastronomiaCierresTurnoListadoFiltros::CAMPOS;

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId ?? 0,
                'empresa_scope' => $empresaScope,
            ]);
        }

        $filtros = GastronomiaCierresTurnoListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
        $filtros['empresa_id'] = $empresaId ?? 0;
        $filtros['empresa_scope'] = $empresaScope;
        $filtros['todas_terminales'] = true;
        $filtros['identificador_pc'] = '';

        if (trim((string) ($filtros['fecha_desde'] ?? '')) === ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) === '') {
            $filtros['fecha_desde'] = Carbon::today()->subDays(7)->toDateString();
            $filtros['fecha_hasta'] = Carbon::today()->toDateString();
        }

        return $filtros;
    }

    /**
     * @return array{0:?int,1:string}
     */
    private static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
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
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        $filtros = GastronomiaCierresTurnoListadoFiltros::filtrosVacios();
        $filtros['todas_terminales'] = true;
        $filtros['identificador_pc'] = '';
        $filtros['fecha_desde'] = Carbon::today()->subDays(7)->toDateString();
        $filtros['fecha_hasta'] = Carbon::today()->toDateString();
        $filtros['empresa_scope'] = 'una';

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        $qs = GastronomiaCierresTurnoListadoFiltros::paraQueryString($filtros);
        unset($qs['identificador_pc'], $qs['todas_terminales'], $qs['empresa_id'], $qs['empresa_todas']);

        return array_merge($params, $qs);
    }

    /**
     * @param  array<string, mixed>  $filtros
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

    /**
     * Criterios del panel / búsqueda (sin el filtro externo de empresa).
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosUsuario(array $filtros): bool
    {
        if (trim((string) ($filtros['tipo'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }
        if (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosUsuario($filtros);
    }

    public static function operadoresParaCampo(string $campo): array
    {
        return GastronomiaCierresTurnoListadoFiltros::operadoresParaCampo($campo);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function textoCabeceraExport(array $filtros): string
    {
        $partes = [];
        if (($filtros['empresa_scope'] ?? 'una') === 'todas' || (int) ($filtros['empresa_id'] ?? 0) <= 0) {
            $partes[] = 'Empresa: todas (asignadas)';
        } elseif ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $partes[] = 'Empresa ID '.$filtros['empresa_id'];
        }
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '' || $hasta !== '') {
            $partes[] = 'Fechas: '
                .($desde !== '' ? Carbon::parse($desde)->format('d/m/Y') : '…')
                .' — '
                .($hasta !== '' ? Carbon::parse($hasta)->format('d/m/Y') : '…');
        }
        $tipo = trim((string) ($filtros['tipo'] ?? ''));
        if ($tipo !== '') {
            $partes[] = 'Tipo: '.$tipo;
        }
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $partes[] = 'Texto: '.$valor;
        }

        return $partes === [] ? 'Sin filtros adicionales' : implode(' · ', $partes);
    }
}
