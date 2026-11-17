<?php

namespace App\Support\Stock;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del control de conversiones compra → stock → insumo en recepciones.
 */
final class RecepcionConversionControlFiltros
{
    public const PREFERENCIAS_CLAVE = 'recepcion_conversion_control';

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
            'solo_con_insumo' => $request->boolean('solo_con_insumo', true),
            'proveedor' => trim((string) $request->input('proveedor', '')),
            'sku' => trim((string) $request->input('sku', '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $hasta = Carbon::today();
        $desde = $hasta->copy()->subDays(30);

        return [
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'fecha_desde' => $desde->format('Y-m-d'),
            'fecha_hasta' => $hasta->format('Y-m-d'),
            'solo_con_insumo' => true,
            'proveedor' => '',
            'sku' => '',
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
            'proveedor' => ($filtros['proveedor'] ?? '') !== '' ? $filtros['proveedor'] : null,
            'sku' => ($filtros['sku'] ?? '') !== '' ? $filtros['sku'] : null,
        ], static fn ($v) => $v !== null && $v !== '');

        $ids = self::empresaIds($filtros);
        if ($ids !== []) {
            $query['empresa_ids'] = $ids;
        }

        if (empty($filtros['consolidar_empresas'])) {
            $query['consolidar_empresas'] = 0;
        } else {
            $query['consolidar_empresas'] = 1;
        }

        if (! empty($filtros['solo_con_insumo'])) {
            $query['solo_con_insumo'] = 1;
        } else {
            $query['solo_con_insumo'] = 0;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::empresaIds($filtros) !== []
            && ! empty($filtros['fecha_desde']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    public static function empresaIds(array $filtros): array
    {
        return collect($filtros['empresa_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = self::formatearFechaPantalla($filtros['fecha_desde'] ?? null);
        $hasta = self::formatearFechaPantalla($filtros['fecha_hasta'] ?? null);
        if ($desde === '' && $hasta === '') {
            return 'Sin período';
        }
        if ($desde !== '' && $hasta === '') {
            return 'Desde '.$desde;
        }
        if ($desde === '' && $hasta !== '') {
            return 'Hasta '.$hasta;
        }

        return $desde.' — '.$hasta;
    }

    public static function formatearFechaPantalla(mixed $fecha): string
    {
        $ymd = self::fechaOpcional($fecha);
        if ($ymd === null) {
            return '';
        }
        try {
            return Carbon::parse($ymd)->format('d/m/Y');
        } catch (\Throwable) {
            return $ymd;
        }
    }

    private static function fechaOpcional(mixed $valor): ?string
    {
        $raw = trim((string) ($valor ?? ''));
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
