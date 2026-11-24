<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Cache de sesión del reporte Facturación y cobranzas.
 * Evita reconsultar ERP + Anita bridge en cada página / export.
 */
final class FacturacionCobranzasReporteCacheSupport
{
    private const SESSION_KEY = 'facturacion_cobranzas_reporte_cache';

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        $relevante = [
            'v' => 1,
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'orden' => FacturacionCobranzasListadoFiltros::normalizarOrden($filtros['orden'] ?? null),
            'fuente' => FacturacionCobranzasListadoFiltros::normalizarFuente($filtros['fuente'] ?? null),
            'incluye' => FacturacionCobranzasListadoFiltros::normalizarIncluye($filtros['incluye'] ?? null),
            'solo_totales' => ! empty($filtros['solo_totales']),
            'moneda_origen' => ! empty($filtros['moneda_origen']),
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 1),
            'reparto_modo' => FacturacionCobranzasListadoFiltros::normalizarRepartoModo($filtros['reparto_modo'] ?? null),
            'reparto_desde' => (string) ($filtros['reparto_desde'] ?? ''),
            'reparto_hasta' => (string) ($filtros['reparto_hasta'] ?? ''),
            'repartos' => FacturacionCobranzasListadoFiltros::codigosRepartoParticulares($filtros),
        ];

        return hash('sha256', (string) json_encode($relevante, JSON_UNESCAPED_UNICODE));
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
