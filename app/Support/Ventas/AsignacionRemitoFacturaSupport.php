<?php

namespace App\Support\Ventas;

use Illuminate\Http\Request;

/**
 * Filtros y serialización del workbench de asignación remito ↔ factura (El Bierzo).
 */
final class AsignacionRemitoFacturaSupport
{
    public const VISTA_HUERFANOS = 'huerfanos';

    public const VISTA_TODOS = 'todos';

    public const PER_PAGE = 20;

    /**
     * @return array<string, mixed>
     */
    public static function resolverFiltros(Request $request): array
    {
        $desde = trim((string) $request->input('fecha_desde', date('Y-m-d')));
        $hasta = trim((string) $request->input('fecha_hasta', ''));
        if ($desde === '') {
            $desde = date('Y-m-d');
        }
        if ($hasta !== '' && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $vista = (string) $request->input('vista', self::VISTA_HUERFANOS);
        if (! in_array($vista, [self::VISTA_HUERFANOS, self::VISTA_TODOS], true)) {
            $vista = self::VISTA_HUERFANOS;
        }

        return [
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'vista' => $vista,
            'filtro_reparto' => trim((string) $request->input('filtro_reparto', '')),
            'busqueda_remito' => trim((string) $request->input('busqueda_remito', '')),
            'busqueda_factura' => trim((string) $request->input('busqueda_factura', '')),
            'pagina_remito' => max(1, (int) $request->input('pagina_remito', 1)),
            'pagina_factura' => max(1, (int) $request->input('pagina_factura', 1)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? date('Y-m-d')),
            'vista' => (string) ($filtros['vista'] ?? self::VISTA_HUERFANOS),
        ];
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }
        if (trim((string) ($filtros['filtro_reparto'] ?? '')) !== '') {
            $params['filtro_reparto'] = (string) $filtros['filtro_reparto'];
        }

        return $params;
    }

    public static function esVistaHuerfanos(array $filtros): bool
    {
        return ($filtros['vista'] ?? self::VISTA_HUERFANOS) === self::VISTA_HUERFANOS;
    }

    /**
     * @param  array<string, mixed>  $remito
     * @param  array<string, mixed>  $factura
     * @return array{nivel: string, etiqueta: string, mismo_cliente: bool, misma_fecha: bool, kilos_cercanos: bool}
     */
    public static function puntajeCoincidencia(array $remito, array $factura): array
    {
        $mismoCliente = (int) ($remito['cliente_id'] ?? 0) > 0
            && (int) ($remito['cliente_id'] ?? 0) === (int) ($factura['cliente_id'] ?? 0);
        $mismaFecha = (string) ($remito['fecha'] ?? '') !== ''
            && (string) ($remito['fecha'] ?? '') === (string) ($factura['fecha'] ?? '');

        $kilosR = (float) ($remito['kilos'] ?? 0);
        $kilosF = (float) ($factura['kilos'] ?? 0);
        $kilosCercanos = $kilosR > 0 && $kilosF > 0
            && (abs($kilosR - $kilosF) / max($kilosR, $kilosF)) <= 0.15;

        if ($mismoCliente && ($mismaFecha || $kilosCercanos)) {
            $nivel = 'excelente';
            $etiqueta = 'Mismo cliente'.($mismaFecha ? ' y fecha' : ' y kilos similares');
        } elseif ($mismoCliente) {
            $nivel = 'bueno';
            $etiqueta = 'Mismo cliente';
        } elseif ($mismaFecha) {
            $nivel = 'regular';
            $etiqueta = 'Misma fecha, distinto cliente (el remito tomará los datos de la factura)';
        } else {
            $nivel = 'distinto';
            $etiqueta = 'Sin coincidencia de cliente ni fecha (el remito tomará los datos de la factura)';
        }

        return [
            'nivel' => $nivel,
            'etiqueta' => $etiqueta,
            'mismo_cliente' => $mismoCliente,
            'misma_fecha' => $mismaFecha,
            'kilos_cercanos' => $kilosCercanos,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $remitos
     * @param  list<array<string, mixed>>  $facturas
     * @return list<array{remito_id: int, venta_id: int, nivel: string}>
     */
    public static function sugerirEmparejamientos(array $remitos, array $facturas): array
    {
        $usadosRemito = [];
        $usadosFactura = [];
        $pares = [];

        $candidatos = [];
        foreach ($remitos as $remito) {
            if (! empty($remito['venta_id'])) {
                continue;
            }
            foreach ($facturas as $factura) {
                if (! empty($factura['remito_id'])) {
                    continue;
                }
                $score = self::puntajeCoincidencia($remito, $factura);
                if (! $score['mismo_cliente']) {
                    continue;
                }
                $peso = ($score['misma_fecha'] ? 100 : 0) + ($score['kilos_cercanos'] ? 20 : 0);
                $candidatos[] = [
                    'peso' => $peso,
                    'remito_id' => (int) $remito['id'],
                    'venta_id' => (int) $factura['id'],
                    'nivel' => $score['nivel'],
                ];
            }
        }

        usort($candidatos, static fn ($a, $b) => $b['peso'] <=> $a['peso']);

        foreach ($candidatos as $c) {
            if (isset($usadosRemito[$c['remito_id']]) || isset($usadosFactura[$c['venta_id']])) {
                continue;
            }
            $usadosRemito[$c['remito_id']] = true;
            $usadosFactura[$c['venta_id']] = true;
            $pares[] = [
                'remito_id' => $c['remito_id'],
                'venta_id' => $c['venta_id'],
                'nivel' => $c['nivel'],
            ];
        }

        return $pares;
    }
}
