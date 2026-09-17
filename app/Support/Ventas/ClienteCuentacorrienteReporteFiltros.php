<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use Illuminate\Http\Request;

/**
 * Filtros del reporte deuda / ficha de cuenta corriente de clientes.
 */
final class ClienteCuentacorrienteReporteFiltros
{
    public const MODO_DEUDA = 'deuda';

    public const MODO_FICHA = 'ficha';

    public const ALCANCE_TODOS = 'todos';

    public const ALCANCE_PUNTUALES = 'puntuales';

    public const ALCANCE_RANGO = 'rango';

    public const EXPRESION_ORIGEN = 'origen';

    public const EXPRESION_PESOS = 'pesos';

    public const COTIZACION_COMPROBANTE_O_DIA = 'comprobante_o_dia';

    public const COTIZACION_DIA = 'dia';

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $modo = trim((string) $request->input('modo', self::MODO_DEUDA));
        if (! in_array($modo, [self::MODO_DEUDA, self::MODO_FICHA], true)) {
            $modo = self::MODO_DEUDA;
        }

        $alcance = trim((string) $request->input('alcance_clientes', self::ALCANCE_TODOS));
        if (! in_array($alcance, [self::ALCANCE_TODOS, self::ALCANCE_PUNTUALES, self::ALCANCE_RANGO], true)) {
            $alcance = self::ALCANCE_TODOS;
        }

        $alcanceVendedores = trim((string) $request->input('alcance_vendedores', self::ALCANCE_TODOS));
        if (! in_array($alcanceVendedores, [self::ALCANCE_TODOS, self::ALCANCE_PUNTUALES, self::ALCANCE_RANGO], true)) {
            $alcanceVendedores = self::ALCANCE_TODOS;
        }

        $expresion = trim((string) $request->input('expresion', self::EXPRESION_PESOS));
        if (! in_array($expresion, [self::EXPRESION_ORIGEN, self::EXPRESION_PESOS], true)) {
            $expresion = self::EXPRESION_PESOS;
        }

