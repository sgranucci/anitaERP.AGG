<?php

namespace App\Support\Ventas;

use Illuminate\Http\Request;

final class KiloCategoriaListadoFiltros
{
    public const ESTADOS = [
        'PENDIENTE' => 'Pedidos pendientes de facturar',
        'TODO' => 'Todos los pedidos',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $estado = strtoupper(trim((string) $request->input('estado', 'PENDIENTE')));
        if (! array_key_exists($estado, self::ESTADOS)) {
            $estado = 'PENDIENTE';
        }

        $repartoDesde = trim((string) $request->input('reparto_desde', $request->input('codigodesdetransporte', '')));
        $repartoHasta = trim((string) $request->input('reparto_hasta', $request->input('codigohastatransporte', '')));

        [$repartoDesde, $repartoHasta] = KiloPedidoListadoFiltros::normalizarRangoRepartos($repartoDesde, $repartoHasta);

        $fechaDesde = trim((string) $request->input('fecha_desde', $request->input('desdefecha', date('Y-m-d'))));
        $fechaHasta = trim((string) $request->input('fecha_hasta', $request->input('hastafecha', date('Y-m-d'))));

        return [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'reparto_desde' => $repartoDesde,
            'reparto_hasta' => $repartoHasta,
            'estado' => $estado,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            && trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $hasta = (string) ($filtros['reparto_hasta'] ?? '');

        return array_filter([
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'reparto_desde' => (string) ($filtros['reparto_desde'] ?? ''),
            'reparto_hasta' => KiloPedidoListadoFiltros::esRepartoHastaAbierto($hasta) ? '' : $hasta,
            'estado' => (string) ($filtros['estado'] ?? 'PENDIENTE'),
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearRepartoTexto(array $filtros): string
    {
        return KiloPedidoListadoFiltros::formatearRepartoTexto($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        return KiloPedidoListadoFiltros::formatearPeriodoTexto($filtros);
    }
}
