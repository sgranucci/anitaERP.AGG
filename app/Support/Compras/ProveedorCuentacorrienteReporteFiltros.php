<?php

declare(strict_types=1);

namespace App\Support\Compras;

use Illuminate\Http\Request;

/**
 * Filtros del reporte deuda / ficha de cuenta corriente de proveedores.
 */
final class ProveedorCuentacorrienteReporteFiltros
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

        $alcance = trim((string) $request->input('alcance_proveedores', self::ALCANCE_TODOS));
        if (! in_array($alcance, [self::ALCANCE_TODOS, self::ALCANCE_PUNTUALES, self::ALCANCE_RANGO], true)) {
            $alcance = self::ALCANCE_TODOS;
        }

        $expresion = trim((string) $request->input('expresion', self::EXPRESION_PESOS));
        if (! in_array($expresion, [self::EXPRESION_ORIGEN, self::EXPRESION_PESOS], true)) {
            $expresion = self::EXPRESION_PESOS;
        }

        $cotizacionModo = trim((string) $request->input('cotizacion_modo', self::COTIZACION_COMPROBANTE_O_DIA));
        if (! in_array($cotizacionModo, [self::COTIZACION_COMPROBANTE_O_DIA, self::COTIZACION_DIA], true)) {
            $cotizacionModo = self::COTIZACION_COMPROBANTE_O_DIA;
        }

        $empresaIds = self::parseEmpresaIds($request);
        $fechaHasta = trim((string) $request->input('fecha_hasta', date('Y-m-d')));
        if ($fechaHasta === '') {
            $fechaHasta = date('Y-m-d');
        }

        $proveedorIds = self::parseIdsCsv($request->input('proveedor_ids', ''));

        return [
            'empresa_ids' => $empresaIds,
            'empresa_id' => $empresaIds[0] ?? null,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'modo' => $modo,
            'alcance_proveedores' => $alcance,
            'proveedor_ids' => $proveedorIds,
            'proveedor_codigo_desde' => trim((string) $request->input('proveedor_codigo_desde', '')),
            'proveedor_codigo_hasta' => trim((string) $request->input('proveedor_codigo_hasta', '')),
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
        if (self::empresaIds($filtros) === []) {
            return false;
        }

        $alcance = (string) ($filtros['alcance_proveedores'] ?? self::ALCANCE_TODOS);
        if ($alcance === self::ALCANCE_PUNTUALES) {
            return ! empty($filtros['proveedor_ids']);
        }
        if ($alcance === self::ALCANCE_RANGO) {
            return trim((string) ($filtros['proveedor_codigo_desde'] ?? '')) !== ''
                || trim((string) ($filtros['proveedor_codigo_hasta'] ?? '')) !== '';
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
            'modo' => $filtros['modo'] ?? self::MODO_DEUDA,
            'alcance_proveedores' => $filtros['alcance_proveedores'] ?? self::ALCANCE_TODOS,
            'fecha_desde' => $filtros['fecha_desde'] ?? '',
            'fecha_hasta' => $filtros['fecha_hasta'] ?? date('Y-m-d'),
            'expresion' => $filtros['expresion'] ?? self::EXPRESION_PESOS,
            'cotizacion_modo' => $filtros['cotizacion_modo'] ?? self::COTIZACION_COMPROBANTE_O_DIA,
        ];

        foreach (self::empresaIds($filtros) as $empresaId) {
            $out['empresa_ids'][] = $empresaId;
        }
        if (empty($filtros['consolidar_empresas'])) {
            $out['consolidar_empresas'] = 0;
        }

        if (! empty($filtros['incluir_aplicaciones'])) {
            $out['incluir_aplicaciones'] = 1;
        }
        if (! empty($filtros['solo_totales'])) {
            $out['solo_totales'] = 1;
        }

        $ids = $filtros['proveedor_ids'] ?? [];
        if (is_array($ids) && $ids !== []) {
            $out['proveedor_ids'] = implode(',', array_map('intval', $ids));
        }

        $desde = trim((string) ($filtros['proveedor_codigo_desde'] ?? ''));
        $hasta = trim((string) ($filtros['proveedor_codigo_hasta'] ?? ''));
        if ($desde !== '') {
            $out['proveedor_codigo_desde'] = $desde;
        }
        if ($hasta !== '') {
            $out['proveedor_codigo_hasta'] = $hasta;
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
        return match ((string) ($filtros['alcance_proveedores'] ?? self::ALCANCE_TODOS)) {
            self::ALCANCE_PUNTUALES => 'Proveedores elegidos',
            self::ALCANCE_RANGO => 'Rango de códigos',
            default => 'Todos los proveedores',
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
     * @return list<int>
     */
    public static function empresaIds(array $filtros): array
    {
        $ids = array_values(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            static fn (int $id) => $id > 0,
        ));

        if ($ids === [] && (int) ($filtros['empresa_id'] ?? 0) > 0) {
            return [(int) $filtros['empresa_id']];
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function esMultiempresa(array $filtros): bool
    {
        return count(self::empresaIds($filtros)) > 1;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function consolidarEmpresas(array $filtros): bool
    {
        return ! empty($filtros['consolidar_empresas']) || count(self::empresaIds($filtros)) <= 1;
    }

    public static function armarSubtitulo(array $filtros, string $empresasTexto = ''): string
    {
        $partes = [];
        if ($empresasTexto !== '') {
            $partes[] = (self::esMultiempresa($filtros) ? 'Empresas: ' : 'Empresa: ').$empresasTexto;
            if (self::esMultiempresa($filtros)) {
                $partes[] = self::consolidarEmpresas($filtros) ? 'Modo: consolidado' : 'Modo: por empresa';
            }
        }
        $partes[] = self::etiquetaModo($filtros);
        $partes[] = self::etiquetaAlcance($filtros);
        $partes[] = 'Período: '.self::formatearPeriodoTexto($filtros);
        if (! empty($filtros['solo_totales'])) {
            $partes[] = 'Solo totales por proveedor';
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
    private static function parseEmpresaIds(Request $request): array
    {
        $ids = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === [] && (int) $request->input('empresa_id', 0) > 0) {
            return [(int) $request->input('empresa_id')];
        }

        return $ids;
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
