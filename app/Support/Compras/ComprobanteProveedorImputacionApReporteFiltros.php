<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del informe comprobante proveedor vs imputación AP MN/ME/anticipo.
 */
final class ComprobanteProveedorImputacionApReporteFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'proveedores' => trim((string) $request->input('proveedores', '')),
            'solo_diferencias' => $request->boolean('solo_diferencias'),
            'incluir_comprobantes' => $request->has('consultar')
                ? $request->boolean('incluir_comprobantes')
                : true,
            'incluir_opa' => $request->has('consultar')
                ? $request->boolean('incluir_opa')
                : true,
            'incluir_aplicaciones' => $request->has('consultar')
                ? $request->boolean('incluir_aplicaciones')
                : true,
            'tolerancia' => self::tolerancia($request->input('tolerancia')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $query = array_filter([
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'proveedores' => ($filtros['proveedores'] ?? '') !== '' ? ($filtros['proveedores'] ?? null) : null,
            'solo_diferencias' => ! empty($filtros['solo_diferencias']) ? 1 : null,
            'incluir_comprobantes' => empty($filtros['incluir_comprobantes']) ? 0 : null,
            'incluir_opa' => empty($filtros['incluir_opa']) ? 0 : null,
            'incluir_aplicaciones' => empty($filtros['incluir_aplicaciones']) ? 0 : null,
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = array_values(array_map('intval', $filtros['empresa_ids']));
        }

        if (empty($filtros['consolidar_empresas'])) {
            $query['consolidar_empresas'] = 0;
        }

        if (! empty($filtros['incluir_comprobantes'])) {
            $query['incluir_comprobantes'] = 1;
        }
        if (! empty($filtros['incluir_opa'])) {
            $query['incluir_opa'] = 1;
        }
        if (! empty($filtros['incluir_aplicaciones'])) {
            $query['incluir_aplicaciones'] = 1;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['empresa_ids'] ?? []) !== []
            && ! empty($filtros['fecha_desde']);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $hoy = Carbon::now();

        return [
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'fecha_desde' => $hoy->copy()->startOfMonth()->format('Y-m-d'),
            'fecha_hasta' => $hoy->format('Y-m-d'),
            'proveedores' => '',
            'solo_diferencias' => false,
            'incluir_comprobantes' => true,
            'incluir_opa' => true,
            'incluir_aplicaciones' => true,
            'tolerancia' => ComprobanteProveedorImputacionApSupport::TOLERANCIA,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = self::formatearFechaPantalla($filtros['fecha_desde'] ?? null);
        $hasta = self::formatearFechaPantalla($filtros['fecha_hasta'] ?? null);

        if ($desde !== '' && $hasta !== '') {
            return $desde.' — '.$hasta;
        }

        if ($desde !== '') {
            return 'Desde '.$desde;
        }

        return '—';
    }

    public static function formatearFechaPantalla(?string $fecha): string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return '';
        }

        try {
            return Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return $fecha;
        }
    }

    private static function fechaOpcional($valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? substr($valor, 0, 10) : null;
    }

    private static function tolerancia(mixed $valor): float
    {
        $n = (float) $valor;
        if ($n <= 0) {
            return ComprobanteProveedorImputacionApSupport::TOLERANCIA;
        }

        return min(10.0, $n);
    }
}