        $cotizacionModo = trim((string) $request->input('cotizacion_modo', self::COTIZACION_COMPROBANTE_O_DIA));
        if (! in_array($cotizacionModo, [self::COTIZACION_COMPROBANTE_O_DIA, self::COTIZACION_DIA], true)) {
            $cotizacionModo = self::COTIZACION_COMPROBANTE_O_DIA;
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaHasta = trim((string) $request->input('fecha_hasta', date('Y-m-d')));
        if ($fechaHasta === '') {
            $fechaHasta = date('Y-m-d');
        }

        $clienteIds = self::parseIdsCsv($request->input('cliente_ids', ''));
        $vendedorIds = self::parseIdsCsv($request->input('vendedor_ids', ''));

        return [
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'modo' => $modo,
            'alcance_clientes' => $alcance,
            'cliente_ids' => $clienteIds,
            'cliente_codigo_desde' => trim((string) $request->input('cliente_codigo_desde', '')),
            'cliente_codigo_hasta' => trim((string) $request->input('cliente_codigo_hasta', '')),
            'alcance_vendedores' => $alcanceVendedores,
            'vendedor_ids' => $vendedorIds,
            'vendedor_codigo_desde' => trim((string) $request->input('vendedor_codigo_desde', '')),
            'vendedor_codigo_hasta' => trim((string) $request->input('vendedor_codigo_hasta', '')),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => $fechaHasta,
            'incluir_aplicaciones' => $request->boolean('incluir_aplicaciones'),
            'solo_totales' => $request->boolean('solo_totales'),
            'expresion' => $expresion,
            'cotizacion_modo' => $cotizacionModo,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (empty($filtros['empresa_id'])) {
            return false;
        }

        $alcance = (string) ($filtros['alcance_clientes'] ?? self::ALCANCE_TODOS);
        if ($alcance === self::ALCANCE_PUNTUALES && empty($filtros['cliente_ids'])) {
            return false;
        }
        if ($alcance === self::ALCANCE_RANGO
            && trim((string) ($filtros['cliente_codigo_desde'] ?? '')) === ''
            && trim((string) ($filtros['cliente_codigo_hasta'] ?? '')) === '') {
            return false;
        }

        $alcanceVendedores = (string) ($filtros['alcance_vendedores'] ?? self::ALCANCE_TODOS);
        if ($alcanceVendedores === self::ALCANCE_PUNTUALES && empty($filtros['vendedor_ids'])) {
            return false;
        }
        if ($alcanceVendedores === self::ALCANCE_RANGO
            && trim((string) ($filtros['vendedor_codigo_desde'] ?? '')) === ''
            && trim((string) ($filtros['vendedor_codigo_hasta'] ?? '')) === '') {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'modo' => $filtros['modo'] ?? self::MODO_DEUDA,
            'alcance_clientes' => $filtros['alcance_clientes'] ?? self::ALCANCE_TODOS,
            'alcance_vendedores' => $filtros['alcance_vendedores'] ?? self::ALCANCE_TODOS,
            'fecha_desde' => $filtros['fecha_desde'] ?? '',
            'fecha_hasta' => $filtros['fecha_hasta'] ?? date('Y-m-d'),
            'expresion' => $filtros['expresion'] ?? self::EXPRESION_PESOS,
            'cotizacion_modo' => $filtros['cotizacion_modo'] ?? self::COTIZACION_COMPROBANTE_O_DIA,
        ];

        if (! empty($filtros['incluir_aplicaciones'])) {
            $out['incluir_aplicaciones'] = 1;
        }
        if (! empty($filtros['solo_totales'])) {
            $out['solo_totales'] = 1;
        }

        $ids = $filtros['cliente_ids'] ?? [];
        if (is_array($ids) && $ids !== []) {
            $out['cliente_ids'] = implode(',', array_map('intval', $ids));
        }

        $desde = trim((string) ($filtros['cliente_codigo_desde'] ?? ''));
        $hasta = trim((string) ($filtros['cliente_codigo_hasta'] ?? ''));
        if ($desde !== '') {
            $out['cliente_codigo_desde'] = $desde;
        }
        if ($hasta !== '') {
            $out['cliente_codigo_hasta'] = $hasta;
        }

        $vendedorIds = $filtros['vendedor_ids'] ?? [];
        if (is_array($vendedorIds) && $vendedorIds !== []) {
            $out['vendedor_ids'] = implode(',', array_map('intval', $vendedorIds));
        }

        $vendedorDesde = trim((string) ($filtros['vendedor_codigo_desde'] ?? ''));
        $vendedorHasta = trim((string) ($filtros['vendedor_codigo_hasta'] ?? ''));
        if ($vendedorDesde !== '') {
            $out['vendedor_codigo_desde'] = $vendedorDesde;
        }
        if ($vendedorHasta !== '') {
            $out['vendedor_codigo_hasta'] = $vendedorHasta;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function etiquetaModo(array $filtros): string
    {
        return (($filtros['modo'] ?? '') === self::MODO_FICHA)
            ? 'Ficha cuenta corriente'
            : 'Deuda pendiente';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function etiquetaAlcance(array $filtros): string
    {
        return match ((string) ($filtros['alcance_clientes'] ?? self::ALCANCE_TODOS)) {
            self::ALCANCE_PUNTUALES => 'Clientes elegidos',
            self::ALCANCE_RANGO => 'Rango de códigos',
            default => 'Todos los clientes',
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function etiquetaAlcanceVendedores(array $filtros): string
    {
        return match ((string) ($filtros['alcance_vendedores'] ?? self::ALCANCE_TODOS)) {
            self::ALCANCE_PUNTUALES => 'Vendedores elegidos',
            self::ALCANCE_RANGO => 'Rango de vendedores',
            default => 'Todos los vendedores',
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        $hastaFmt = $hasta !== '' ? self::fmtFecha($hasta) : 'hoy';

        if ($desde === '') {
            return 'Desde el inicio hasta '.$hastaFmt;
        }

        return self::fmtFecha($desde).' — '.$hastaFmt;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function armarSubtitulo(array $filtros, ?string $empresaNombre = null): string
    {
        $partes = [];
        if ($empresaNombre) {
            $partes[] = 'Empresa: '.$empresaNombre;
        }
        $partes[] = self::etiquetaModo($filtros);
        $partes[] = self::etiquetaAlcance($filtros);
        $partes[] = self::etiquetaAlcanceVendedores($filtros);
        $partes[] = 'Período: '.self::formatearPeriodoTexto($filtros);
        if (! empty($filtros['solo_totales'])) {
            $partes[] = 'Solo totales por cliente';
        }
        if (! empty($filtros['incluir_aplicaciones'])) {
            $partes[] = 'Con aplicaciones';
        }
        $partes[] = (($filtros['expresion'] ?? '') === self::EXPRESION_PESOS)
            ? 'Importes en moneda local'
            : 'Importes en moneda origen';

        return implode(' · ', $partes);
    }

    private static function fmtFecha(string $ymd): string
    {
        $ts = strtotime($ymd);

        return $ts ? date('d/m/Y', $ts) : $ymd;
    }

    /**
     * @return list<int>
     */
    private static function parseIdsCsv(mixed $raw): array
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/[,\s]+/', (string) $raw) ?: [];
        }

        $ids = [];
        foreach ($parts as $p) {
            $id = (int) $p;
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
