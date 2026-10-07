<?php

declare(strict_types=1);

namespace App\Support\Contable\PercepcionSufrida;

use Illuminate\Http\Request;

final class PercepcionSufridaListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        return [
            'empresa_id' => max(0, (int) $request->input('empresa_id', 0)),
            'fecha_desde' => (string) $request->input('fecha_desde', date('Y-m-01')),
            'fecha_hasta' => (string) $request->input('fecha_hasta', date('Y-m-d')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        return [
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return (int) ($filtros['empresa_id'] ?? 0) > 0
            && ($filtros['fecha_desde'] ?? '') !== ''
            && ($filtros['fecha_hasta'] ?? '') !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde === '' || $hasta === '') {
            return '';
        }

        $fmt = static fn (string $iso) => date('d/m/Y', strtotime($iso) ?: time());

        return $fmt($desde).' — '.$fmt($hasta);
    }
}
