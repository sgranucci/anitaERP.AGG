<?php

namespace App\Support\Ticket;

use Illuminate\Support\Facades\Cache;

/**
 * Snapshot del informe estadístico de tickets (file cache).
 */
final class TicketEstadisticaReporteCacheSupport
{
    private const TTL_HORAS = 2;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function cacheKey(array $filtros): string
    {
        $userId = (int) (auth()->id() ?? 0);

        return 'ticket_estadistica_reporte_v1_'.$userId.'_'.TicketEstadisticaReporteFiltros::firma($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultado
     */
    public static function guardar(array $filtros, array $resultado): void
    {
        $filas = $resultado['filas'] ?? [];
        if ($filas instanceof \Illuminate\Support\Collection) {
            $resultado['filas'] = $filas->values()->all();
        } elseif (! is_array($filas)) {
            $resultado['filas'] = [];
        }

        Cache::store('file')->put(self::cacheKey($filtros), [
            'firma' => TicketEstadisticaReporteFiltros::firma($filtros),
            'resultado' => $resultado,
        ], now()->addHours(self::TTL_HORAS));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>|null
     */
    public static function recuperar(array $filtros): ?array
    {
        $firma = TicketEstadisticaReporteFiltros::firma($filtros);
        $pack = Cache::store('file')->get(self::cacheKey($filtros));
        if (! is_array($pack) || ($pack['firma'] ?? '') !== $firma) {
            return null;
        }
        $resultado = $pack['resultado'] ?? null;
        if (! is_array($resultado)) {
            return null;
        }
        $filas = $resultado['filas'] ?? [];
        $resultado['filas'] = collect(is_array($filas) ? $filas : [])->map(static function ($f) {
            return is_array($f) ? $f : (array) $f;
        })->values();

        return $resultado;
    }
}
