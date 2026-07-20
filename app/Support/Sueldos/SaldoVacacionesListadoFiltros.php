<?php

namespace App\Support\Sueldos;

use Illuminate\Http\Request;

/**
 * Filtros del reporte de saldos de vacaciones.
 *
 * Externos: empresa (default primera asignada, empresa_scope=todas), estado (default Activo),
 * año de período (opcional), texto (legajo/nombre) y solo con saldo positivo.
 */
class SaldoVacacionesListadoFiltros
{
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        $estadoInput = strtoupper(trim((string) $request->input('filtro_estado', '')));
        if ($estadoInput === 'TODOS') {
            $estado = 'TODOS';
        } elseif (in_array($estadoInput, [EmpleadoEstados::PROVISORIO, EmpleadoEstados::ACTIVO, EmpleadoEstados::BAJA], true)) {
            $estado = $estadoInput;
        } else {
            $estado = EmpleadoEstados::ACTIVO;
        }

        $empresaScope = 'una';
        $empresaId = null;
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            $empresaScope = 'todas';
        } elseif ($request->filled('empresa_id')) {
            $empresaId = (int) $request->input('empresa_id');
        } elseif ($empresaDefault !== null && $empresaDefault > 0) {
            $empresaId = $empresaDefault;
        }

        $anio = $request->input('anio');
        $anio = ($anio !== null && $anio !== '' && (int) $anio > 0) ? (int) $anio : null;

        $valor = trim((string) ($request->input('filtro_valor', $busquedaRuta ?? '')));

        return [
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'estado' => $estado,
            'anio' => $anio,
            'valor' => $valor,
            'solo_con_saldo' => $request->boolean('solo_con_saldo'),
        ];
    }

    /**
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
        $estado = $filtros['estado'] ?? EmpleadoEstados::ACTIVO;
        if ($estado === 'TODOS') {
            $params['filtro_estado'] = 'TODOS';
        } elseif ($estado !== EmpleadoEstados::ACTIVO) {
            $params['filtro_estado'] = $estado;
        }
        if (! empty($filtros['anio'])) {
            $params['anio'] = (int) $filtros['anio'];
        }
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if (! empty($filtros['solo_con_saldo'])) {
            $params['solo_con_saldo'] = 1;
        }

        return $params;
    }
}
