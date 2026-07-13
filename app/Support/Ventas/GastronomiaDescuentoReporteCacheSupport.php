<?php

namespace App\Support\Ventas;

final class GastronomiaDescuentoReporteCacheSupport
{
    private const SESSION_KEY = 'gastronomia_descuento_reporte_cache';

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        $relevante = [
            'estructura_v' => 2,
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'codigos_descuento' => (string) ($filtros['codigos_descuento'] ?? ''),
            'clientes_descuento_ids_raw' => (string) ($filtros['clientes_descuento_ids_raw'] ?? ''),
            'cliente_codigo_desde' => (string) ($filtros['cliente_codigo_desde'] ?? ''),
            'cliente_codigo_hasta' => (string) ($filtros['cliente_codigo_hasta'] ?? ''),
            'mozos_descuento_ids_raw' => (string) ($filtros['mozos_descuento_ids_raw'] ?? ''),
            'mozo_codigo_desde' => (string) ($filtros['mozo_codigo_desde'] ?? ''),
            'mozo_codigo_hasta' => (string) ($filtros['mozo_codigo_hasta'] ?? ''),
            'vips_descuento_ids_raw' => (string) ($filtros['vips_descuento_ids_raw'] ?? ''),
            'vip_codigo_desde' => (string) ($filtros['vip_codigo_desde'] ?? ''),
            'vip_codigo_hasta' => (string) ($filtros['vip_codigo_hasta'] ?? ''),
            'codigos_descuento_cliente' => (string) ($filtros['codigos_descuento_cliente'] ?? ''),
            'listar_todos' => ! empty($filtros['listar_todos']),
            'agrupar_por' => (string) ($filtros['agrupar_por'] ?? GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO),
            'presentacion_columnas' => ! empty($filtros['presentacion_columnas']),
            'excel_solapas' => ! empty($filtros['excel_solapas']),
        ];

        return hash('sha256', json_encode($relevante, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    public static function guardar(array $filtros, array $resultado): void
    {
        session()->put(self::SESSION_KEY, [
            'firma' => self::firma($filtros),
            'resultado' => $resultado,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    public static function recuperar(array $filtros): ?array
    {
        $cache = session()->get(self::SESSION_KEY);
        if (! is_array($cache) || ($cache['firma'] ?? '') !== self::firma($filtros)) {
            return null;
        }

        $resultado = $cache['resultado'] ?? null;

        return is_array($resultado) ? $resultado : null;
    }

    public static function limpiar(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
