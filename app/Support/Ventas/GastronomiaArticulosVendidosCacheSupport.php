<?php

namespace App\Support\Ventas;

use Carbon\Carbon;
use Illuminate\Support\Collection;

final class GastronomiaArticulosVendidosCacheSupport
{
    private const SESSION_KEY = 'gastronomia_articulos_vendidos_cache';

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        $relevante = [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'deposito_id' => (int) ($filtros['deposito_id'] ?? 0),
            'jornada_id' => (int) ($filtros['jornada_id'] ?? 0),
            'fecha_jornada' => (string) ($filtros['fecha_jornada'] ?? ''),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'modo' => (string) ($filtros['modo'] ?? GastronomiaArticulosVendidosListadoFiltros::MODO_TODOS),
            'campo' => (string) ($filtros['campo'] ?? ''),
            'operador' => (string) ($filtros['operador'] ?? 'contiene'),
            'valor' => (string) ($filtros['valor'] ?? ''),
            'valor_hasta' => (string) ($filtros['valor_hasta'] ?? ''),
            'busqueda_rapida' => ! empty($filtros['busqueda_rapida']),
        ];

        return hash('sha256', json_encode($relevante, JSON_UNESCAPED_UNICODE));
    }

    /**
     * No cachear jornada abierta de hoy: ventas siguen entrando en vivo.
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $jornadaEstado
     */
    public static function permiteUsarCache(array $filtros, ?array $jornadaEstado): bool
    {
        $fecha = GastronomiaArticulosVendidosListadoFiltros::fechaJornadaDesdeFiltros($filtros);
        if ($fecha === '') {
            return false;
        }

        $hoy = Carbon::today()->format('Y-m-d');
        if ($fecha === $hoy && ! empty($jornadaEstado['jornada_abierta'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array{filas: Collection<int, object>, totales: array<string, mixed>}  $resultado
     */
    public static function guardar(array $filtros, array $resultado): void
    {
        session()->put(self::SESSION_KEY, [
            'firma' => self::firma($filtros),
            'filas' => $resultado['filas']
                ->map(static fn (object $fila): array => (array) $fila)
                ->values()
                ->all(),
            'totales' => $resultado['totales'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filas: Collection<int, object>, totales: array<string, mixed>}|null
     */
    public static function recuperar(array $filtros): ?array
    {
        $cache = session()->get(self::SESSION_KEY);
        if (! is_array($cache) || ($cache['firma'] ?? '') !== self::firma($filtros)) {
            return null;
        }

        $filasRaw = $cache['filas'] ?? null;
        $totales = $cache['totales'] ?? null;
        if (! is_array($filasRaw) || ! is_array($totales)) {
            return null;
        }

        return [
            'filas' => collect($filasRaw)->map(static fn (array $fila): object => (object) $fila),
            'totales' => $totales,
        ];
    }

    public static function limpiar(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
