<?php

namespace App\Support\Sueldos;

use Illuminate\Http\Request;

class SancionReporteListadoFiltros
{
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));
        if ($fechaDesde === '') {
            $fechaDesde = date('Y-01-01');
        }
        if ($fechaHasta === '') {
            $fechaHasta = date('Y-m-d');
        }

        return [
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'tipo_sancion_id' => (int) $request->input('tipo_sancion_id', 0) ?: null,
            'motivo_sancion_id' => (int) $request->input('motivo_sancion_id', 0) ?: null,
            'estado' => trim((string) $request->input('estado', '')),
            'legajo_desde' => trim((string) $request->input('legajo_desde', '')),
            'legajo_hasta' => trim((string) $request->input('legajo_hasta', '')),
            'centrocosto_id' => (int) $request->input('centrocosto_id', 0) ?: null,
            'incluir_comentario' => $request->boolean('incluir_comentario'),
        ];
    }

    public static function paraQueryString(array $filtros, bool $consultar = false): array
    {
        $params = [
            'fecha_desde' => $filtros['fecha_desde'] ?? '',
            'fecha_hasta' => $filtros['fecha_hasta'] ?? '',
        ];
        if (! empty($filtros['empresa_id'])) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (! empty($filtros['tipo_sancion_id'])) {
            $params['tipo_sancion_id'] = (int) $filtros['tipo_sancion_id'];
        }
        if (! empty($filtros['motivo_sancion_id'])) {
            $params['motivo_sancion_id'] = (int) $filtros['motivo_sancion_id'];
        }
        if (($filtros['estado'] ?? '') !== '') {
            $params['estado'] = $filtros['estado'];
        }
        if (($filtros['legajo_desde'] ?? '') !== '') {
            $params['legajo_desde'] = $filtros['legajo_desde'];
        }
        if (($filtros['legajo_hasta'] ?? '') !== '') {
            $params['legajo_hasta'] = $filtros['legajo_hasta'];
        }
        if (! empty($filtros['centrocosto_id'])) {
            $params['centrocosto_id'] = (int) $filtros['centrocosto_id'];
        }
        if (! empty($filtros['incluir_comentario'])) {
            $params['incluir_comentario'] = 1;
        }
        if ($consultar) {
            $params['consultar'] = 1;
        }

        return $params;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['fecha_desde'] ?? '') !== '' || ($filtros['fecha_hasta'] ?? '') !== '';
    }

    public static function subtitulo(array $filtros): string
    {
        $partes = [];
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $partes[] = 'Desde '.$filtros['fecha_desde'];
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $partes[] = 'Hasta '.$filtros['fecha_hasta'];
        }
        if (($filtros['estado'] ?? '') !== '') {
            $partes[] = 'Estado: '.EmpleadoSancionSupport::etiquetaEstado($filtros['estado']);
        }

        return implode(' · ', $partes);
    }
}
