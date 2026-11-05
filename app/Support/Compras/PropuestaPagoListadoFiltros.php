<?php

namespace App\Support\Compras;

use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Http\Request;

/**
 * Filtros del listado de propuestas de pago.
 */
class PropuestaPagoListadoFiltros
{
    public const CAMPOS = [
        'id' => ['column' => 'propuesta_pago.id', 'type' => 'entero', 'label' => 'ID'],
        'detalle' => ['column' => 'propuesta_pago.detalle', 'type' => 'texto', 'label' => 'Detalle'],
        'estado' => ['column' => 'propuesta_pago.estado', 'type' => 'texto', 'label' => 'Estado'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
    ];

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios();
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);

        return [
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'estado' => trim((string) $request->input('estado', '')),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'tipo_lote' => self::tipoLote($request),
            'busqueda' => $valor,
            'valor' => $valor,
        ];
    }

    public static function filtrosVacios(): array
    {
        return [
            'empresa_id' => 0,
            'estado' => '',
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'tipo_lote' => '',
            'busqueda' => '',
            'valor' => '',
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            || trim((string) ($filtros['estado'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_hasta'] ?? '')) !== ''
            || trim((string) ($filtros['tipo_lote'] ?? '')) !== ''
            || trim((string) ($filtros['busqueda'] ?? $filtros['valor'] ?? '')) !== '';
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (trim((string) ($filtros['estado'] ?? '')) !== '') {
            $params['estado'] = $filtros['estado'];
        }
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (trim((string) ($filtros['tipo_lote'] ?? '')) !== '') {
            $params['tipo_lote'] = $filtros['tipo_lote'];
        }
        if (trim((string) ($filtros['busqueda'] ?? $filtros['valor'] ?? '')) !== '') {
            $params['filtro_valor'] = $filtros['busqueda'] ?? $filtros['valor'];
        }

        return $params;
    }

    private static function tipoLote(Request $request): string
    {
        $tipo = strtoupper(trim((string) $request->input('tipo_lote', '')));

        return in_array($tipo, PropuestaPagoTipoLote::todos(), true) ? $tipo : '';
    }
}
